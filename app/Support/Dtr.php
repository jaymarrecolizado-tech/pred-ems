<?php

namespace App\Support;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Builds the data grid for the CSC Form 48 Daily Time Record.
 *
 * One row per day of the month with AM in/out, PM in/out, total hours
 * rendered, and late/undertime computed against the schedule in effect for
 * that day (AOM 2026-020). Rest days and holidays never accrue late or
 * undertime, but punches rendered on them still count as hours — that
 * timelog is the evidence for CTO credit claims.
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

        // Holidays are few — load once and reuse for every day of the month
        // instead of re-querying per row.
        $holidays = Holiday::query()->get();

        $days = [];
        $totals = ['hours' => 0, 'late' => 0, 'undertime' => 0, 'present' => 0, 'holidays' => 0];

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->day($day);

            // Resolve the schedule for THIS row's date (effective dating).
            $daySchedule = Schedule::day($date, $holidays);
            $isWorkingDay = $daySchedule['work'];
            $isHoliday = $daySchedule['holiday'] !== null;

            $amStart = $date->copy()->setTimeFromTimeString($daySchedule['am_start']);
            $amEnd = $date->copy()->setTimeFromTimeString($daySchedule['am_end']);
            $pmStart = $date->copy()->setTimeFromTimeString($daySchedule['pm_start']);
            $pmEnd = $date->copy()->setTimeFromTimeString($daySchedule['pm_end']);

            $get = fn (string $type) => $logs->get($day . ':' . $type);

            $amIn = $get('am_in');
            $amOut = $get('am_out');
            $pmIn = $get('pm_in');
            $pmOut = $get('pm_out');

            $row = [
                'day' => $day,
                'date' => $date,
                'is_weekend' => $date->isSaturday() || $date->isSunday(),
                'is_rest_day' => ! $isWorkingDay,
                'is_holiday' => $isHoliday,
                'holiday_name' => $daySchedule['holiday_name'],
                'holiday_type' => $daySchedule['holiday_type'],
                'reverted_week' => $daySchedule['reverted'],
                'schedule_name' => $daySchedule['schedule_name'],
                'am_in' => $amIn?->punched_at,
                'am_out' => $amOut?->punched_at,
                'pm_in' => $pmIn?->punched_at,
                'pm_out' => $pmOut?->punched_at,
                'hours' => 0.0,
                'late' => 0,
                'undertime' => 0,
                'present' => false,
            ];

            // Late/undertime are only assessed on scheduled working days that
            // are not holidays (CSC rule: holiday on a working day is deemed
            // complied; rest days have no required hours to be late against).
            if ($isWorkingDay && ! $isHoliday) {
                if ($amIn && $amIn->punched_at->gt($amStart)) {
                    $row['late'] += $amIn->punched_at->diffInMinutes($amStart);
                }
                if ($pmIn && $pmIn->punched_at->gt($pmStart)) {
                    $row['late'] += $pmIn->punched_at->diffInMinutes($pmStart);
                }

                if ($amOut && $amOut->punched_at->lt($amEnd)) {
                    $row['undertime'] += $amEnd->diffInMinutes($amOut->punched_at);
                }
                if ($pmOut && $pmOut->punched_at->lt($pmEnd)) {
                    $row['undertime'] += $pmEnd->diffInMinutes($pmOut->punched_at);
                }
            }

            // Hours rendered always count — including rest days, weekends and
            // holidays, where they represent overtime eligible for CTO credit.
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
            if ($isHoliday) {
                $totals['holidays']++;
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
                'holidays' => $totals['holidays'],
            ],
            'officeHours' => $hours,
            // Representative schedule for the month: the one in effect on the
            // 15th (mid-month) so effective-dated switches at month edges don't
            // mislabel the whole record.
            'scheduleLabel' => Schedule::day($start->copy()->setDay(min(15, $start->daysInMonth)), $holidays)['schedule_name'],
        ];
    }
}
