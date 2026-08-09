<?php

namespace App\Support;

use App\Models\AttendanceLog;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly attendance summary across all active employees (reports suite).
 *
 * One row per employee for a given month: days present, scheduled work days,
 * absences, total hours rendered, late/undertime minutes, and hours rendered
 * on rest days / weekends / holidays (the CTO-eligible overtime evidence).
 *
 * Efficient by construction: a single AttendanceLog query covers the whole
 * month and the per-day schedule plan (AOM 2026-020 + CSC 2600838 revert
 * rules) is resolved ONCE per day, then reused for every employee — the
 * number of Schedule::day() calls is 31, not 31 × employees.
 */
class AttendanceSummary
{
    public static function build(int $month, int $year, ?int $divisionId = null): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $employees = Employee::query()
            ->with('division')
            ->where('status', 'active')
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $holidays = Holiday::query()->get();

        // Resolve the schedule once per day of the month.
        $dayPlans = [];
        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->day($day);
            $dayPlans[$day] = Schedule::day($date, $holidays) + ['date' => $date];
        }

        // One query for the whole month, grouped employee → day:punch_type.
        // When a division filter is active, narrow to its employees' logs so
        // the query matches the prefiltered employee set.
        $logs = AttendanceLog::query()
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->when($divisionId, fn ($q) => $q->whereIn('employee_id', $employees->pluck('id')))
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $set) => $set->keyBy(fn ($log) => $log->log_date->format('j') . ':' . $log->punch_type));

        $rows = $employees->map(function (Employee $employee) use ($dayPlans, $logs) {
            $own = $logs->get($employee->id, collect());

            $present = 0;
            $workdays = 0;
            $hours = 0.0;
            $late = 0;
            $undertime = 0;
            $otHours = 0.0;

            foreach ($dayPlans as $day => $plan) {
                $isWorking = (bool) $plan['work'] && $plan['holiday'] === null;
                if ($isWorking) {
                    $workdays++;
                }

                $amIn = $own->get($day . ':am_in');
                $amOut = $own->get($day . ':am_out');
                $pmIn = $own->get($day . ':pm_in');
                $pmOut = $own->get($day . ':pm_out');

                $dayHours = 0.0;
                if ($amIn && $amOut) {
                    $dayHours += round($amIn->punched_at->diffInMinutes($amOut->punched_at) / 60, 2);
                }
                if ($pmIn && $pmOut) {
                    $dayHours += round($pmIn->punched_at->diffInMinutes($pmOut->punched_at) / 60, 2);
                }

                $hours += $dayHours;
                if ($amIn || $pmIn) {
                    $present++;
                }

                // Late/undertime only assessed on scheduled working days that
                // are not holidays (mirrors the DTR rules). Rest-day, weekend
                // and holiday hours are the CTO evidence instead.
                if ($isWorking) {
                    $date = $plan['date'];
                    $amStart = $date->copy()->setTimeFromTimeString($plan['am_start']);
                    $amEnd = $date->copy()->setTimeFromTimeString($plan['am_end']);
                    $pmStart = $date->copy()->setTimeFromTimeString($plan['pm_start']);
                    $pmEnd = $date->copy()->setTimeFromTimeString($plan['pm_end']);

                    if ($amIn && $amIn->punched_at->gt($amStart)) {
                        $late += $amIn->punched_at->diffInMinutes($amStart);
                    }
                    if ($pmIn && $pmIn->punched_at->gt($pmStart)) {
                        $late += $pmIn->punched_at->diffInMinutes($pmStart);
                    }
                    if ($amOut && $amOut->punched_at->lt($amEnd)) {
                        $undertime += $amEnd->diffInMinutes($amOut->punched_at);
                    }
                    if ($pmOut && $pmOut->punched_at->lt($pmEnd)) {
                        $undertime += $pmEnd->diffInMinutes($pmOut->punched_at);
                    }
                } else {
                    $otHours += $dayHours;
                }
            }

            return [
                'employee' => $employee,
                'present' => $present,
                'workdays' => $workdays,
                'absences' => max(0, $workdays - $present),
                'hours' => round($hours, 2),
                'late' => $late,
                'undertime' => $undertime,
                'ot_hours' => round($otHours, 2),
            ];
        });

        return [
            'month' => $month,
            'year' => $year,
            'monthLabel' => $start->format('F Y'),
            'rows' => $rows,
            'totals' => [
                'workdays' => $rows->sum('workdays'),
                'present' => $rows->sum('present'),
                'absences' => $rows->sum('absences'),
                'hours' => round($rows->sum('hours'), 2),
                'late' => $rows->sum('late'),
                'undertime' => $rows->sum('undertime'),
                'ot_hours' => round($rows->sum('ot_hours'), 2),
            ],
            'divisions' => Division::orderBy('name')->get(['id', 'name']),
        ];
    }
}
