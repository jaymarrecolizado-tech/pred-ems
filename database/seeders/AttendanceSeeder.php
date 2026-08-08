<?php

namespace Database\Seeders;

use App\Models\AttendanceCheckpoint;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Initial geofence checkpoints — DICT RO2 HQ (Tuguegarao City) plus the
     * five provincial offices. Coordinates are approximate office locations;
     * HR should verify/adjust them on the map in the app.
     *
     * Also seeds the default CSC office hours used by the DTR.
     */
    public function run(): void
    {
        $checkpoints = [
            ['name' => 'DICT RO2 HQ — Tuguegarao City', 'latitude' => 17.6132, 'longitude' => 121.7272, 'radius_meters' => 300],
            ['name' => 'DICT Cagayan Provincial Office', 'latitude' => 17.6160, 'longitude' => 121.7290, 'radius_meters' => 200],
            ['name' => 'DICT Isabela Provincial Office', 'latitude' => 16.9750, 'longitude' => 121.8100, 'radius_meters' => 200],
            ['name' => 'DICT Nueva Vizcaya Provincial Office', 'latitude' => 16.4890, 'longitude' => 121.1450, 'radius_meters' => 200],
            ['name' => 'DICT Quirino Provincial Office', 'latitude' => 16.2840, 'longitude' => 121.5520, 'radius_meters' => 200],
            ['name' => 'DICT Batanes Provincial Office', 'latitude' => 20.4510, 'longitude' => 121.9700, 'radius_meters' => 200],
        ];

        foreach ($checkpoints as $data) {
            AttendanceCheckpoint::updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['is_active' => true, 'notes' => 'Seeded initial location — verify on map.'])
            );
        }

        Setting::set('office_hours', [
            'am_start' => '08:00',
            'am_end' => '12:00',
            'pm_start' => '13:00',
            'pm_end' => '17:00',
        ]);

        // --- Work schedules (AOM No. 2026-020) ---
        $standard = WorkSchedule::updateOrCreate(
            ['name' => 'Standard 8-Hour (Mon–Fri)'],
            [
                'description' => 'Default CSC 8-hour workday: 8:00 AM – 5:00 PM, Monday to Friday.',
                'days' => [
                    '1' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                    '2' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                    '3' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                    '4' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                    '5' => ['work' => true, 'am_start' => '08:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '17:00'],
                    '6' => ['work' => false],
                    '7' => ['work' => false],
                ],
                'is_active' => true,
                'starts_on' => null,
                'ends_on' => null,
            ]
        );

        // 4-Day Compressed Workweek effective 03 August 2026 (AOM No. 2026-020):
        // Mon–Thu 7:00 AM – 6:00 PM (10 hrs/day, 1-hr meal break, 40 hrs/week),
        // Friday is the designated rest day. If a holiday falls on a rest day of
        // the week, the week reverts to the Standard schedule above.
        WorkSchedule::updateOrCreate(
            ['name' => '4-Day Compressed Workweek (Mon–Thu)'],
            [
                'description' => 'AOM No. 2026-020: Mon–Thu 7:00 AM – 6:00 PM, Friday rest day.',
                'days' => [
                    '1' => ['work' => true, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '2' => ['work' => true, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '3' => ['work' => true, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '4' => ['work' => true, 'am_start' => '07:00', 'am_end' => '12:00', 'pm_start' => '13:00', 'pm_end' => '18:00'],
                    '5' => ['work' => false],
                    '6' => ['work' => false],
                    '7' => ['work' => false],
                ],
                'is_active' => true,
                'starts_on' => '2026-08-03',
                'ends_on' => null,
                'revert_schedule_id' => $standard->id,
            ]
        );

        // --- Fixed-date national holidays (recurring yearly) ---
        $fixedHolidays = [
            ['name' => 'New Year\'s Day', 'date' => '2026-01-01', 'type' => 'regular_holiday'],
            ['name' => 'Araw ng Kagitingan', 'date' => '2026-04-09', 'type' => 'regular_holiday'],
            ['name' => 'Labor Day', 'date' => '2026-05-01', 'type' => 'regular_holiday'],
            ['name' => 'Independence Day', 'date' => '2026-06-12', 'type' => 'regular_holiday'],
            ['name' => 'National Heroes Day', 'date' => '2026-08-31', 'type' => 'regular_holiday'],
            ['name' => 'Bonifacio Day', 'date' => '2026-11-30', 'type' => 'regular_holiday'],
            ['name' => 'Christmas Day', 'date' => '2026-12-25', 'type' => 'regular_holiday'],
            ['name' => 'Rizal Day', 'date' => '2026-12-30', 'type' => 'regular_holiday'],
            ['name' => 'All Saints\' Day', 'date' => '2026-11-01', 'type' => 'special_nonworking'],
            ['name' => 'Immaculate Conception', 'date' => '2026-12-08', 'type' => 'special_nonworking'],
        ];

        foreach ($fixedHolidays as $data) {
            Holiday::updateOrCreate(
                ['name' => $data['name'], 'date' => $data['date']],
                ['type' => $data['type'], 'is_repeating' => true]
            );
        }
    }
}
