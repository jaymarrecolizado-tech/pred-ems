<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up test-imported records so reruns stay idempotent. Must be a
        // hard delete — the Employee model is soft-deleting, so a regular
        // delete() would leave the unique employee_number occupied.
        Employee::where('employee_number', 'like', 'RO2-9%')->where('remarks', 'IMPORT TEST')->forceDelete();
        parent::tearDown();
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    private function rosterCsv(): string
    {
        return implode("\n", [
            'employee_number,first_name,last_name,employment_type,division,position,salary_grade,step,monthly_salary,date_original_appointment,status,remarks',
            'RO2-9901,Juan,Dela Cruz,Permanent,Regional Office 2,Administrative Assistant II,8,1,21096.00,2020-01-15,active,IMPORT TEST',
            'RO2-9902,Maria,Santos,COS,Cagayan Provincial Office,IT Specialist,12,2,30989.00,2021-03-01,active,IMPORT TEST',
            'RO2-9903,NoLastName,,Permanent,,,,,,,,active,IMPORT TEST',
            'RO2-9904,UnknownType,Person,NOT_A_REAL_TYPE,,,,,,,,active,IMPORT TEST',
        ]);
    }

    public function test_employee_csv_preview_flags_invalid_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('roster.csv', $this->rosterCsv());

        $response = $this->actingAs($this->adminUser())
            ->post('/imports/employees/preview', ['file' => $file, 'mode' => 'upsert']);

        $response->assertOk();
        $response->assertSee('2 valid');
        $response->assertSee('2 with errors');
        $response->assertSee('First and last name are required');
        $response->assertSee('Employment type not found');
    }

    public function test_employee_csv_commit_creates_only_valid_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('roster.csv', $this->rosterCsv());

        $preview = $this->actingAs($this->adminUser())
            ->post('/imports/employees/preview', ['file' => $file, 'mode' => 'upsert']);

        $preview->assertOk();
        preg_match('/name="token" value="([A-Za-z0-9]+)"/', $preview->getContent(), $match);
        $this->assertNotEmpty($match, 'Preview should embed an upload token.');

        $commit = $this->actingAs($this->adminUser())
            ->post('/imports/employees/commit', ['token' => $match[1], 'mode' => 'upsert']);

        $commit->assertRedirect();
        $commit->assertSessionHas('success');

        $this->assertDatabaseHas('employees', ['employee_number' => 'RO2-9901', 'remarks' => 'IMPORT TEST']);
        $this->assertDatabaseHas('employees', ['employee_number' => 'RO2-9902']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'RO2-9903']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'RO2-9904']);

        // The original appointment seeds the Service Record.
        $juan = Employee::where('employee_number', 'RO2-9901')->firstOrFail();
        $this->assertTrue($juan->appointments()->exists());
        $this->assertSame('PERMANENT', $juan->employmentType?->code);
        $this->assertSame('Administrative Assistant II', $juan->position?->title);
    }

    public function test_attendance_csv_import_creates_punches(): void
    {
        $employee = Employee::firstOrFail();
        $csv = implode("\n", [
            'employee_number,log_date,am_in,am_out,pm_in,pm_out',
            "{$employee->employee_number},2026-08-03,07:02,12:00,13:00,18:05",
        ]);

        $file = UploadedFile::fake()->createWithContent('att.csv', $csv);

        $preview = $this->actingAs($this->adminUser())
            ->post('/imports/attendance/preview', ['file' => $file]);

        $preview->assertOk();
        $preview->assertSee('1 valid');

        preg_match('/name="token" value="([A-Za-z0-9]+)"/', $preview->getContent(), $match);

        $this->actingAs($this->adminUser())
            ->post('/imports/attendance/commit', ['token' => $match[1]])
            ->assertRedirect()
            ->assertSessionHas('success');

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

    public function test_xlsx_import_supported(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['employee_number', 'first_name', 'last_name', 'employment_type', 'remarks'],
            ['RO2-9907', 'Xlsx', 'Imported', 'Permanent', 'IMPORT TEST'],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'import');
        (new Xlsx($spreadsheet))->save($tmp);

        $file = new UploadedFile($tmp, 'roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $preview = $this->actingAs($this->adminUser())
            ->post('/imports/employees/preview', ['file' => $file, 'mode' => 'upsert']);

        $preview->assertOk();
        $preview->assertSee('1 valid');

        preg_match('/name="token" value="([A-Za-z0-9]+)"/', $preview->getContent(), $match);

        $this->actingAs($this->adminUser())
            ->post('/imports/employees/commit', ['token' => $match[1], 'mode' => 'upsert'])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', ['employee_number' => 'RO2-9907']);

        @unlink($tmp);
    }

    public function test_templates_download(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/imports/employees/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->adminUser())
            ->get('/imports/attendance/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_employees_cannot_access_imports(): void
    {
        $this->actingAs($this->employeeUser())
            ->get('/imports')
            ->assertForbidden();
    }
}
