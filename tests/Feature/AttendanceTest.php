<?php

namespace Tests\Feature;

use App\Models\AttendanceCheckpoint;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Dtr;
use App\Support\Schedule;
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

        $holidayIds = $this->createdHolidayIds ?? [];
        if ($holidayIds) {
            Holiday::whereIn('id', $holidayIds)->delete();
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
    private array $createdHolidayIds = [];

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

    /* ------------------------------------------------------------------ */
    /*  Work schedules + holidays (AOM 2026-020)                           */
    /* ------------------------------------------------------------------ */

    public function test_admin_manages_work_schedule(): void
    {
        // Store a new schedule.
        $this->actingAs($this->adminUser())
            ->post('/attendance/schedules', [
                'name' => 'Test CWW',
                'description' => 'Mon–Thu 7–6',
                'starts_on' => '2026-08-03',
                'is_active' => 1,
                'days' => [
                    '1' => ['work' => 1, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '2' => ['work' => 1, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '3' => ['work' => 1, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '4' => ['work' => 1, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '5' => [],
                    '6' => [],
                    '7' => [],
                ],
            ])
            ->assertRedirect();

        $schedule = WorkSchedule::where('name', 'Test CWW')->firstOrFail();
        $this->assertSame('07:00', $schedule->days['1']['am_start']);
        $this->assertArrayNotHasKey('am_start', $schedule->days['5']);

        // Update it.
        $this->actingAs($this->adminUser())
            ->put("/attendance/schedules/{$schedule->id}", [
                'name' => 'Test CWW v2',
                'is_active' => 1,
                'days' => $schedule->days,
            ])
            ->assertRedirect();

        $this->assertSame('Test CWW v2', $schedule->refresh()->name);

        // Reject schedules with no working days.
        $this->actingAs($this->adminUser())
            ->post('/attendance/schedules', [
                'name' => 'All Rest',
                'days' => ['1' => [], '2' => [], '3' => [], '4' => [], '5' => [], '6' => [], '7' => []],
            ])
            ->assertSessionHasErrors('days');

        $schedule->delete();
    }

    public function test_schedule_resolution_follows_effective_dates(): void
    {
        // A schedule effective only before the CWW started.
        $legacy = WorkSchedule::create([
            'name' => 'Pre-CWW Temp',
            'days' => [
                '1' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                '2' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                '3' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                '4' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                '5' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                '6' => ['work' => false],
                '7' => ['work' => false],
            ],
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-08-02',
            'is_active' => true,
        ]);

        // Aug 3 onward → the CWW (Mon–Thu) applies.
        $mon = \Illuminate\Support\Carbon::parse('2026-08-03'); // Monday
        $fri = \Illuminate\Support\Carbon::parse('2026-08-07');

        $this->assertTrue(Schedule::day($mon)['work']);
        $this->assertSame('07:00', Schedule::day($mon)['am_start']);
        $this->assertFalse(Schedule::day($fri)['work']); // Friday = rest day
        $this->assertTrue(Schedule::day($fri)['rest_day']);

        $legacy->delete();
    }

    public function test_holiday_on_rest_day_reverts_week_to_standard(): void
    {
        // Friday 2026-08-14: a holiday on the CWW rest day → whole week reverts.
        $holiday = Holiday::create([
            'name' => 'Test Holiday (Fri)',
            'date' => '2026-08-14',
            'type' => 'regular_holiday',
        ]);

        $monday = \Illuminate\Support\Carbon::parse('2026-08-10');
        $friday = \Illuminate\Support\Carbon::parse('2026-08-14');

        // The whole week reverts to standard: Friday is now a working day…
        $this->assertTrue(Schedule::day($friday)['work']);
        $this->assertSame('08:00', Schedule::day($friday)['am_start']);
        $this->assertTrue(Schedule::day($friday)['reverted']);
        // …and Monday is still a working day (standard 8AM start, not 7AM).
        $this->assertSame('08:00', Schedule::day($monday)['am_start']);
        $this->assertSame('Test Holiday (Fri)', Schedule::day($friday)['holiday_name']);

        $this->createdHolidayIds[] = $holiday->id;
    }

    public function test_weekend_holiday_does_not_revert_the_week(): void
    {
        // Saturday 2026-08-15 is already a weekend under every schedule — it is
        // not the CWW's designated rest day, so the week must NOT revert.
        $holiday = Holiday::create([
            'name' => 'Test Holiday (Sat)',
            'date' => '2026-08-15',
            'type' => 'regular_holiday',
        ]);
        $this->createdHolidayIds[] = $holiday->id;

        $monday = \Illuminate\Support\Carbon::parse('2026-08-10');
        $tuesday = \Illuminate\Support\Carbon::parse('2026-08-11');

        // Monday stays on the CWW (7AM start, no revert), Tuesday not reverted.
        $this->assertSame('07:00', Schedule::day($monday)['am_start']);
        $this->assertFalse(Schedule::day($tuesday)['reverted']);
        $this->assertSame('Test Holiday (Sat)', Schedule::day($holiday->date)['holiday_name']);
    }

    public function test_holiday_on_working_day_is_flagged_no_revert(): void
    {
        // Tuesday 2026-08-11 is a CWW working day → flagged, not reverted.
        $holiday = Holiday::create([
            'name' => 'Test Holiday (Tue)',
            'date' => '2026-08-11',
            'type' => 'special_nonworking',
        ]);

        $tuesday = \Illuminate\Support\Carbon::parse('2026-08-11');
        $this->assertTrue(Schedule::day($tuesday)['work']);
        $this->assertSame('07:00', Schedule::day($tuesday)['am_start']);
        $this->assertSame('Test Holiday (Tue)', Schedule::day($tuesday)['holiday_name']);
        $this->assertFalse(Schedule::day($tuesday)['reverted']);

        $this->createdHolidayIds[] = $holiday->id;
    }

    public function test_admin_manages_holidays(): void
    {
        $this->actingAs($this->adminUser())
            ->post('/attendance/holidays', [
                'name' => 'Test Holiday',
                'date' => '2026-12-24',
                'type' => 'special_nonworking',
                'is_repeating' => 1,
            ])
            ->assertRedirect();

        $holiday = Holiday::where('name', 'Test Holiday')->firstOrFail();
        $this->assertTrue($holiday->is_repeating);
        $this->assertSame('special_nonworking', $holiday->type);

        // A repeating Dec 24 matches any year.
        $this->assertTrue($holiday->occursOn(\Illuminate\Support\Carbon::parse('2030-12-24')));

        $holiday->delete();
    }

    public function test_dtr_flags_holidays_and_rest_day_overtime(): void
    {
        $employee = $this->employeeUser()->employee;

        // Rest-day punch (Friday 2026-08-14 would revert; use a normal rest day,
        // e.g. Saturday) plus a normal working-day punch.
        $sat = '2026-08-08'; // Saturday

        foreach ([['am_in', '08:00:00'], ['am_out', '12:00:00'], ['pm_in', '13:00:00'], ['pm_out', '17:00:00']] as [$type, $time]) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'log_date' => $sat,
                'punch_type' => $type,
                'punched_at' => $sat . ' ' . $time,
                'source' => 'geofence',
            ]);
            $this->createdLogIds[] = AttendanceLog::where('employee_id', $employee->id)
                ->where('log_date', $sat)->where('punch_type', $type)->first()->id;
        }

        $dtr = Dtr::build($employee, 8, 2026);

        $satRow = collect($dtr['days'])->first(fn ($r) => $r['date']->toDateString() === $sat);

        $this->assertTrue($satRow['is_rest_day']);
        $this->assertSame(0, $satRow['late']); // no late on rest days
        $this->assertGreaterThan(0, $satRow['hours']); // OT hours still count

        $holiday = Holiday::create(['name' => 'Test Holiday Aug', 'date' => '2026-08-19', 'type' => 'regular_holiday']);
        $this->createdHolidayIds[] = $holiday->id;

        $dtr2 = Dtr::build($employee, 8, 2026);
        $holRow = collect($dtr2['days'])->first(fn ($r) => $r['date']->toDateString() === '2026-08-19');
        $this->assertTrue($holRow['is_holiday']);
        $this->assertSame('Test Holiday Aug', $holRow['holiday_name']);
    }
}
