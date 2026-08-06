<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class EmployeePagesSmokeTest extends TestCase
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

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_with_demo_credentials(): void
    {
        $this->post('/login', [
            'email' => 'admin@dictro2.gov.ph',
            'password' => '!Password123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_dashboard_and_employee_pages_render_for_admin(): void
    {
        $admin = User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/employees')->assertOk();

        $employee = Employee::firstOrFail();
        $this->actingAs($admin)->get("/employees/{$employee->id}")->assertOk();
        $this->actingAs($admin)->get("/employees/{$employee->id}/edit")->assertOk();
        $this->actingAs($admin)->get('/employees/create')->assertOk();
    }

    public function test_employee_role_cannot_view_directory(): void
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        $this->actingAs($employee->user)->get('/employees')->assertForbidden();
    }

    public function test_employee_can_view_own_profile_only(): void
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();
        $other = Employee::where('id', '!=', $employee->id)->whereNotNull('user_id')->firstOrFail();

        $this->actingAs($employee->user)->get("/employees/{$employee->id}")->assertOk();
        $this->actingAs($employee->user)->get("/employees/{$other->id}")->assertForbidden();
    }

    public function test_payroll_can_view_but_not_manage_employees(): void
    {
        $payroll = User::where('email', 'payroll@dictro2.gov.ph')->firstOrFail();

        $this->actingAs($payroll)->get('/employees')->assertOk();
        $this->actingAs($payroll)->get('/employees/create')->assertForbidden();
        $this->actingAs($payroll)->get('/employees/1/edit')->assertForbidden();
    }

    public function test_hr_can_manage_employees(): void
    {
        $hr = User::where('email', 'hr@dictro2.gov.ph')->firstOrFail();

        $this->actingAs($hr)->get('/employees/create')->assertOk();
        $this->actingAs($hr)->get('/employees/1/edit')->assertOk();
    }
}
