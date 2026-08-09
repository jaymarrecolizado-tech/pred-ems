<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveTypeSeeder extends Seeder
{
    /**
     * CSC leave types (Omnibus Rules on Leave, MC 41 s. 1998 + amendments).
     * VL/SL accrue 1.25 days/month; SLP is 3 days/year, non-cumulative and
     * non-commutable; statutory leaves are fixed grants.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'VL', 'name' => 'Vacation Leave', 'accrual_per_month' => 1.25, 'annual_max_credit' => null, 'is_cumulative' => true, 'is_commutable' => true],
            ['code' => 'SL', 'name' => 'Sick Leave', 'accrual_per_month' => 1.25, 'annual_max_credit' => null, 'is_cumulative' => true, 'is_commutable' => true],
            ['code' => 'SLP', 'name' => 'Special Leave Privileges', 'accrual_per_month' => 0, 'annual_max_credit' => 3, 'annual_grant' => true, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'accrual_per_month' => 0, 'annual_max_credit' => 60, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'PATERNITY', 'name' => 'Paternity Leave', 'accrual_per_month' => 0, 'annual_max_credit' => 7, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'SOLO_PARENT', 'name' => 'Solo Parent Leave', 'accrual_per_month' => 0, 'annual_max_credit' => 7, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'VAWC', 'name' => 'VAWC Leave', 'accrual_per_month' => 0, 'annual_max_credit' => 10, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'SLW', 'name' => 'Special Leave for Women', 'accrual_per_month' => 0, 'annual_max_credit' => 60, 'is_cumulative' => false, 'is_commutable' => false],
            ['code' => 'STUDY', 'name' => 'Study Leave', 'accrual_per_month' => 0, 'annual_max_credit' => null, 'annual_grant' => false, 'is_cumulative' => true, 'is_commutable' => false],
            ['code' => 'LWOP', 'name' => 'Leave Without Pay', 'accrual_per_month' => 0, 'annual_max_credit' => null, 'is_cumulative' => false, 'is_commutable' => false],
        ];

        foreach ($types as $type) {
            DB::table('leave_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['annual_grant' => $type['annual_grant'] ?? false, 'requires_approval' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
