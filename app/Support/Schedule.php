<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\Setting;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves the working schedule for any given date.
 *
 * Implements AOM No. 2026-020 (4-Day Compressed Workweek) and the CSC
 * Resolution No. 2600838 rules it cites:
 *
 *   1. Holiday/work suspension on the schedule's designated rest day (e.g.
 *      Friday under the CWW — a weekday rest day, not a weekend) → the WHOLE
 *      week reverts to the standard 8-hour Mon–Fri schedule (or the
 *      schedule's configured revert target).
 *   2. Holiday/work suspension on a working day → the required hours are
 *      deemed complied (the row is flagged, no late/undertime is assessed).
 *
 * Punches are intentionally NEVER blocked on rest days/holidays/weekends —
 * those timelogs are the evidence employees use to claim CTO credits.
 */
class Schedule
{
    /**
     * Resolve the schedule + day definition for a date.
     *
     * @param  Collection<int, Holiday>|null  $holidays  preloaded holiday rows
     * @return array{
     *   schedule: ?WorkSchedule,
     *   schedule_name: string,
     *   work: bool,
     *   am_start: string,
     *   am_end: string,
     *   pm_start: string,
     *   pm_end: string,
     *   rest_day: bool,
     *   reverted: bool,
     *   holiday: ?Holiday,
     *   holiday_name: ?string,
     *   holiday_type: ?string,
     * }
     */
    public static function day(CarbonInterface $date, ?Collection $holidays = null): array
    {
        $holidays ??= Holiday::query()->get();

        $schedule = WorkSchedule::effectiveOn(Carbon::instance($date));
        $reverted = false;

        if ($schedule) {
            // CSC rule 1: a holiday/suspension on a designated rest day of this
            // week reverts the ENTIRE week to the standard schedule.
            if (self::weekHasRestDayHoliday($schedule, $date, $holidays)) {
                $schedule = $schedule->revertSchedule
                    ?? self::standardFallback()
                    ?? $schedule;
                $reverted = true;
            }
        }

        $holiday = self::holidayOn($date, $holidays);

        if (! $schedule) {
            // Legacy fallback: the plain office_hours setting as a Mon–Fri day.
            $hours = Setting::officeHours();
            $work = ! $date->isWeekend();

            return [
                'schedule' => null,
                'schedule_name' => 'Legacy office hours',
                'work' => $work,
                'am_start' => $hours['am_start'],
                'am_end' => $hours['am_end'],
                'pm_start' => $hours['pm_start'],
                'pm_end' => $hours['pm_end'],
                'rest_day' => ! $work,
                'reverted' => $reverted,
                'holiday' => $holiday,
                'holiday_name' => $holiday?->name,
                'holiday_type' => $holiday?->type,
            ];
        }

        $config = $schedule->dayConfig($date->dayOfWeekIso);
        $work = (bool) ($config['work'] ?? false);

        return [
            'schedule' => $schedule,
            'schedule_name' => $schedule->name,
            'work' => $work,
            'am_start' => $config['am_start'] ?? '08:00',
            'am_end' => $config['am_end'] ?? '12:00',
            'pm_start' => $config['pm_start'] ?? '13:00',
            'pm_end' => $config['pm_end'] ?? '17:00',
            'rest_day' => ! $work,
            'reverted' => $reverted,
            'holiday' => $holiday,
            'holiday_name' => $holiday?->name,
            'holiday_type' => $holiday?->type,
        ];
    }

    /**
     * True when any holiday/work suspension in $date's week (Mon–Sun) falls on
     * a weekday rest day of the given schedule.
     *
     * Only WEEKDAY rest days trigger the revert — weekends are non-working
     * under every schedule, so a Saturday/Sunday holiday is not the "designated
     * non-working day" the CSC rule refers to (Friday under the CWW).
     */
    private static function weekHasRestDayHoliday(WorkSchedule $schedule, CarbonInterface $date, Collection $holidays): bool
    {
        $start = Carbon::instance($date)->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $isWeekend = $d->isSaturday() || $d->isSunday();
            if ($isWeekend) {
                continue;
            }

            $isHoliday = $holidays->contains(fn (Holiday $h) => $h->occursOn($d));
            if ($isHoliday && ! $schedule->isWorkingDay($d->dayOfWeekIso)) {
                return true;
            }
        }

        return false;
    }

    private static function holidayOn(CarbonInterface $date, Collection $holidays): ?Holiday
    {
        return $holidays->first(fn (Holiday $h) => $h->occursOn($date));
    }

    /**
     * The standard Mon–Fri 8AM–5PM schedule used when a week must revert.
     */
    private static function standardFallback(): ?WorkSchedule
    {
        return WorkSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%Standard%')
                    ->orWhere('description', 'like', '%standard 8%');
            })
            ->orderBy('id')
            ->first();
    }
}
