<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContributionRateSeeder extends Seeder
{
    /**
     * Statutory contribution & tax rules as CONFIG DATA (2025–2026 values).
     * When rates change, update a row — no code changes needed.
     *
     * IMPORTANT: Verify all values against the latest agency circulars before
     * production use (GSIS circulars, PhilHealth premium circular, PAG-IBIG
     * Circular 460, BIR Revenue Regulations).
     */
    public function run(): void
    {
        $rates = [
            [
                'agency' => 'GSIS',
                'name' => 'GSIS Monthly Contributions (RA 8291)',
                'config' => json_encode([
                    'employee_rate' => 9,      // % of monthly compensation
                    'employer_rate' => 12,     // % shouldered by government
                    'no_salary_ceiling' => true,
                ]),
                'effective_from' => '1997-06-24',
                'effective_to' => null,
            ],
            [
                'agency' => 'PHILHEALTH',
                'name' => 'PhilHealth Premium (UHC Act, 5%)',
                'config' => json_encode([
                    'rate' => 5,               // % of monthly basic salary
                    'employee_share' => 2.5,   // split equally
                    'employer_share' => 2.5,
                    'floor' => 10000,          // salary floor
                    'ceiling' => 100000,       // salary ceiling
                    'min_premium' => 500,      // total monthly premium
                    'max_premium' => 5000,     // total monthly premium
                ]),
                'effective_from' => '2024-01-01',
                'effective_to' => null,
            ],
            [
                'agency' => 'PAGIBIG',
                'name' => 'PAG-IBIG Contributions (Circular 460)',
                'config' => json_encode([
                    'low_bracket' => 1500,     // salary <= 1500
                    'low_employee' => 1,       // %
                    'low_employer' => 2,       // %
                    'std_employee' => 2,       // % for salary > 1500
                    'std_employer' => 2,       // %
                    'cap_base' => 10000,       // salary beyond which caps apply
                    'cap_employee' => 200,     // max employee share
                    'cap_employer' => 200,     // max employer share
                ]),
                'effective_from' => '2024-01-01',
                'effective_to' => null,
            ],
            [
                'agency' => 'BIR',
                'name' => 'BIR Withholding Tax on Compensation (TRAIN Law)',
                'config' => json_encode([
                    'exemption' => 250000,     // annual tax-free amount
                    'brackets' => [
                        ['min' => 0,          'max' => 250000,  'base' => 0,      'rate' => 0],
                        ['min' => 250000,     'max' => 400000,  'base' => 0,      'rate' => 15],
                        ['min' => 400000,     'max' => 800000,  'base' => 22500,  'rate' => 20],
                        ['min' => 800000,     'max' => 2000000, 'base' => 102500, 'rate' => 25],
                        ['min' => 2000000,    'max' => 8000000, 'base' => 402500, 'rate' => 30],
                        ['min' => 8000000,    'max' => null,    'base' => 2202500,'rate' => 35],
                    ],
                ]),
                'effective_from' => '2023-01-01',
                'effective_to' => null,
            ],
        ];

        foreach ($rates as $rate) {
            DB::table('contribution_rates')->updateOrInsert(
                ['agency' => $rate['agency'], 'name' => $rate['name'], 'effective_from' => $rate['effective_from']],
                $rate + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
