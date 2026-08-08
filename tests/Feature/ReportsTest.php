<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    public function test_hr_can_view_reports_hub(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports')
            ->assertOk()
            ->assertSee('Headcount Report')
            ->assertSee('Leave Balances')
            ->assertSee('Documents Issued')
            ->assertSee('Attrition');
    }

    public function test_headcount_report_renders_and_exports_csv(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports/headcount')
            ->assertOk()
            ->assertSee('Headcount by Employment Type');

        $response = $this->actingAs($this->adminUser())
            ->get('/reports/headcount?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?: '');
        $this->assertStringContainsString('Employment Type', $response->getContent());
    }

    public function test_leave_balances_and_utilization_render(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports/leave-balances')
            ->assertOk()
            ->assertSee('VL Balance');

        $this->actingAs($this->adminUser())
            ->get('/reports/leave-utilization')
            ->assertOk()
            ->assertSee('Leave Utilization');
    }

    public function test_documents_report_lists_issued_documents(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports/documents')
            ->assertOk()
            ->assertSee('Documents Issued');
    }

    public function test_attrition_report_renders(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/reports/attrition')
            ->assertOk()
            ->assertSee('Separated / Retired')
            ->assertSee('New Hires');
    }

    public function test_employees_cannot_access_reports(): void
    {
        $this->actingAs($this->employeeUser())
            ->get('/reports')
            ->assertForbidden();

        $this->actingAs($this->employeeUser())
            ->get('/reports/headcount')
            ->assertForbidden();
    }
}
