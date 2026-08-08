<?php

namespace Tests\Feature;

use App\Models\AttendanceCheckpoint;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class AttendanceTest extends TestCase
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
        // Clean up attendance rows created by this test so the shared dev DB
        // stays stable across runs.
        $ids = $this->createdLogIds ?? [];
        if ($ids) {
            AttendanceLog::whereIn('id', $ids)->delete();
        }

        $corrIds = $this->createdCorrectionIds ?? [];
        if ($corrIds) {
            AttendanceCorrection::whereIn('id', $corrIds)->delete();
        }

        Document::where('document_type', 'dtr')
            ->where('generated_by', $this->adminUser()->id)
            ->where('generated_at', '>=', $this->testStartedAt)
            ->delete();

        parent::tearDown();
    }

    private \DateTimeInterface $testStartedAt;
    private array $createdLogIds = [];
    private array $createdCorrectionIds = [];

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    private function checkpoint(): AttendanceCheckpoint
    {
        return AttendanceCheckpoint::where('is_active', true)->firstOrFail();
    }

    /* ------------------------------------------------------------------ */
    /*  Geofence                                                           */
    /* ------------------------------------------------------------------ */

    public function test_punch_within_radius_is_accepted(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $cp = $this->checkpoint();

        // Position exactly at the checkpoint — inside any radius.
        $response = $this->actingAs($user)->postJson('/attendance/punch', [
            'latitude' => (float) $cp->latitude,
            'longitude' => (float) $cp->longitude,
        ]);

        $response->assertOk();

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('log_date', now()->toDateString())
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('geofence', $log->source);
        $this->assertSame($cp->id, $log->checkpoint_id);
        $this->createdLogIds[] = $log->id;
    }

    public function test_punch_outside_radius_is_rejected(): void
    {
        $user = $this->employeeUser();

        // Far away (approx. antipode-ish offset) — no active checkpoint anywhere near.
        $response = $this->actingAs($user)->postJson('/attendance/punch', [
            'latitude' => -30.0,
            'longitude' => 140.0,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => true]);

        // No punch recorded for this user/date.
        $this->assertDatabaseMissing('attendance_logs', [
            'employee_id' => $user->employee->id,
            'log_date' => now()->toDateString(),
        ]);
    }

    public function test_punch_requires_employee_link(): void
    {
        // admin@dictro2.gov.ph has no linked employee 201-file — punch must 403.
        $cp = $this->checkpoint();

        $this->actingAs($this->adminUser())
            ->postJson('/attendance/punch', [
                'latitude' => (float) $cp->latitude,
                'longitude' => (float) $cp->longitude,
            ])
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  Corrections                                                        */
    /* ------------------------------------------------------------------ */

    public function test_employee_can_request_correction_and_hr_approves(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $response = $this->actingAs($user)->postJson('/attendance/corrections', [
            'log_date' => now()->subDay()->toDateString(),
            'punch_type' => 'am_in',
            'requested_time' => '07:45',
            'reason' => 'Forgot to punch in on arrival.',
        ]);

        $response->assertOk();

        $correction = AttendanceCorrection::where('employee_id', $employee->id)
            ->where('punch_type', 'am_in')
            ->latest()
            ->first();

        $this->assertNotNull($correction);
        $this->assertSame('pending', $correction->status);
        $this->createdCorrectionIds[] = $correction->id;

        // HR approves → a corrected log entry is created.
        $this->actingAs($this->adminUser())
            ->post("/attendance/corrections/{$correction->id}/approve")
            ->assertRedirect();

        $correction->refresh();
        $this->assertSame('approved', $correction->status);

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->where('log_date', $correction->log_date->toDateString())
            ->where('punch_type', 'am_in')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('hr_correction', $log->source);
        $this->createdLogIds[] = $log->id;
    }

    public function test_duplicate_pending_correction_is_rejected(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $payload = [
            'log_date' => now()->subDay()->toDateString(),
            'punch_type' => 'am_in',
            'requested_time' => '07:45',
            'reason' => 'First request',
        ];

        $this->actingAs($user)->postJson('/attendance/corrections', $payload)->assertOk();

        $first = AttendanceCorrection::where('employee_id', $employee->id)
            ->where('punch_type', 'am_in')
            ->latest()
            ->first();
        $this->createdCorrectionIds[] = $first->id;

        // Same date + punch while the first is still pending → rejected.
        $this->actingAs($user)->postJson('/attendance/corrections', $payload)->assertStatus(422);

        $this->assertSame(1, AttendanceCorrection::where('employee_id', $employee->id)
            ->where('log_date', $payload['log_date'])
            ->where('punch_type', 'am_in')
            ->where('status', 'pending')
            ->count());
    }

    public function test_hr_can_reject_correction_with_reason(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $correction = AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'log_date' => now()->subDay()->toDateString(),
            'punch_type' => 'pm_out',
            'requested_time' => '17:30',
            'reason' => 'Left early, forgot.',
            'status' => 'pending',
        ]);
        $this->createdCorrectionIds[] = $correction->id;

        $this->actingAs($this->adminUser())
            ->post("/attendance/corrections/{$correction->id}/reject", [
                'denial_reason' => 'No supporting document.',
            ])
            ->assertRedirect();

        $correction->refresh();
        $this->assertSame('rejected', $correction->status);
        $this->assertSame('No supporting document.', $correction->denial_reason);
    }

    public function test_employee_cannot_review_corrections(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $correction = AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'log_date' => now()->subDay()->toDateString(),
            'punch_type' => 'am_out',
            'requested_time' => '12:00',
            'reason' => 'Test',
            'status' => 'pending',
        ]);
        $this->createdCorrectionIds[] = $correction->id;

        $this->actingAs($user)->get('/attendance/corrections')->assertForbidden();
        $this->actingAs($user)->post("/attendance/corrections/{$correction->id}/approve")->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /*  DTR                                                                */
    /* ------------------------------------------------------------------ */

    public function test_employee_can_view_own_attendance_and_dtr(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('Time Log')
            ->assertSee('Daily Time Record');

        $this->actingAs($user)
            ->get('/attendance/dtr')
            ->assertOk()
            ->assertSee('DAILY TIME RECORD');
    }

    public function test_hr_can_issue_dtr_pdf_with_reference(): void
    {
        $employee = Employee::firstOrFail();

        $response = $this->actingAs($this->adminUser())
            ->get("/attendance/employees/{$employee->id}/dtr/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?: '');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertDatabaseHas('documents', [
            'employee_id' => $employee->id,
            'document_type' => 'dtr',
            'generated_by' => $this->adminUser()->id,
        ]);
    }

    public function test_hr_manages_checkpoints(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/attendance/checkpoints')
            ->assertOk()
            ->assertSee('Checkpoint');

        $response = $this->actingAs($this->adminUser())
            ->post('/attendance/checkpoints', [
                'name' => 'Test Checkpoint',
                'latitude' => 17.6,
                'longitude' => 121.7,
                'radius_meters' => 150,
                'is_active' => 1,
            ]);

        $response->assertRedirect();

        $cp = AttendanceCheckpoint::where('name', 'Test Checkpoint')->firstOrFail();
        $this->assertSame(150, $cp->radius_meters);

        $cp->delete();
    }

    public function test_office_hours_setting_updates(): void
    {
        $this->actingAs($this->adminUser())
            ->put('/attendance/settings', [
                'am_start' => '07:30',
                'am_end' => '12:00',
                'pm_start' => '13:00',
                'pm_end' => '16:30',
            ])
            ->assertRedirect();

        $this->assertSame('07:30', Setting::officeHours()['am_start']);

        // Restore defaults.
        Setting::set('office_hours', [
            'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00',
        ]);
    }
}
