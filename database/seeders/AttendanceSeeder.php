<?php

namespace Database\Seeders;

use App\Models\AttendanceCheckpoint;
use App\Models\Setting;
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
    }
}
