<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckpointRequest;
use App\Http\Requests\HolidayRequest;
use App\Http\Requests\ManualPunchRequest;
use App\Http\Requests\RejectCorrectionRequest;
use App\Http\Requests\WorkScheduleRequest;
use App\Models\AttendanceCheckpoint;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\DocumentQr;
use App\Support\Dtr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
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
    /*  Checkpoints */
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

    public function storeCheckpoint(CheckpointRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $checkpoint = AttendanceCheckpoint::create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'created_by' => auth()->id(),
        ]);

        Audit::record('checkpoint_created', $checkpoint, [], $checkpoint->toArray());

        return back()->with('success', "Checkpoint \"{$checkpoint->name}\" created.");
    }

    public function updateCheckpoint(CheckpointRequest $request, AttendanceCheckpoint $checkpoint): RedirectResponse
    {
        $validated = $request->validated();

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
    /*  Correction requests */
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
            'punched_at' => $logDate.' '.$correction->requested_time->format('H:i:s'),
            'source' => 'hr_correction',
            'remarks' => 'Corrected via request #'.$correction->id.' — '.$correction->reason,
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

    public function rejectCorrection(RejectCorrectionRequest $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($correction->isPending(), 409, 'This request was already reviewed.');

        $validated = $request->validated();

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
    /*  Timelog browser + manual HR entry */
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
    public function storeManualPunch(ManualPunchRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $logDate = $validated['log_date'];

        $data = [
            'employee_id' => $validated['employee_id'],
            'log_date' => $logDate,
            'punch_type' => $validated['punch_type'],
            'punched_at' => $logDate.' '.$validated['time'].':00',
            'source' => 'hr_manual',
            'remarks' => 'HR entry — '.$validated['remarks'],
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
    /*  Per-employee DTR */
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
                'qrDataUri' => DocumentQr::dataUri($referenceNo),
            ])->setPaper('a4', 'portrait');

            $pdfOutput = $pdf->output();

            try {
                $document = Document::create([
                    'employee_id' => $employee->id,
                    'document_type' => 'dtr',
                    'reference_no' => $referenceNo,
                    'remarks' => 'Daily Time Record — '.$dtr['monthLabel'],
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

        $filename = 'DTR_'.str_replace([' ', '.'], '_', $employee->full_name).'_'.$dtr['monthLabel'].'.pdf';

        return response($pdfOutput)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /* ------------------------------------------------------------------ */
    /*  Work schedules + holidays (AOM 2026-020 flexible scheduling) */
    /* ------------------------------------------------------------------ */

    public function settings(): View
    {
        return view('attendance.settings', [
            'schedules' => WorkSchedule::with('revertSchedule')->orderBy('starts_on')->orderBy('id')->get(),
            'holidays' => Holiday::orderBy('date')->get(),
            'editingSchedule' => null,
        ]);
    }

    public function editSchedule(WorkSchedule $schedule): View
    {
        return view('attendance.settings', [
            'schedules' => WorkSchedule::with('revertSchedule')->orderBy('starts_on')->orderBy('id')->get(),
            'holidays' => Holiday::orderBy('date')->get(),
            'editingSchedule' => $schedule,
        ]);
    }

    private function validateSchedule(WorkScheduleRequest $request): array
    {
        $data = $request->validated();

        // Normalise the per-day-of-week JSON (1 = Mon … 7 = Sun).
        $days = [];
        foreach (range(1, 7) as $iso) {
            $d = $data['days'][(string) $iso] ?? [];
            $working = ! empty($d['work']);

            $days[(string) $iso] = ['work' => $working];
            if ($working) {
                $days[(string) $iso] += [
                    'am_start' => $d['am_start'] ?? '08:00',
                    'am_end' => $d['am_end'] ?? '12:00',
                    'pm_start' => $d['pm_start'] ?? '13:00',
                    'pm_end' => $d['pm_end'] ?? '17:00',
                ];
            }
        }

        if (! collect($days)->contains('work', true)) {
            throw ValidationException::withMessages(['days' => 'At least one working day is required.']);
        }

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'revert_schedule_id' => $data['revert_schedule_id'] ?? null,
            'days' => $days,
        ];
    }

    public function storeSchedule(WorkScheduleRequest $request): RedirectResponse
    {
        $data = $this->validateSchedule($request);

        $schedule = WorkSchedule::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        Audit::record('work_schedule_created', $schedule, [], $schedule->toArray());

        return redirect()->route('attendance.settings')
            ->with('success', "Work schedule \"{$schedule->name}\" created.");
    }

    public function updateSchedule(WorkScheduleRequest $request, WorkSchedule $schedule): RedirectResponse
    {
        $data = $this->validateSchedule($request);

        // A schedule cannot revert to itself.
        if ($data['revert_schedule_id'] == $schedule->id) {
            throw ValidationException::withMessages(['revert_schedule_id' => 'A schedule cannot revert to itself.']);
        }

        $old = $schedule->toArray();
        $schedule->update($data);

        Audit::record('work_schedule_updated', $schedule, $old, $schedule->toArray());

        return redirect()->route('attendance.settings')
            ->with('success', "Work schedule \"{$schedule->name}\" updated.");
    }

    public function destroySchedule(WorkSchedule $schedule): RedirectResponse
    {
        Audit::record('work_schedule_deleted', $schedule, $schedule->toArray(), []);
        $schedule->delete();

        return redirect()->route('attendance.settings')->with('success', 'Work schedule deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  Holidays */
    /* ------------------------------------------------------------------ */

    public function storeHoliday(HolidayRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $holiday = Holiday::create([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'type' => $validated['type'],
            'is_repeating' => $request->boolean('is_repeating'),
            'created_by' => auth()->id(),
        ]);

        Audit::record('holiday_created', $holiday, [], $holiday->toArray());

        return back()->with('success', "Holiday \"{$holiday->name}\" added.");
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        Audit::record('holiday_deleted', $holiday, $holiday->toArray(), []);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
