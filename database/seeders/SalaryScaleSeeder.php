<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalaryScaleSeeder extends Seeder
{
    /**
     * Official Salary Standardization Law V (SSL V) salary schedule.
     *
     * Implemented by Executive Order No. 64, series of 2024 ("Updating the
     * Salary Schedule for Civilian Government Personnel"), First Tranche
     * effective January 1, 2024. Amounts are the official monthly salaries
     * (₱) for salary grades 1–33, steps 1–8 (SG 33 has steps 1–2 only).
     *
     * The EO rolls out in four tranches (2024 / 2025 / 2026 / 2027). This
     * seeder loads the verified First Tranche as the base table; later
     * tranche amounts can be added as additional rows with their own
     * `effective_from` — the payroll/reference lookups are effective-dated,
     * so the newest row wins for the current date.
     *
     * Run standalone:  php artisan db:seed --class=SalaryScaleSeeder
     */
    public function run(): void
    {
        // [salary_grade => [step1..step8]] — EO 64 s. 2024, First Tranche.
        $table = [
            1 => [13530, 13633, 13748, 13862, 13979, 14095, 14213, 14331],
            2 => [14372, 14482, 14593, 14706, 14818, 14931, 15047, 15161],
            3 => [15265, 15384, 15501, 15621, 15741, 15861, 15984, 16105],
            4 => [16209, 16334, 16460, 16586, 16714, 16841, 16971, 17101],
            5 => [17205, 17338, 17471, 17605, 17739, 17877, 18014, 18151],
            6 => [18255, 18396, 18537, 18680, 18824, 18968, 19114, 19261],
            7 => [19365, 19514, 19663, 19815, 19966, 20120, 20274, 20430],
            8 => [20534, 20720, 20908, 21096, 21287, 21479, 21674, 21870],
            9 => [22219, 22404, 22591, 22780, 22971, 23162, 23356, 23551],
            10 => [24381, 24585, 24790, 24998, 25207, 25417, 25630, 25844],
            11 => [28512, 28796, 29085, 29377, 29673, 29974, 30278, 30587],
            12 => [30705, 30989, 31277, 31568, 31863, 32162, 32464, 32770],
            13 => [32870, 33183, 33499, 33829, 34144, 34472, 34804, 35141],
            14 => [35434, 35794, 36158, 36528, 36900, 37278, 37662, 38049],
            15 => [38413, 38810, 39212, 39619, 40030, 40446, 40868, 41296],
            16 => [41616, 42052, 42494, 42941, 43394, 43852, 44317, 44786],
            17 => [45138, 45619, 46105, 46597, 47095, 47599, 48109, 48626],
            18 => [49015, 49542, 50077, 50617, 51166, 51721, 52282, 52851],
            19 => [53873, 54649, 55437, 56237, 57051, 57878, 58719, 59573],
            20 => [60157, 61032, 61922, 62827, 63747, 64669, 65599, 66532],
            21 => [67005, 67992, 68996, 70016, 71054, 72107, 73143, 74231],
            22 => [74836, 75952, 77086, 78238, 79409, 80562, 81771, 82999],
            23 => [83659, 84918, 86199, 87507, 88936, 90387, 91862, 93299],
            24 => [94132, 95668, 97230, 98817, 100430, 102069, 103685, 105378],
            25 => [107208, 108958, 110736, 112543, 114381, 116359, 118145, 120073],
            26 => [121146, 123122, 125132, 127174, 129250, 131359, 133503, 135682],
            27 => [136893, 139128, 141399, 143638, 145983, 148171, 150498, 152954],
            28 => [154320, 156838, 159398, 161845, 164485, 167267, 169654, 172423],
            29 => [173962, 176802, 179688, 182621, 185602, 188434, 191340, 194463],
            30 => [196199, 199401, 202558, 205765, 209024, 212434, 215796, 219319],
            31 => [285813, 291395, 297086, 302741, 308504, 314468, 320516, 326681],
            32 => [339921, 346777, 353769, 360727, 368002, 375424, 382996, 390719],
            33 => [428994, 441863],
        ];

        DB::transaction(function () use ($table) {
            // Replace wholesale so no placeholder or stale row survives.
            DB::table('salary_scales')->delete();

            foreach ($table as $grade => $steps) {
                foreach ($steps as $index => $amount) {
                    DB::table('salary_scales')->insert([
                        'salary_grade' => $grade,
                        'step' => $index + 1,
                        'amount' => $amount,
                        'effective_from' => '2024-01-01',
                        'effective_to' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->command?->info('SSL V (EO 64 s. 2024, First Tranche) loaded: '
            .DB::table('salary_scales')->count().' grade × step rows, effective 2024-01-01.');
    }
}
