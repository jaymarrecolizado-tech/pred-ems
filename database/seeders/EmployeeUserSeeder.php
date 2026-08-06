<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions a login account for every ACTIVE employee that has an email on
 * file (gov email preferred, personal email as fallback).
 *
 * - Username  = the employee's email address
 * - Password  = !Password123  (force change in production before rollout)
 * - Role      = derived from position title (see $roleByPosition)
 *
 * Idempotent: accounts are created only when the email is not already in use.
 * Runs in every environment except `production`.
 */
class EmployeeUserSeeder extends Seeder
{
    /** Position title => role name. Everything else becomes `employee`. */
    private array $roleByPosition = [
        'Director IV' => 'admin',
        'Director III' => 'unit_head',
        'Chief Administrative Officer' => 'hr',
        'Human Resource Management Officer II' => 'hr',
        'Administrative Officer II (HRMO I)' => 'hr',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('EmployeeUserSeeder skipped in production — create accounts via the admin UI instead.');

            return;
        }

        $password = Hash::make('!Password123');
        $roles = Role::pluck('id', 'name');

        $created = $skippedNoEmail = $skippedExists = 0;

        Employee::query()
            ->where('status', '!=', 'separated')
            ->with('position')
            ->orderBy('id')
            ->get()
            ->each(function (Employee $employee) use ($password, $roles, &$created, &$skippedNoEmail, &$skippedExists) {
                $email = $employee->gov_email ?: $employee->personal_email;

                if (! $email) {
                    $skippedNoEmail++;
                    $this->command?->line("  skip (no email): {$employee->full_name}");

                    return;
                }

                if (User::where('email', $email)->exists()) {
                    $skippedExists++;
                    $this->command?->line("  skip (account exists): {$email}");

                    return;
                }

                $roleName = $employee->position ? ($this->roleByPosition[$employee->position->title] ?? 'employee') : 'employee';

                $user = User::create([
                    'name' => $employee->full_name,
                    'email' => $email,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $user->roles()->attach($roles[$roleName] ?? $roles['employee']);
                $employee->update(['user_id' => $user->id]);

                $created++;
            });

        $this->command?->info(
            "Employee accounts created: {$created} (skipped: {$skippedNoEmail} without email, {$skippedExists} already existing)."
        );
    }
}
