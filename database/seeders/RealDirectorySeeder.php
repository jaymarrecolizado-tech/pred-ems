<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Loads the real DICT RO2 personnel directory (130 records) from the
 * normalized `database/data/employees_directory.json` produced by
 * `data/normalize_to_json.py`.
 *
 * Idempotent: employees are upserted by employee number (RO2-0101+), and
 * appointments / qualifications are only created when missing.
 *
 * NOTE: re-running refreshes imported fields from the JSON (the directory is
 * treated as the source of truth for imported records), so HR edits made in
 * the UI after an import will be overwritten on the next re-seed.
 *
 * Run standalone:  php artisan db:seed --class=RealDirectorySeeder
 */
class RealDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/employees_directory.json');
        if (! file_exists($path)) {
            $this->command?->warn('employees_directory.json not found — skipping RealDirectorySeeder.');

            return;
        }

        $records = json_decode((string) file_get_contents($path), true);
        if (! is_array($records) || $records === []) {
            $this->command?->warn('employees_directory.json is empty — skipping RealDirectorySeeder.');

            return;
        }

        $typeIds = EmploymentType::pluck('id', 'code');
        $divisionIds = Division::pluck('id', 'code');

        $appointmentType = [
            'PERMANENT' => 'original',
            'TEMPORARY' => 'temporary',
            'CASUAL' => 'casual',
            'CO_TERMINUS' => 'co_terminus',
            'CONTRACTUAL' => 'contractual',
            'CONTRACT_OF_SERVICE' => 'contract_of_service',
            'JOB_ORDER' => 'job_order',
            'GIP' => 'gip',
        ];

        $imported = 0;
        $created = 0;

        foreach ($records as $record) {
            $typeCode = $record['employment_type_code'] ?? 'PERMANENT';

            $position = null;
            if (! empty($record['position_title'])) {
                $position = Position::firstOrCreate(
                    ['title' => $record['position_title']],
                    [
                        'salary_grade' => $record['salary_grade'] ?? null,
                        'level' => null,
                        'is_plantilla' => in_array($typeCode, ['PERMANENT', 'TEMPORARY', 'CASUAL', 'CO_TERMINUS']),
                        'is_active' => true,
                    ]
                );
            }

            $employee = Employee::updateOrCreate(
                ['employee_number' => $record['employee_number']],
                [
                    'first_name' => $record['first_name'] ?? '',
                    'middle_name' => $record['middle_name'] ?? null,
                    'last_name' => $record['last_name'] ?? '',
                    'suffix' => $record['suffix'] ?? null,
                    'gender' => $record['gender'] ?? null,
                    'birth_date' => $record['birth_date'] ?? null,
                    'contact_number' => $record['contact_number'] ?? null,
                    'gov_email' => $record['gov_email'] ?? null,
                    'personal_email' => $record['personal_email'] ?? null,
                    'residential_address' => $record['residential_address'] ?? null,
                    'employment_type_id' => $typeIds[$typeCode] ?? null,
                    'division_id' => $divisionIds[$record['division_code'] ?? ''] ?? null,
                    'position_id' => $position?->id,
                    'salary_grade' => $record['salary_grade'] ?? null,
                    'step' => $record['step'] ?? null,
                    'monthly_salary' => $record['monthly_salary'] ?? null,
                    'plantilla_item_no' => $record['plantilla_item_no'] ?? null,
                    'bp_number' => $record['bp_number'] ?? null,
                    'source_of_fund' => $record['source_of_fund'] ?? null,
                    'date_original_appointment' => $record['date_original_appointment'] ?? null,
                    'status' => $record['status'] ?? 'active',
                    'remarks' => $record['remarks'] ?? null,
                ]
            );

            $created += $employee->wasRecentlyCreated ? 1 : 0;

            // Original appointment record -> seeds the future CSC Service Record.
            if (! empty($record['date_original_appointment']) && ! $employee->appointments()->exists()) {
                $employee->appointments()->create([
                    'position_id' => $position?->id,
                    'division_id' => $divisionIds[$record['division_code'] ?? ''] ?? null,
                    'employment_type_id' => $typeIds[$typeCode] ?? null,
                    'appointment_type' => $appointmentType[$typeCode] ?? 'original',
                    'appointment_status' => 'approved',
                    'salary_grade' => $record['salary_grade'] ?? null,
                    'step' => $record['step'] ?? null,
                    'monthly_salary' => $record['monthly_salary'] ?? null,
                    'effective_from' => $record['date_original_appointment'],
                    'remarks' => $record['source_of_fund'] ?? null,
                ]);
            }

            // 201-file qualifications (education + CSC eligibility) for GIP interns.
            if (! empty($record['education']) && ! $employee->educations()->exists()) {
                $employee->educations()->create([
                    'level' => $record['education']['level'] ?? 'College',
                    'school_name' => 'Not on file',
                    'course' => $record['education']['course'] ?? null,
                ]);
            }

            foreach ($record['eligibilities'] ?? [] as $eligibility) {
                if (! $employee->civilServiceEligibilities()->where('eligibility', $eligibility)->exists()) {
                    $employee->civilServiceEligibilities()->create(['eligibility' => $eligibility]);
                }
            }

            $imported++;
        }

        $this->command?->info("Directory import complete: {$imported} employees processed ({$created} new).");
    }
}
