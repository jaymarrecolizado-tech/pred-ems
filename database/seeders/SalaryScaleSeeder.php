<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalaryScaleSeeder extends Seeder
{
    /**
     * SAMPLE salary scale rows for development only.
     *
     * ⚠️ IMPORTANT: Replace with the OFFICIAL Salary Standardization Law (SSL)
     * table for the current year (DBM Circular Letter / EO implementing SSL VI)
     * before using in production. Recommended approach:
     *  1. Obtain the official SSL table (grades 1–33, steps 1–8) as CSV from DBM.
     *  2. Import it via a CSV importer or insert directly, e.g.:
     *        php artisan tinker --execute="..."
     *  3. Delete these placeholder rows first.
     */
    public function run(): void
    {
        // Representative placeholders — DO NOT use these amounts in production.
        $samples = [
            [1, 1, 14500.00], [1, 2, 15100.00], [1, 3, 15700.00],
            [11, 1, 32000.00], [11, 2, 33400.00], [11, 3, 34800.00],
            [18, 1, 62000.00], [18, 2, 64600.00],
            [24, 1, 95000.00], [24, 2, 99000.00],
            [30, 1, 160000.00], [30, 2, 166000.00],
        ];

        foreach ($samples as [$grade, $step, $amount]) {
            DB::table('salary_scales')->updateOrInsert(
                ['salary_grade' => $grade, 'step' => $step],
                ['amount' => $amount, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
