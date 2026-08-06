<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run in dependency order (children referenced by later seeders first).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            EmploymentTypeSeeder::class,
            DivisionsAndPositionsSeeder::class,
            LeaveTypeSeeder::class,
            ContributionRateSeeder::class,
            SalaryScaleSeeder::class,
            DevEmployeeSeeder::class,
            RealDirectorySeeder::class, // real personnel directory (130 employees)
            EmployeeUserSeeder::class,  // login accounts for all active employees (email + !Password123)
        ]);
    }
}
