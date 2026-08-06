<?php

/**
 * One-time data cleanup for an already-seeded database.
 *
 * Mirrors data/cleanup_directory.py (which fixes the JSON source of truth)
 * for the live database:
 *
 *   1. Delete the stale duplicate employee rows (and their related records)
 *      that were merged/dropped in the JSON.
 *   2. Rename the 3 user accounts that were created with malformed emails.
 *   3. Re-run RealDirectorySeeder to refresh the kept records from the fixed
 *      JSON (position details absorbed from dropped copies, fixed emails,
 *      convention emails for the 5 previously email-less staff).
 *   4. Re-run EmployeeUserSeeder to provision the 5 new accounts.
 *
 * Run from the app root:  php artisan tinker scripts/apply_data_cleanup.php
 */

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

$say = fn (string $m) => fwrite(STDOUT, $m . PHP_EOL);

// Employee numbers dropped during the JSON cleanup (stale active copies +
// the double-active Catinoy copy). Their kept counterparts are refreshed by
// RealDirectorySeeder, so we only remove the rows below.
$dropNumbers = [
    'RO2-0215', // duplicate active copy    -> kept separated record RO2-0192
    'RO2-0198', // duplicate active copy    -> kept separated record RO2-0193
    'RO2-0202', // duplicate active copy    -> kept separated record RO2-0194
    'RO2-0205', // duplicate active copy    -> kept separated record RO2-0206
    'RO2-0208', // duplicate active copy    -> kept separated record RO2-0209
    'RO2-0211', // duplicate active copy    -> kept separated record RO2-0213
    'RO2-0212', // second duplicate active copy -> kept separated record RO2-0213
    'RO2-0217', // duplicate active copy    -> kept separated record RO2-0218
    'RO2-0219', // duplicate active copy    -> kept separated record RO2-0220
    'RO2-0200', // duplicate job-order copy -> kept RO2-0149
];

// User accounts created earlier with malformed emails.
$userEmailFixes = [
    'jayson.guisando.dict.gov.ph' => 'jayson.guisando@dict.gov.ph',
    'oseph.rosal@dict.gov.ph' => 'joseph.rosal@dict.gov.ph',
    'christopher.capili@dictgov.ph' => 'christopher.capili@dict.gov.ph',
];

$say('=== 1. Removing stale duplicate employees ===');
foreach ($dropNumbers as $number) {
    $employee = Employee::where('employee_number', $number)->first();
    if (! $employee) {
        $say("  [skip] {$number} not found (already clean)");
        continue;
    }

    // Remove related records first so FK constraints never block the delete.
    foreach (['appointments', 'educations', 'civilServiceEligibilities', 'leaveCredits', 'allowances'] as $relation) {
        $employee->{$relation}()->delete();
    }
    if ($employee->user_id) {
        $employee->user()->delete();
    }

    $name = $employee->full_name;
    $employee->delete();
    $say("  [deleted] {$number} {$name}");
}

$say('');
$say('=== 2. Renaming user accounts with malformed emails ===');
foreach ($userEmailFixes as $old => $new) {
    $updated = User::where('email', $old)->update(['email' => $new]);
    $say("  " . ($updated ? "[renamed] {$old} -> {$new}" : "[skip] no user with {$old}"));
}

$say('');
$say('=== 3. Refreshing employees from the cleaned JSON ===');
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RealDirectorySeeder', '--force' => true]);
$say('  ' . trim(Artisan::output()));

$say('');
$say('=== 4. Provisioning accounts for newly-emailed staff ===');
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\EmployeeUserSeeder', '--force' => true]);
$say('  ' . trim(Artisan::output()));

$say('');
$say('=== 5. Verification ===');
$total = Employee::count();
$active = Employee::where('status', '!=', 'separated')->count();
$separated = Employee::where('status', 'separated')->count();
$noEmail = Employee::where('status', '!=', 'separated')
    ->whereNull('gov_email')->whereNull('personal_email')->count();
$noAccount = Employee::where('status', '!=', 'separated')
    ->whereNull('user_id')
    ->where(function ($q) {
        $q->whereNotNull('gov_email')->orWhereNotNull('personal_email');
    })->count();
$userCount = User::count();
$oldEmailsRemaining = User::whereIn('email', array_keys($userEmailFixes))->count();

$say("  employees: {$total} total / {$active} active / {$separated} separated");
$say("  active without email: {$noEmail}");
$say("  active with email but no account: {$noAccount}");
$say("  users: {$userCount}");
$say("  stale malformed user emails remaining: {$oldEmailsRemaining}");
$say('');
$say('Done.');
