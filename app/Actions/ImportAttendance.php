<?php

namespace App\Actions;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Support\Audit;
use App\Support\ImportParser;

/**
 * Bulk attendance import (admin/HR).
 *
 * Punch rows (am_in/am_out/pm_in/pm_out) are upserted per employee + date,
 * stamped source=hr_manual (append-only, no overwrite of the geofence
 * evidence beyond the unique employee/date/punch key). `preview()` validates
 * every row for the screen; `commit()` re-validates and imports only the
 * valid rows, auditing imported/skipped counts.
 */
class ImportAttendance
{
    /**
     * Validate parsed rows; data carries rows/validCount/errorCount.
     */
    public function preview(array $rows): ActionResult
    {
        return ActionResult::ok('Preview ready.', $this->validateRows($rows));
    }

    /**
     * Re-validate the rows and import the valid ones, then audit the outcome.
     */
    public function commit(array $rows): ActionResult
    {
        $preview = $this->validateRows($rows);

        $imported = 0;
        $skipped = 0;

        foreach ($preview['rows'] as $row) {
            if (! empty($row['errors'])) {
                $skipped++;

                continue;
            }

            $this->importAttendanceRow($row['data']);
            $imported++;
        }

        Audit::record('attendance_imported', null, [], [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        return ActionResult::ok(
            "Attendance import complete: {$imported} day-records imported, {$skipped} skipped.",
            ['imported' => $imported, 'skipped' => $skipped]
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Row validation (preview) */
    /* ------------------------------------------------------------------ */

    private function validateRows(array $rows): array
    {
        $employees = Employee::pluck('id', 'employee_number');

        $normalized = collect($rows)->map(function ($row) use ($employees) {
            $errors = [];
            $data = $row;

            $empNumber = trim((string) ($data['employee_number'] ?? ''));
            $employeeId = $employees[$empNumber] ?? null;

            if ($empNumber === '' || $employeeId === null) {
                $errors[] = 'Employee number not found: "'.$empNumber.'".';
            }

            $logDate = ImportParser::parseDate((string) ($data['log_date'] ?? ''));
            if ($logDate === null) {
                $errors[] = 'Invalid log date "'.($data['log_date'] ?? '').'". Use YYYY-MM-DD.';
            }

            foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
                $value = (string) ($data[$punch] ?? '');
                if ($value === '') {
                    continue;
                }
                if (ImportParser::parseTime($value) === null) {
                    $errors[] = "Invalid {$punch} time \"{$value}\". Use HH:MM (24-hour) or HH:MM AM/PM.";
                }
            }

            if ($logDate && $employeeId && empty($errors)) {
                $data['employee_id'] = $employeeId;
                $data['log_date_parsed'] = $logDate;
                foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
                    $data[$punch.'_parsed'] = ImportParser::parseTime((string) ($data[$punch] ?? ''));
                }
            }

            return ['data' => $data, 'errors' => $errors];
        });

        return [
            'rows' => $normalized->values()->all(),
            'validCount' => $normalized->filter(fn ($r) => empty($r['errors']))->count(),
            'errorCount' => $normalized->filter(fn ($r) => ! empty($r['errors']))->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Row import (commit) */
    /* ------------------------------------------------------------------ */

    private function importAttendanceRow(array $data): void
    {
        foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
            $time = $data[$punch.'_parsed'] ?? null;
            if (! $time) {
                continue;
            }

            AttendanceLog::updateOrCreate(
                [
                    'employee_id' => $data['employee_id'],
                    'log_date' => $data['log_date_parsed'],
                    'punch_type' => $punch,
                ],
                [
                    'employee_id' => $data['employee_id'],
                    'log_date' => $data['log_date_parsed'],
                    'punch_type' => $punch,
                    'punched_at' => $data['log_date_parsed'].' '.$time,
                    'source' => 'hr_manual',
                    'remarks' => 'Bulk import',
                ]
            );
        }
    }
}
