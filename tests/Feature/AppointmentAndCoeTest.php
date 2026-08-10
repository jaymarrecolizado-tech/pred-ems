<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Tests\TestCase;

class AppointmentAndCoeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Smoke-test against the real (seeded) MySQL database, not :memory:.
        config(['database.default' => 'mysql']);
        config([
            'database.connections.mysql.database' => 'hris',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        $this->testStartedAt = now();
    }

    protected function tearDown(): void
    {
        // Remove COE documents minted by this test so the shared dev DB's
        // reference-number counter stays stable across runs.
        Document::where('document_type', 'certificate_of_employment')
            ->where('generated_by', $this->adminUser()->id)
            ->where('generated_at', '>=', $this->testStartedAt)
            ->delete();

        parent::tearDown();
    }

    private \DateTimeInterface $testStartedAt;

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    /* ------------------------------------------------------------------ */
    /*  Appointment Manager                                                */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_add_appointment_and_syncs_snapshot(): void
    {
        $employee = Employee::firstOrFail();
        $position = Position::firstOrFail();
        $snapshotBefore = $employee->only(['position_id', 'division_id', 'employment_type_id', 'salary_grade', 'step', 'monthly_salary', 'date_original_appointment']);

        try {
            $this->actingAs($this->adminUser())
                ->post("/employees/{$employee->id}/appointments", [
                    'appointment_type' => 'promotion',
                    'appointment_status' => 'approved',
                    'position_id' => $position->id,
                    'salary_grade' => 18,
                    'step' => 2,
                    'monthly_salary' => 40000,
                    'effective_from' => now()->toDateString(),
                    'remarks' => 'Test promotion',
                ])
                ->assertRedirect("/employees/{$employee->id}");

            $this->assertDatabaseHas('appointments', [
                'employee_id' => $employee->id,
                'appointment_type' => 'promotion',
                'position_id' => $position->id,
                'effective_from' => now()->toDateString(),
                'remarks' => 'Test promotion',
            ]);

            $employee->refresh();
            $this->assertSame($position->id, $employee->position_id);
        } finally {
            // Restore the real employee's snapshot and remove the test row so
            // the shared dev DB stays exactly as it was.
            Appointment::where('employee_id', $employee->id)
                ->where('remarks', 'Test promotion')
                ->delete();
            $employee->update($snapshotBefore);
        }
    }

    public function test_deleting_last_appointment_reverts_snapshot(): void
    {
        $employee = Employee::create([
            'employee_number' => 'RO2-TEST-' . now()->timestamp,
            'first_name' => 'Snapshot',
            'last_name' => 'Test',
            'employment_type_id' => 1,
            'status' => 'active',
        ]);

        try {
            $appointment = Appointment::create([
                'employee_id' => $employee->id,
                'appointment_type' => 'original',
                'appointment_status' => 'approved',
                'position_id' => Position::firstOrFail()->id,
                'monthly_salary' => 30000,
                'effective_from' => now()->toDateString(),
            ]);

            // Adding the first (original) appointment establishes the original-appointment date.
            $this->actingAs($this->adminUser())
                ->put("/appointments/{$appointment->id}", [
                    'appointment_type' => 'original',
                    'appointment_status' => 'approved',
                    'position_id' => $appointment->position_id,
                    'monthly_salary' => 30000,
                    'effective_from' => $appointment->effective_from->toDateString(),
                ]);

            $employee->refresh();
            $this->assertNotNull($employee->date_original_appointment);
            $this->assertNotNull($employee->position_id);

            // Deleting the only appointment reverts the snapshot.
            $this->actingAs($this->adminUser())
                ->delete("/appointments/{$appointment->id}")
                ->assertRedirect("/employees/{$employee->id}");

            $employee->refresh();
            $this->assertNull($employee->position_id);
            $this->assertNull($employee->monthly_salary);
        } finally {
            $employee->appointments()->delete();
            $employee->delete();
        }
    }

    public function test_hr_can_edit_and_delete_appointment(): void
    {
        $employee = Employee::firstOrFail();

        $appointment = Appointment::create([
            'employee_id' => $employee->id,
            'appointment_type' => 'original',
            'appointment_status' => 'approved',
            'effective_from' => now()->subYears(2)->toDateString(),
        ]);

        $this->actingAs($this->adminUser())
            ->put("/appointments/{$appointment->id}", [
                'appointment_type' => 'transfer',
                'appointment_status' => 'approved',
                'effective_from' => $appointment->effective_from->toDateString(),
                'effective_to' => now()->toDateString(),
            ])
            ->assertRedirect("/employees/{$employee->id}");

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'appointment_type' => 'transfer']);

        $this->actingAs($this->adminUser())
            ->delete("/appointments/{$appointment->id}")
            ->assertRedirect("/employees/{$employee->id}");

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    public function test_appointment_validation_rejects_bad_dates(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->adminUser())
            ->post("/employees/{$employee->id}/appointments", [
                'appointment_type' => 'promotion',
                'appointment_status' => 'approved',
                'effective_from' => now()->toDateString(),
                'effective_to' => now()->subYear()->toDateString(), // before effective_from
            ])
            ->assertSessionHasErrors('effective_to');
    }

    public function test_employee_cannot_manage_appointments(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $this->actingAs($user)
            ->get("/employees/{$employee->id}/appointments/create")
            ->assertForbidden();

        $this->actingAs($user)
            ->post("/employees/{$employee->id}/appointments", [
                'appointment_type' => 'promotion',
                'appointment_status' => 'approved',
                'effective_from' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Certificate of Employment                                          */
    /* ------------------------------------------------------------------ */

    public function test_hr_can_view_coe_and_download_pdf(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->adminUser())
            ->get("/employees/{$employee->id}/coe")
            ->assertOk()
            ->assertSee('CERTIFICATION')
            ->assertSee('TO WHOM IT MAY CONCERN');

        $response = $this->actingAs($this->adminUser())
            ->get("/employees/{$employee->id}/coe/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?: '');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertDatabaseHas('documents', [
            'employee_id' => $employee->id,
            'document_type' => 'certificate_of_employment',
            'generated_by' => $this->adminUser()->id,
        ]);
    }

    public function test_employee_can_view_own_coe_but_not_others(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $other = Employee::where('id', '!=', $employee->id)->firstOrFail();

        $this->actingAs($user)->get("/employees/{$employee->id}/coe")->assertOk();
        $this->actingAs($user)->get("/employees/{$other->id}/coe")->assertForbidden();
    }
}
