<?php

namespace App\Support;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Builds the data grid for the CSC Form 48 Daily Time Record.
 *
 * One row per day of the month with AM in/out, PM in/out, total hours
 * rendered, and late/undertime computed against the configured office hours.
 */
class Dtr
{
    public static function build(Employee $employee, int $month, int $year): array
    {
        $hours = Setting::officeHours();

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($log) => $log->log_date->format('j') . ':' . $log->punch_type);

        $days = [];
        $totals = ['hours' => 0, 'late' => 0, 'undertime' => 0, 'present' => 0];

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->day($day);

            // Schedule times are built from THIS row's date (not today) so
            // DTRs for past months compute late/undertime correctly.
            $amStart = $date->copy()->setTimeFromTimeString($hours['am_start']);
            $amEnd = $date->copy()->setTimeFromTimeString($hours['am_end']);
            $pmStart = $date->copy()->setTimeFromTimeString($hours['pm_start']);
            $pmEnd = $date->copy()->setTimeFromTimeString($hours['pm_end']);

            $get = fn (string $type) => $logs->get($day . ':' . $type);

            $amIn = $get('am_in');
            $amOut = $get('am_out');
            $pmIn = $get('pm_in');
            $pmOut = $get('pm_out');

            $row = [
                'day' => $day,
                'date' => $date,
                'is_weekend' => $date->isSaturday() || $date->isSunday(),
                'am_in' => $amIn?->punched_at,
                'am_out' => $amOut?->punched_at,
                'pm_in' => $pmIn?->punched_at,
                'pm_out' => $pmOut?->punched_at,
                'hours' => 0.0,
                'late' => 0,
                'undertime' => 0,
                'present' => false,
            ];

            // Minutes late vs scheduled starts (only when actually punched in).
            if ($amIn && $amIn->punched_at->gt($amStart)) {
                $row['late'] += $amIn->punched_at->diffInMinutes($amStart);
            }
            if ($pmIn && $pmIn->punched_at->gt($pmStart)) {
                $row['late'] += $pmIn->punched_at->diffInMinutes($pmStart);
            }

            // Minutes under the scheduled ends.
            if ($amOut && $amOut->punched_at->lt($amEnd)) {
                $row['undertime'] += $amEnd->diffInMinutes($amOut->punched_at);
            }
            if ($pmOut && $pmOut->punched_at->lt($pmEnd)) {
                $row['undertime'] += $pmEnd->diffInMinutes($pmOut->punched_at);
            }

            // Total hours rendered: sum of the two spans actually covered.
            if ($amIn && $amOut) {
                $row['hours'] += round($amIn->punched_at->diffInMinutes($amOut->punched_at) / 60, 2);
            }
            if ($pmIn && $pmOut) {
                $row['hours'] += round($pmIn->punched_at->diffInMinutes($pmOut->punched_at) / 60, 2);
            }

            $row['present'] = (bool) ($amIn || $pmIn);

            $totals['hours'] += $row['hours'];
            $totals['late'] += $row['late'];
            $totals['undertime'] += $row['undertime'];
            if ($row['present']) {
                $totals['present']++;
            }

            $days[] = $row;
        }

        return [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'monthLabel' => $start->format('F Y'),
            'days' => $days,
            'totals' => [
                'hours' => round($totals['hours'], 2),
                'late' => $totals['late'],
                'undertime' => $totals['undertime'],
                'present' => $totals['present'],
            ],
            'officeHours' => $hours,
        ];
    }
}
