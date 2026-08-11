<?php

namespace App\Actions;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Support\Audit;
use App\Support\ImportParser;
use Illuminate\Support\Collection;

/**
 * Bulk employee-roster import (admin/HR).
 *
 * Rows are upserted by employee number; employment type / division are
 * resolved by code or name, and positions are auto-created by title. The flow
 * is preview-then-commit: `preview()` normalizes + validates every row (with
 * per-row errors for the screen), and `commit()` re-validates and imports
 * only the valid rows, auditing created/updated/skipped counts.
 */
class ImportEmployees
{
    /**
     * Normalize + validate parsed rows; data carries rows/validCount/errorCount.
     */
    public function preview(array $rows): ActionResult
    {
        return ActionResult::ok('Preview ready.', $this->validateRows($rows));
    }

    /**
     * Re-validate the rows and import the valid ones (create/update/upsert by
     * employee number per $mode), then audit the outcome.
     */
    public function commit(array $rows, string $mode): ActionResult
    {
        $preview = $this->validateRows($rows);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Load existing employees once so the per-row lookup is O(1), not a
        // query per row (roster imports can be hundreds of rows).
        $existingByNumber = Employee::whereIn(
            'employee_number',
            collect($preview['rows'])->pluck('data.employee_number')->filter()->unique()->values()->all()
        )->get()->keyBy('employee_number');

        foreach ($preview['rows'] as $row) {
            if (! empty($row['errors'])) {
                $skipped++;

                continue;
            }

            $result = $this->importEmployeeRow($row['data'], $mode, $existingByNumber);
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        Audit::record('employees_imported', null, [], [
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return ActionResult::ok(
            "Employee import complete: {$created} created, {$updated} updated, {$skipped} skipped.",
            ['created' => $created, 'updated' => $updated, 'skipped' => $skipped]
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Row validation (preview) */
    /* ------------------------------------------------------------------ */

    private function validateRows(array $rows): array
    {
        $types = EmploymentType::get(['id', 'code', 'name']);
        $divisions = Division::get(['id', 'code', 'name']);

        // Preload existing positions by title once — avoids a SELECT per row
        // (positions are reused heavily across a roster). Genuinely new
        // titles are created via the shared map (rare), exactly like the
        // seeder did row-by-row.
        $positionIds = Position::whereIn(
            'title',
            collect($rows)->pluck('position')->filter()->unique()->values()->all()
        )->pluck('id', 'title')->all();

        // Note: a plain array, NOT a Collection — the foreach below mutates
        // rows by reference and reference iteration over a Collection does
        // not persist the $row['errors'] assignments.
        $normalized = collect($rows)->map(fn ($row) => $this->normalizeEmployeeRow($row, $types, $divisions, $positionIds))->all();
        // Track employee numbers so duplicates within the file are flagged.
        $seen = [];

        foreach ($normalized as &$row) {
            $data = $row['data'];
            $errors = [];

            if (empty($data['first_name']) || empty($data['last_name'])) {
                $errors[] = 'First and last name are required.';
            }

            $empNumber = $data['employee_number'] ?? '';
            if ($empNumber !== '') {
                if (isset($seen[$empNumber])) {
                    $errors[] = "Duplicate employee number {$empNumber} in this file.";
                }
                $seen[$empNumber] = true;
            }

            if (($data['employment_type_id'] ?? null) === null) {
                $errors[] = 'Employment type not found: "'.($data['employment_type'] ?? '').'". Use a code (e.g. Permanent, COS, JO) or exact name.';
            }

            if (! empty($data['gender']) && ! in_array(strtolower($data['gender']), ['male', 'female'])) {
                $errors[] = 'Gender must be Male or Female.';
            }

            if (! empty($data['birth_date']) && ImportParser::parseDate($data['birth_date']) === null) {
                $errors[] = 'Invalid birth date "'.$data['birth_date'].'". Use YYYY-MM-DD or MM/DD/YYYY.';
            }

            if (! empty($data['date_original_appointment']) && ImportParser::parseDate($data['date_original_appointment']) === null) {
                $errors[] = 'Invalid appointment date "'.$data['date_original_appointment'].'".';
            }

            if (! empty($data['salary_grade']) && (! ctype_digit((string) $data['salary_grade']) || (int) $data['salary_grade'] < 1 || (int) $data['salary_grade'] > 33)) {
                $errors[] = 'Salary grade must be 1–33.';
            }

            if (! empty($data['step']) && (! ctype_digit((string) $data['step']) || (int) $data['step'] < 1 || (int) $data['step'] > 8)) {
                $errors[] = 'Step must be 1–8.';
            }

            if (! empty($data['monthly_salary']) && ! is_numeric($data['monthly_salary'])) {
                $errors[] = 'Monthly salary must be a number.';
            }

            if (! empty($data['status']) && ! in_array($data['status'], ['active', 'on_leave', 'separated', 'resigned', 'retired'])) {
                $errors[] = 'Status must be active, on_leave, separated, resigned, or retired.';
            }

            $row['errors'] = $errors;
        }
        unset($row);

        return [
            'rows' => $normalized,
            'validCount' => collect($normalized)->filter(fn ($r) => empty($r['errors']))->count(),
            'errorCount' => collect($normalized)->filter(fn ($r) => ! empty($r['errors']))->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Row import (commit) */
    /* ------------------------------------------------------------------ */

    private function importEmployeeRow(array $data, string $mode, Collection $existingByNumber): string
    {
        $employeeNumber = trim((string) ($data['employee_number'] ?? ''));

        $existing = $employeeNumber !== ''
            ? $existingByNumber->get($employeeNumber)
            : null;

        if ($mode === 'create' && $existing) {
            return 'skipped'; // preview flagged duplicates only within-file
        }
        if ($mode === 'update' && ! $existing) {
            return 'skipped';
        }

        $payload = $this->employeePayload($data);

        if ($mode === 'create' || ($mode === 'upsert' && ! $existing)) {
            $payload['employee_number'] = $employeeNumber ?: $this->nextEmployeeNumber();
            $employee = Employee::create($payload);
            $existingByNumber->put($employee->employee_number, $employee);
            Audit::record('created', $employee, [], $employee->toArray());

            // Seed the original appointment so the Service Record has a start.
            if (! empty($payload['date_original_appointment'])) {
                $employee->appointments()->create([
                    'position_id' => $payload['position_id'] ?? null,
                    'division_id' => $payload['division_id'] ?? null,
                    'employment_type_id' => $payload['employment_type_id'],
                    'appointment_type' => 'original',
                    'appointment_status' => 'approved',
                    'salary_grade' => $payload['salary_grade'] ?? null,
                    'step' => $payload['step'] ?? null,
                    'monthly_salary' => $payload['monthly_salary'] ?? null,
                    'effective_from' => $payload['date_original_appointment'],
                ]);
            }

            return 'created';
        }

        $old = $existing->toArray();
        $existing->update($payload);
        Audit::record('updated', $existing, $old, $existing->toArray());

        return 'updated';
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    private function normalizeEmployeeRow(array $row, $types, $divisions, array &$positionIds): array
    {
        $data = $row;

        // Required fallback: employee_number may be blank for upsert/create.
        $data['employee_number'] = trim((string) ($data['employee_number'] ?? ''));
        $data['status'] = trim((string) ($data['status'] ?? '')) ?: 'active';

        $typeValue = trim((string) ($data['employment_type'] ?? ''));
        $resolvedType = null;
        if ($typeValue !== '') {
            // Common HR shorthand → canonical code (COS, JO, CT, …).
            $aliases = [
                'COS' => 'CONTRACT_OF_SERVICE',
                'JO' => 'JOB_ORDER',
                'CO_TERMINUS' => 'CO_TERMINUS',
                'CT' => 'CONTRACTUAL',
                'PERM' => 'PERMANENT',
                'GIP' => 'GIP',
            ];
            $typeKey = strtoupper(str_replace([' ', '-'], '_', $typeValue));
            $typeKey = $aliases[$typeKey] ?? $typeKey;

            $resolvedType = $types->first(fn ($t) => strtoupper($t->code) === $typeKey)
                ?? $types->first(fn ($t) => strtolower($t->name) === strtolower($typeValue));
        }
        $data['employment_type_id'] = $resolvedType?->id;

        $divisionValue = trim((string) ($data['division'] ?? ''));
        $data['division_id'] = null;
        if ($divisionValue !== '') {
            $divisionKey = strtoupper(str_replace([' ', '-'], '_', $divisionValue));
            $data['division_id'] = $divisions->first(fn ($d) => strtoupper((string) $d->code) === $divisionKey)?->id
                ?? $divisions->first(fn ($d) => strtolower((string) $d->name) === strtolower($divisionValue))?->id;
        }

        $positionTitle = trim((string) ($data['position'] ?? ''));
        $data['position_id'] = null;
        if ($positionTitle !== '') {
            $data['position_id'] = $positionIds[$positionTitle] ?? null;
            if ($data['position_id'] === null) {
                $position = Position::create([
                    'title' => $positionTitle,
                    'salary_grade' => ! empty($data['salary_grade']) ? (int) $data['salary_grade'] : null,
                    'level' => null,
                    'is_plantilla' => in_array($resolvedType?->code, ['PERMANENT', 'TEMPORARY', 'CASUAL', 'CO_TERMINUS']),
                    'is_active' => true,
                ]);
                $positionIds[$positionTitle] = $position->id;
                $data['position_id'] = $position->id;
            }
        }

        foreach (['birth_date', 'date_original_appointment', 'date_last_promotion'] as $dateField) {
            if (! empty($data[$dateField])) {
                $data[$dateField] = ImportParser::parseDate((string) $data[$dateField]);
            }
        }

        foreach (['salary_grade', 'step'] as $intField) {
            if (! empty($data[$intField])) {
                $data[$intField] = (int) $data[$intField];
            }
        }

        return ['data' => $data];
    }

    private function employeePayload(array $data): array
    {
        $keys = ['first_name', 'middle_name', 'maiden_name', 'last_name', 'suffix', 'birth_date', 'birth_place', 'gender', 'civil_status', 'citizenship', 'contact_number', 'personal_email', 'gov_email', 'gsis_no', 'philhealth_no', 'pagibig_no', 'tin_no', 'sss_no', 'employment_type_id', 'division_id', 'position_id', 'plantilla_item_no', 'bp_number', 'source_of_fund', 'salary_grade', 'step', 'monthly_salary', 'date_original_appointment', 'date_last_promotion', 'status', 'remarks'];

        return collect($keys)->mapWithKeys(fn ($key) => [$key => $data[$key] ?? null])->all();
    }

    private function nextEmployeeNumber(): string
    {
        $last = Employee::query()
            ->where('employee_number', 'like', 'RO2-%')
            ->orderByRaw('CAST(SUBSTRING(employee_number, 5) AS UNSIGNED) DESC')
            ->value('employee_number');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'RO2-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
