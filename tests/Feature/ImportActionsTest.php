<?php

namespace Tests\Feature;

use App\Actions\ImportAttendance;
use App\Actions\ImportEmployees;
use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class ImportActionsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Keep re-runs idempotent: hard-delete test rows (the Employee model
        // soft-deletes, so delete() would leave the unique number occupied).
        Employee::where('employee_number', 'like', 'RO2-9%')->where('remarks', 'IMPORT TEST')->forceDelete();
        parent::tearDown();
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    public function test_employee_preview_flags_duplicate_numbers_within_file(): void
    {
        $rows = [
            ['employee_number' => 'RO2-9901', 'first_name' => 'A', 'last_name' => 'One', 'employment_type' => 'Permanent', 'remarks' => 'IMPORT TEST'],
            ['employee_number' => 'RO2-9901', 'first_name' => 'B', 'last_name' => 'Two', 'employment_type' => 'Permanent', 'remarks' => 'IMPORT TEST'],
        ];

        $result = (new ImportEmployees)->preview($rows);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['validCount']);
        $this->assertSame(1, $result->data['errorCount']);
        $this->assertStringContainsString('Duplicate employee number RO2-9901', $result->data['rows'][1]['errors'][0]);
    }

    public function test_employee_preview_resolves_employment_type_aliases(): void
    {
        $rows = [
            ['employee_number' => 'RO2-9902', 'first_name' => 'C', 'last_name' => 'Three', 'employment_type' => 'COS', 'remarks' => 'IMPORT TEST'],
            ['employee_number' => 'RO2-9903', 'first_name' => 'D', 'last_name' => 'Four', 'employment_type' => 'NOT_A_REAL_TYPE', 'remarks' => 'IMPORT TEST'],
        ];

        $result = (new ImportEmployees)->preview($rows);

        $this->assertSame(1, $result->data['validCount']);
        $this->assertSame(1, $result->data['errorCount']);
        $this->assertStringContainsString('Employment type not found', $result->data['rows'][1]['errors'][0]);

        // Commit only the valid row; the COS alias must resolve.
        $commit = (new ImportEmployees)->commit($rows, 'upsert');
        $this->assertTrue($commit->success);
        $this->assertSame(1, $commit->data['created']);
        $this->assertSame(1, $commit->data['skipped']);

        $employee = Employee::where('employee_number', 'RO2-9902')->firstOrFail();
        $this->assertSame('CONTRACT_OF_SERVICE', $employee->employmentType?->code);
    }

    public function test_employee_commit_mode_semantics(): void
    {
        $rows = [
            ['employee_number' => 'RO2-9904', 'first_name' => 'E', 'last_name' => 'Five', 'employment_type' => 'Permanent', 'remarks' => 'IMPORT TEST'],
        ];

        // create mode on a fresh number → created
        $created = (new ImportEmployees)->commit($rows, 'create');
        $this->assertSame(1, $created->data['created']);

        // update mode on the same number → updated (not duplicated)
        $updated = (new ImportEmployees)->commit($rows, 'update');
        $this->assertSame(1, $updated->data['updated']);
        $this->assertSame(1, Employee::where('employee_number', 'RO2-9904')->count());
    }

    public function test_attendance_preview_rejects_unknown_employee_and_bad_date(): void
    {
        $rows = [
            ['employee_number' => 'RO2-DOES-NOT-EXIST', 'log_date' => '2026-08-03', 'am_in' => '07:02'],
            ['employee_number' => 'RO2-DOES-NOT-EXIST', 'log_date' => 'not-a-date', 'am_in' => '07:02'],
        ];

        $result = (new ImportAttendance)->preview($rows);

        $this->assertSame(0, $result->data['validCount']);
        $this->assertSame(2, $result->data['errorCount']);
        $this->assertStringContainsString('Employee number not found', $result->data['rows'][0]['errors'][0]);
        // The second row fails both checks — all errors are collected per row.
        $this->assertStringContainsString('Employee number not found', $result->data['rows'][1]['errors'][0]);
        $this->assertStringContainsString('Invalid log date', implode(' ', $result->data['rows'][1]['errors']));
    }

    public function test_attendance_commit_creates_punches_for_known_employee(): void
    {
        $employee = Employee::firstOrFail();
        $rows = [
            ['employee_number' => $employee->employee_number, 'log_date' => '2026-08-03', 'am_in' => '07:02', 'pm_out' => '18:05'],
        ];

        $result = (new ImportAttendance)->commit($rows);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['imported']);

        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'log_date' => '2026-08-03',
            'punch_type' => 'am_in',
            'source' => 'hr_manual',
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'log_date' => '2026-08-03',
            'punch_type' => 'pm_out',
        ]);
    }
}
