<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmploymentTypeSeeder extends Seeder
{
    /**
     * Philippine government employment classifications with entitlement flags.
     *
     * - Permanent/Temporary/Casual/Co-Terminus: full leave credits + GSIS coverage.
     * - Contractual: no leave credits as of right -> 20% salary premium instead;
     *   still GSIS-covered when receiving fixed monthly compensation (RA 8291).
     * - JO/COS: paid per service, no leave credits, not GSIS-covered.
     * - GIP (Government Internship Program): stipend-based interns, no leave credits.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'PERMANENT', 'name' => 'Permanent', 'has_leave_credits' => true,
                'has_gsis' => true, 'has_philhealth' => true, 'has_pagibig' => true,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 1, 'description' => 'Plantilla position, full CSC benefits',
            ],
            [
                'code' => 'TEMPORARY', 'name' => 'Temporary', 'has_leave_credits' => true,
                'has_gsis' => true, 'has_philhealth' => true, 'has_pagibig' => true,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 2, 'description' => 'Temporary appointment, same benefits as permanent',
            ],
            [
                'code' => 'CASUAL', 'name' => 'Casual', 'has_leave_credits' => true,
                'has_gsis' => true, 'has_philhealth' => true, 'has_pagibig' => true,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 3, 'description' => 'Casual appointment in plantilla',
            ],
            [
                'code' => 'CO_TERMINUS', 'name' => 'Co-Terminus', 'has_leave_credits' => true,
                'has_gsis' => true, 'has_philhealth' => true, 'has_pagibig' => true,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 4, 'description' => 'Co-terminus with the appointing authority',
            ],
            [
                'code' => 'CONTRACTUAL', 'name' => 'Contractual', 'has_leave_credits' => false,
                'has_gsis' => true, 'has_philhealth' => true, 'has_pagibig' => true,
                'has_withholding_tax' => true, 'requires_20pct_premium' => true,
                'sort_order' => 5, 'description' => 'No leave credits as of right; 20% premium in lieu',
            ],
            [
                'code' => 'JOB_ORDER', 'name' => 'Job Order (JO)', 'has_leave_credits' => false,
                'has_gsis' => false, 'has_philhealth' => false, 'has_pagibig' => false,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 6, 'description' => 'Paid per service rendered',
            ],
            [
                'code' => 'CONTRACT_OF_SERVICE', 'name' => 'Contract of Service (COS)', 'has_leave_credits' => false,
                'has_gsis' => false, 'has_philhealth' => false, 'has_pagibig' => false,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 7, 'description' => 'Service contract, paid per service',
            ],
            [
                'code' => 'GIP', 'name' => 'Government Internship Program (GIP)', 'has_leave_credits' => false,
                'has_gsis' => false, 'has_philhealth' => false, 'has_pagibig' => false,
                'has_withholding_tax' => true, 'requires_20pct_premium' => false,
                'sort_order' => 8, 'description' => 'Internship program for young professionals',
            ],
        ];

        foreach ($types as $type) {
            DB::table('employment_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
