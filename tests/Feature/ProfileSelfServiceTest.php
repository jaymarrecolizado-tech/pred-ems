<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSelfServiceTest extends TestCase
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
        Storage::fake('public');
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    public function test_employee_can_view_own_profile_page(): void
    {
        $this->actingAs($this->employeeUser())->get('/profile')->assertOk();
        $this->actingAs($this->employeeUser())->get('/profile/edit')->assertOk();
        $this->actingAs($this->employeeUser())->get('/profile/password')->assertOk();
    }

    public function test_employee_can_update_own_personal_info(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $originalFirst = $employee->first_name;
        $originalContact = $employee->contact_number;
        $originalGender = $employee->gender;

        try {
            $this->actingAs($user)->put('/profile', [
                'first_name' => 'Juanito',
                'middle_name' => $employee->middle_name,
                'last_name' => $employee->last_name,
                'contact_number' => '09171234567',
                'gender' => 'Male',
            ])->assertRedirect('/profile');

            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name' => 'Juanito',
                'contact_number' => '09171234567',
            ]);

            $this->assertDatabaseHas('audit_logs', [
                'model_type' => Employee::class,
                'model_id' => $employee->id,
                'action' => 'profile_updated',
            ]);
        } finally {
            // Restore the original values so re-runs against the shared DB stay green.
            $employee->forceFill([
                'first_name' => $originalFirst,
                'contact_number' => $originalContact,
                'gender' => $originalGender,
            ])->save();
        }
    }

    public function test_employee_cannot_change_employment_data_via_profile(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $originalSalary = $employee->monthly_salary;
        $originalStatus = $employee->status;
        $originalGrade = $employee->salary_grade;

        $this->actingAs($user)->put('/profile', [
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'monthly_salary' => 999999,
            'salary_grade' => 33,
            'status' => 'separated',
        ])->assertRedirect('/profile');

        $employee->refresh();

        $this->assertEquals($originalSalary, $employee->monthly_salary);
        $this->assertEquals($originalGrade, $employee->salary_grade);
        $this->assertEquals($originalStatus, $employee->status);
    }

    public function test_employee_can_upload_and_remove_photo(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertRedirect();

        $employee->refresh();
        $this->assertNotNull($employee->profile_photo_path);
        Storage::disk('public')->assertExists($employee->profile_photo_path);

        $this->actingAs($user)->delete('/profile/photo')->assertRedirect();

        $employee->refresh();
        $this->assertNull($employee->profile_photo_path);
    }

    public function test_photo_upload_rejects_non_image(): void
    {
        $this->actingAs($this->employeeUser())
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->create('doc.txt', 10)])
            ->assertSessionHasErrors('photo');
    }

    public function test_employee_can_change_own_password(): void
    {
        $user = $this->employeeUser();

        try {
            $this->actingAs($user)->put('/profile/password', [
                'current_password' => '!Password123',
                'password' => 'NewPass!234',
                'password_confirmation' => 'NewPass!234',
            ])->assertRedirect('/profile/password');

            $this->assertTrue(Hash::check('NewPass!234', $user->fresh()->password));

            $this->assertDatabaseHas('audit_logs', [
                'model_type' => User::class,
                'model_id' => $user->id,
                'action' => 'password_changed',
            ]);
        } finally {
            // Restore the demo password so later test runs can still log in.
            $user->forceFill(['password' => Hash::make('!Password123')])->save();
        }
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $this->actingAs($this->employeeUser())->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPass!234',
            'password_confirmation' => 'NewPass!234',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_audit_trail_restricted_to_admin_and_hr(): void
    {
        $this->actingAs($this->employeeUser())->get('/audit-logs')->assertForbidden();

        $admin = User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
        $this->actingAs($admin)->get('/audit-logs')->assertOk();
    }

    public function test_employee_crud_creates_audit_entries(): void
    {
        $admin = User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
        $type = \App\Models\EmploymentType::firstOrFail();
        $employeeNumber = 'RO2-' . substr((string) time(), -5);

        try {
            $this->actingAs($admin)->post('/employees', [
                'employee_number' => $employeeNumber,
                'first_name' => 'Test',
                'last_name' => 'Audit',
                'employment_type_id' => $type->id,
                'status' => 'active',
            ])->assertRedirect();

            $employee = Employee::where('employee_number', $employeeNumber)->firstOrFail();

            $this->assertDatabaseHas('audit_logs', [
                'model_type' => Employee::class,
                'model_id' => $employee->id,
                'action' => 'created',
            ]);

            $this->actingAs($admin)->put("/employees/{$employee->id}", [
                'first_name' => 'Test',
                'middle_name' => null,
                'last_name' => 'Audited',
                'employment_type_id' => $type->id,
                'status' => 'active',
                'employee_number' => $employeeNumber,
            ])->assertRedirect();

            $this->assertDatabaseHas('audit_logs', [
                'model_type' => Employee::class,
                'model_id' => $employee->id,
                'action' => 'updated',
            ]);
        } finally {
            Employee::where('employee_number', $employeeNumber)->delete();
        }
    }

    public function test_payroll_role_cannot_view_audit_trail(): void
    {
        $payroll = User::where('email', 'payroll@dictro2.gov.ph')->firstOrFail();
        $this->actingAs($payroll)->get('/audit-logs')->assertForbidden();
    }
}
