<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Appointment Manager — HR maintains each employee's chronological appointment
 * history (original appointment, promotions, transfers, re-appointments).
 *
 * The appointments table is the source of truth for the CSC Service Record,
 * so every change here also re-syncs the employee's current-position snapshot.
 */
class AppointmentController extends Controller
{
    public function create(Employee $employee): View
    {
        return view('appointments.create', array_merge(
            ['employee' => $employee],
            $this->formOptions()
        ));
    }

    public function store(AppointmentRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        $data['employee_id'] = $employee->id;
        $data['created_by'] = auth()->id();

        $appointment = Appointment::create($data);
        $this->syncEmployeeSnapshot($employee);

        Audit::record('created', $appointment, [], $appointment->toArray());

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Appointment record added to the service history.');
    }

    public function edit(Appointment $appointment): View
    {
        return view('appointments.edit', array_merge(
            ['appointment' => $appointment, 'employee' => $appointment->employee],
            $this->formOptions()
        ));
    }

    public function update(AppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $old = $appointment->toArray();
        $appointment->update($request->validated());
        $this->syncEmployeeSnapshot($appointment->employee);

        Audit::record('updated', $appointment, $old, $appointment->toArray());

        return redirect()
            ->route('employees.show', $appointment->employee)
            ->with('success', 'Appointment record updated.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $employee = $appointment->employee;

        Audit::record('deleted', $appointment, $appointment->toArray(), []);
        $appointment->delete();
        $this->syncEmployeeSnapshot($employee);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Appointment record removed.');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    private function formOptions(): array
    {
        return [
            'positions' => Position::orderBy('title')->get(),
            'divisions' => Division::orderBy('name')->get(),
            'employmentTypes' => EmploymentType::orderBy('sort_order')->get(),
            'appointmentTypes' => [
                'original' => 'Original Appointment',
                'promotion' => 'Promotion',
                'transfer' => 'Transfer',
                'demotion' => 'Demotion',
                're_appointment' => 'Re-Appointment',
                'co_terminus' => 'Co-Terminus',
                'job_order' => 'Job Order',
                'contract_of_service' => 'Contract of Service',
                'gip' => 'GIP (Internship)',
                'casual' => 'Casual',
                'temporary' => 'Temporary',
            ],
            'statuses' => [
                'approved' => 'Approved',
                'pending' => 'Pending',
                'revoked' => 'Revoked',
            ],
        ];
    }

    /**
     * The appointment history is the source of truth for the current
     * position/salary snapshot shown on the 201 file. After any change, the
     * employee record mirrors the *current* appointment — the latest row with
     * no effective_to (still open), falling back to the latest by date so a
     * future-dated appointment never leaks into the snapshot early.
     *
     * The original-appointment date is only ever established from a true
     * `original` appointment row, never from a promotion or re-appointment.
     * When the history is emptied, the snapshot reverts to null so it cannot
     * advertise stale employment data.
     */
    private function syncEmployeeSnapshot(Employee $employee): void
    {
        // NOTE: appointments() already orders by effective_from ASC, so
        // reorder() must come first — otherwise the DESC is appended and the
        // query still returns the oldest open appointment.
        $current = $employee->appointments()
            ->whereNull('effective_to')
            ->reorder()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first()
            ?? $employee->appointments()
                ->reorder()
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

        if (! $current) {
            $employee->update([
                'position_id' => null,
                'division_id' => null,
                'employment_type_id' => null,
                'salary_grade' => null,
                'step' => null,
                'monthly_salary' => null,
            ]);

            return;
        }

        $employee->update([
            'position_id' => $current->position_id ?? $employee->position_id,
            'division_id' => $current->division_id ?? $employee->division_id,
            'employment_type_id' => $current->employment_type_id ?? $employee->employment_type_id,
            'salary_grade' => $current->salary_grade ?? $employee->salary_grade,
            'step' => $current->step ?? $employee->step,
            'monthly_salary' => $current->monthly_salary ?? $employee->monthly_salary,
        ]);

        if (! $employee->date_original_appointment && $current->appointment_type === 'original') {
            $employee->update(['date_original_appointment' => $current->effective_from]);
        }
    }
}
