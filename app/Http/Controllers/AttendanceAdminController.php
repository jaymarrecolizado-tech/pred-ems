<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCheckpoint;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\Dtr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Attendance & DTR — admin/HR side (Phase 5).
 *
 * Manages geofence checkpoints (map-plotted zones), the correction-request
 * review queue (the only path that alters a log), a timelog browser with
 * manual HR entries, office-hours settings, and per-employee DTR issuance.
 */
class AttendanceAdminController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Checkpoints                                                        */
    /* ------------------------------------------------------------------ */

    public function checkpoints(): View
    {
        return view('attendance.checkpoints', [
            'checkpoints' => AttendanceCheckpoint::with('createdBy')->latest()->get(),
            'defaultRadius' => 200,
        ]);
    }

    public function editCheckpoint(AttendanceCheckpoint $checkpoint): View
    {
        return view('attendance.checkpoints', [
            'checkpoints' => AttendanceCheckpoint::with('createdBy')->latest()->get(),
            'editing' => $checkpoint,
            'defaultRadius' => 200,
        ]);
    }

    public function storeCheckpoint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $checkpoint = AttendanceCheckpoint::create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'created_by' => auth()->id(),
        ]);

        Audit::record('checkpoint_created', $checkpoint, [], $checkpoint->toArray());

        return back()->with('success', "Checkpoint \"{$checkpoint->name}\" created.");
    }

    public function updateCheckpoint(Request $request, AttendanceCheckpoint $checkpoint): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $checkpoint->toArray();
        $checkpoint->update([...$validated, 'is_active' => $request->boolean('is_active')]);

        Audit::record('checkpoint_updated', $checkpoint, $old, $checkpoint->toArray());

        return back()->with('success', "Checkpoint \"{$checkpoint->name}\" updated.");
    }

    public function destroyCheckpoint(AttendanceCheckpoint $checkpoint): RedirectResponse
    {
        Audit::record('checkpoint_deleted', $checkpoint, $checkpoint->toArray(), []);
        $checkpoint->delete();

        return back()->with('success', 'Checkpoint deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  Correction requests                                                */
    /* ------------------------------------------------------------------ */

    public function corrections(Request $request): View
    {
        $status = $request->string('status', 'pending');

        $corrections = AttendanceCorrection::query()
            ->with(['employee', 'employee.position'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('attendance.corrections', [
            'corrections' => $corrections,
            'status' => $status,
            'statuses' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'],
        ]);
    }

    /**
     * Approve a correction: the ONLY path that alters a recorded punch. The
     * corrected time becomes a new entry stamped as HR-verified.
     */
    public function approveCorrection(AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->isPending(), 409, 'This request was already reviewed.');

        $logDate = $correction->log_date->toDateString();

        $data = [
            'employee_id' => $correction->employee_id,
            'log_date' => $logDate,
            'punch_type' => $correction->punch_type,
            'punched_at' => $logDate . ' ' . $correction->requested_time->format('H:i:s'),
            'source' => 'hr_correction',
            'remarks' => 'Corrected via request #' . $correction->id . ' — ' . $correction->reason,
        ];

        // updateOrCreate handles both the fresh-insert and the existing-row
        // path atomically — no null-object risk under concurrent approvals.
        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $correction->employee_id, 'log_date' => $logDate, 'punch_type' => $correction->punch_type],
            $data
        );

        $correction->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        Audit::record('correction_approved', $log, [], [
            'correction_id' => $correction->id,
            'punch_type' => $correction->punch_type,
            'requested_time' => $correction->requested_time->format('H:i'),
            'reason' => $correction->reason,
        ]);

        return back()->with('success', 'Correction approved and applied to the timelog.');
    }

    public function rejectCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->isPending(), 409, 'This request was already reviewed.');

        $validated = $request->validate([
            'denial_reason' => ['required', 'string', 'max:500'],
        ]);

        $correction->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'denial_reason' => $validated['denial_reason'],
        ]);

        Audit::record('correction_rejected', $correction, [], $correction->toArray());

        return back()->with('success', 'Correction request rejected.');
    }

    /* ------------------------------------------------------------------ */
    /*  Timelog browser + manual HR entry                                  */
    /* ------------------------------------------------------------------ */

    public function logs(Request $request): View
    {
        $query = AttendanceLog::query()->with(['employee', 'checkpoint']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('date')) {
            $query->where('log_date', $request->date('date')?->toDateString());
        }
        if ($request->filled('punch_type')) {
            $query->where('punch_type', $request->string('punch_type'));
        }

        return view('attendance.logs', [
            'logs' => $query->latest('log_date')->latest('punched_at')->paginate(30)->withQueryString(),
            'employees' => Employee::orderBy('last_name')->orderBy('first_name')->get(),
            'punchTypes' => ['am_in' => 'AM In', 'am_out' => 'AM Out', 'pm_in' => 'PM In', 'pm_out' => 'PM Out'],
        ]);
    }

    /**
     * Manual HR punch (override) — used when an employee was outside the
     * geofence (field duty, forgotten punch, etc.). Audited.
     */
    public function storeManualPunch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'log_date' => ['required', 'date'],
            'punch_type' => ['required', 'in:am_in,am_out,pm_in,pm_out'],
            'time' => ['required', 'date_format:H:i'],
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $logDate = $validated['log_date'];

        $data = [
            'employee_id' => $validated['employee_id'],
            'log_date' => $logDate,
            'punch_type' => $validated['punch_type'],
            'punched_at' => $logDate . ' ' . $validated['time'] . ':00',
            'source' => 'hr_manual',
            'remarks' => 'HR entry — ' . $validated['remarks'],
        ];

        // updateOrCreate handles both the fresh-insert and the existing-row
        // path atomically — no null-object risk under concurrent HR entries.
        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'log_date' => $logDate, 'punch_type' => $validated['punch_type']],
            $data
        );

        Audit::record('punch_entered', $log, [], $log->toArray());

        return back()->with('success', 'Manual punch recorded.');
    }

    /* ------------------------------------------------------------------ */
    /*  Per-employee DTR                                                   */
    /* ------------------------------------------------------------------ */

    public function employeeDtr(Request $request, Employee $employee): View
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        return view('documents.dtr', [
            'dtr' => Dtr::build($employee, $month, $year),
        ]);
    }

    public function employeeDtrPdf(Request $request, Employee $employee): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $dtr = Dtr::build($employee, $month, $year);

        // Official issued copy — mint a reference number and track it.
        $referenceNo = null;
        $document = null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $referenceNo = DocumentIssuer::nextReferenceNo('DTR');

            $pdf = Pdf::loadView('documents.dtr', [
                'dtr' => $dtr,
                'referenceNo' => $referenceNo,
            ])->setPaper('a4', 'portrait');

            $pdfOutput = $pdf->output();

            try {
                $document = Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'dtr',
                    'reference_no' => $referenceNo,
                    'remarks' => 'Daily Time Record — ' . $dtr['monthLabel'],
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                ]);
                break;
            } catch (QueryException $e) {
                if ((int) $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
                continue;
            }
        }

        $filename = 'DTR_' . str_replace([' ', '.'], '_', $employee->full_name) . '_' . $dtr['monthLabel'] . '.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------------ */
    /*  Settings                                                           */
    /* ------------------------------------------------------------------ */

    public function settings(): View
    {
        return view('attendance.settings', [
            'officeHours' => Setting::officeHours(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'am_start' => ['required', 'date_format:H:i'],
            'am_end' => ['required', 'date_format:H:i'],
            'pm_start' => ['required', 'date_format:H:i'],
            'pm_end' => ['required', 'date_format:H:i'],
        ]);

        Setting::set('office_hours', [
            'am_start' => $validated['am_start'],
            'am_end' => $validated['am_end'],
            'pm_start' => $validated['pm_start'],
            'pm_end' => $validated['pm_end'],
        ]);

        return back()->with('success', 'Office hours updated.');
    }
}
