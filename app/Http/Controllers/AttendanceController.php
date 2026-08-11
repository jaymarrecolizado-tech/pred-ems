<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectionRequest;
use App\Http\Requests\PunchRequest;
use App\Models\AttendanceCheckpoint;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\Dtr;
use App\Support\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Attendance & DTR — employee self-service side (Phase 5).
 *
 * Punches are geofenced: the browser sends its GPS position and the punch is
 * accepted only when it falls inside an active checkpoint's radius. Logs are
 * append-only from the client; every alteration goes through the correction
 * request queue for HR review.
 */
class AttendanceController extends Controller
{
    /**
     * My attendance page: today's punches + punch buttons, this month's DTR
     * preview, and my correction requests.
     */
    public function index(): View
    {
        $employee = auth()->user()->employee;

        abort_if(! $employee, 403, 'No employee 201-file record linked to this account.');

        $today = now()->toDateString();
        $punches = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('log_date', $today)
            ->pluck('punched_at', 'punch_type');

        $dtr = Dtr::build($employee, now()->month, now()->year);

        return view('attendance.index', [
            'employee' => $employee,
            'punches' => $punches,
            'dtr' => $dtr,
            'checkpoints' => AttendanceCheckpoint::where('is_active', true)->get(),
            'corrections' => AttendanceCorrection::query()
                ->where('employee_id', $employee->id)
                ->latest()
                ->take(10)
                ->get(),
            'officeHours' => Setting::officeHours(),
            'todaySchedule' => Schedule::day(now()),
        ]);
    }

    /**
     * Geofenced punch. Validates the GPS position against the nearest active
     * checkpoint before recording an AM/PM in/out entry.
     */
    public function punch(PunchRequest $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        abort_if(! $employee, 403, 'No employee 201-file record linked to this account.');

        $validated = $request->validated();

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $nearest = AttendanceCheckpoint::where('is_active', true)->get()
            ->map(fn ($cp) => ['cp' => $cp, 'meters' => $cp->distanceTo($lat, $lng)])
            ->sortBy('meters')
            ->first();

        if (! $nearest) {
            return response()->json([
                'message' => 'No active checkpoint is configured. Please contact HR.',
            ], 422);
        }

        if ($nearest['meters'] > $nearest['cp']->radius_meters) {
            return response()->json([
                'message' => sprintf(
                    'You are %.0f m from "%s" — outside the %.0f m checkpoint radius. Move inside the zone or contact HR for a manual entry.',
                    $nearest['meters'],
                    $nearest['cp']->name,
                    $nearest['cp']->radius_meters
                ),
                'distance_meters' => round($nearest['meters']),
                'checkpoint' => $nearest['cp']->name,
            ], 422);
        }

        // Determine the expected punch: same pattern as a physical bundy clock —
        // AM in → AM out → PM in → PM out, in order.
        $now = now();
        $today = $now->toDateString();

        $punched = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('log_date', $today)
            ->pluck('punch_type');

        $punchType = $this->expectedPunchType($punched, $now);

        if (! $punchType) {
            return response()->json([
                'message' => 'All four punches for today are already recorded. See you tomorrow!',
            ], 422);
        }

        try {
            $log = AttendanceLog::create([
                'employee_id' => $employee->id,
                'log_date' => $today,
                'punch_type' => $punchType,
                'punched_at' => $now,
                'latitude' => $lat,
                'longitude' => $lng,
                'checkpoint_id' => $nearest['cp']->id,
                'source' => 'geofence',
            ]);
        } catch (QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'This punch was already recorded.',
                ], 422);
            }
            throw $e;
        }

        Audit::record('punched', $log, [], [
            'punch_type' => $punchType,
            'checkpoint' => $nearest['cp']->name,
            'distance_meters' => round($nearest['meters']),
        ]);

        return response()->json([
            'message' => $log->punch_type_label.' recorded at '.$log->time.' ('.$nearest['cp']->name.').',
            'punch_type' => $punchType,
            'time' => $log->time,
            'checkpoint' => $nearest['cp']->name,
        ]);
    }

    /**
     * Employee requests an alteration to a recorded punch — goes to HR for
     * approval; logs are never edited in place.
     */
    public function requestCorrection(CorrectionRequest $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        abort_if(! $employee, 403, 'No employee 201-file record linked to this account.');

        $validated = $request->validated();

        // One pending request per (employee, date, punch) — otherwise two
        // requests for the same punch could both be approved and silently
        // overwrite each other in the log.
        $duplicate = AttendanceCorrection::query()
            ->where('employee_id', $employee->id)
            ->where('log_date', $validated['log_date'])
            ->where('punch_type', $validated['punch_type'])
            ->where('status', 'pending')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'You already have a pending correction request for this date and punch. Wait for HR review or contact HR directly.',
            ], 422);
        }

        $correction = AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'log_date' => $validated['log_date'],
            'punch_type' => $validated['punch_type'],
            'requested_time' => $validated['requested_time'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        Audit::record('correction_requested', $correction, [], $correction->toArray());

        return response()->json([
            'message' => 'Correction request submitted for HR review.',
            'id' => $correction->id,
        ]);
    }

    /**
     * My DTR for a given month, HTML preview.
     */
    public function dtr(Request $request): View
    {
        $employee = auth()->user()->employee;
        abort_if(! $employee, 403);

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        return view('documents.dtr', [
            'dtr' => Dtr::build($employee, $month, $year),
        ]);
    }

    /**
     * My DTR as a downloadable CSC Form 48 PDF.
     */
    public function dtrPdf(Request $request): Response
    {
        $employee = auth()->user()->employee;
        abort_if(! $employee, 403);

        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $dtr = Dtr::build($employee, $month, $year);

        $filename = 'DTR_'.str_replace([' ', '.'], '_', $employee->full_name).'_'.$dtr['monthLabel'].'.pdf';

        // Employee self-service download stays reference-free (reference
        // numbers are minted only for official HR-issued copies).
        $pdf = Pdf::loadView('documents.dtr', [
            'dtr' => $dtr,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Order of punches in a day: AM in → AM out → PM in → PM out. If the
     * morning was skipped entirely (arrived after lunch), the first punch of
     * the day becomes a PM in so staff can always log in.
     */
    private function expectedPunchType($punched, Carbon $now): ?string
    {
        $punched = collect($punched);

        if (! $punched->contains('am_in')) {
            // No AM in yet. After lunch, with no morning punches at all,
            // start straight into the PM session; otherwise log the AM in.
            if ($now->hour >= 12 && ! $punched->contains('am_out')) {
                return 'pm_in';
            }

            return 'am_in';
        }
        if (! $punched->contains('am_out')) {
            return 'am_out';
        }
        if (! $punched->contains('pm_in')) {
            return 'pm_in';
        }
        if (! $punched->contains('pm_out')) {
            return 'pm_out';
        }

        return null;
    }
}
