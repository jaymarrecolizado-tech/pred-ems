<?php

/**
 * One-off: seed sample attendance (July 1–15, 2026) for every active employee.
 *
 * Runs through App\Actions\ImportAttendance — the same action the import UI
 * uses — so rows are validated, recorded as source=hr_manual, and audited
 * exactly like a bulk import. Usage:
 *   php sample_attendance_import.php
 */

use App\Actions\ImportAttendance;
use App\Models\Employee;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$employees = Employee::where('status', 'active')
    ->orderBy('employee_number')
    ->pluck('employee_number');

$rows = [];
$start = new DateTime('2026-07-01');
$end = new DateTime('2026-07-15');
$period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

foreach ($period as $day) {
    // Weekdays only (1 = Mon … 7 = Sun).
    $dow = (int) $day->format('N');
    if ($dow >= 6) {
        continue;
    }

    foreach ($employees as $empNumber) {
        $rows[] = [
            'employee_number' => $empNumber,
            'log_date' => $day->format('Y-m-d'),
            'am_in' => '08:00',
            'am_out' => '12:00',
            'pm_in' => '13:00',
            'pm_out' => '17:00',
        ];
    }
}

echo 'Employees: '.$employees->count().PHP_EOL;
echo 'Working days (Jul 1–15): '.count(array_unique(array_column($rows, 'log_date'))).PHP_EOL;
echo 'Total day-records: '.count($rows).PHP_EOL;

$preview = (new ImportAttendance)->preview($rows);
echo 'Preview — valid: '.$preview->data['validCount'].', errors: '.$preview->data['errorCount'].PHP_EOL;

$result = (new ImportAttendance)->commit($rows);
echo 'Commit: '.$result->message.PHP_EOL;
echo 'Done.'.PHP_EOL;
