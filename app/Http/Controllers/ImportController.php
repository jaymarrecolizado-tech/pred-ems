<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportAttendanceCommitRequest;
use App\Http\Requests\ImportAttendancePreviewRequest;
use App\Http\Requests\ImportCommitRequest;
use App\Http\Requests\ImportEmployeesPreviewRequest;
use App\Models\AttendanceLog;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Support\Audit;
use App\Support\ImportReader;
use DateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Bulk imports (admin/HR) — the last Phase 5 stretch item.
 *
 * Two importers over CSV / .xls / .xlsx:
 *   - Employees: roster rows upserted by employee number; employment type /
 *     division resolved by code or name, positions auto-created by title.
 *   - Attendance: punch rows (am_in/am_out/pm_in/pm_out) upserted per
 *     employee + date, stamped source=hr_manual (append-only, no overwrite
 *     of the geofence evidence beyond the unique employee/date/punch key).
 *
 * Both flows are preview-then-commit: the upload is validated row-by-row,
 * shown with per-row errors, and only the valid rows are imported on commit.
 * Every import is audited with created/updated/skipped counts.
 */
class ImportController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Hub                                                                */
    /* ------------------------------------------------------------------ */

    public function index(): View
    {
        return view('imports.index');
    }

    /* ------------------------------------------------------------------ */
    /*  Employees                                                          */
    /* ------------------------------------------------------------------ */

    public function employees(): View
    {
        return view('imports.employees', $this->employeeFormData());
    }

    public function previewEmployees(ImportEmployeesPreviewRequest $request): View|RedirectResponse
    {
        $request->validated();

        $stored = $this->storeUpload($request);

        try {
            $rows = ImportReader::rows($stored['path'], $stored['name']);
        } catch (\RuntimeException $e) {
            $this->discardUpload($stored['token']);

            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        if (empty($rows)) {
            $this->discardUpload($stored['token']);

            return back()->withErrors(['file' => 'The file has no data rows after the header.'])->withInput();
        }

        $preview = $this->validateEmployeeRows($rows);

        return view('imports.employees', array_merge($this->employeeFormData(), [
            'token' => $stored['token'],
            'mode' => $request->string('mode'),
            'rows' => $preview['rows'],
            'validCount' => $preview['validCount'],
            'errorCount' => $preview['errorCount'],
        ]));
    }

    public function commitEmployees(ImportCommitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $this->resolveUpload($validated['token']);
        $rows = ImportReader::rows($path, basename($path));
        $preview = $this->validateEmployeeRows($rows);

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

            $result = $this->importEmployeeRow($row['data'], $validated['mode'], $existingByNumber);
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->discardUpload($validated['token']);

        Audit::record('employees_imported', null, [], [
            'mode' => $validated['mode'],
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return redirect()->route('imports.employees')
            ->with('success', "Employee import complete: {$created} created, {$updated} updated, {$skipped} skipped.");
    }

    public function employeesTemplate(): \Symfony\Component\HttpFoundation\Response
    {
        $headers = ['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birth_date', 'civil_status', 'contact_number', 'gov_email', 'personal_email', 'employment_type', 'division', 'position', 'salary_grade', 'step', 'monthly_salary', 'date_original_appointment', 'source_of_fund', 'status'];
        $sample = ['RO2-0199', 'Juan', 'Dela', 'Cruz', '', 'Male', '1990-01-15', 'Single', '0917-000-0000', 'juan.cruz@dict.gov.ph', 'juan.cruz@gmail.com', 'Permanent', 'Regional Office 2', 'Administrative Assistant II', '8', '1', '21096.00', '2015-06-01', 'GAA', 'active'];

        return $this->templateCsv('employee_import_template.csv', $headers, $sample);
    }

    /* ------------------------------------------------------------------ */
    /*  Attendance                                                         */
    /* ------------------------------------------------------------------ */

    public function attendance(): View
    {
        return view('imports.attendance');
    }

    public function previewAttendance(ImportAttendancePreviewRequest $request): View|RedirectResponse
    {
        $request->validated();

        $stored = $this->storeUpload($request);

        try {
            $rows = ImportReader::rows($stored['path'], $stored['name']);
        } catch (\RuntimeException $e) {
            $this->discardUpload($stored['token']);

            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        if (empty($rows)) {
            $this->discardUpload($stored['token']);

            return back()->withErrors(['file' => 'The file has no data rows after the header.'])->withInput();
        }

        $preview = $this->validateAttendanceRows($rows);

        return view('imports.attendance', [
            'token' => $stored['token'],
            'rows' => $preview['rows'],
            'validCount' => $preview['validCount'],
            'errorCount' => $preview['errorCount'],
        ]);
    }

    public function commitAttendance(ImportAttendanceCommitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $this->resolveUpload($validated['token']);
        $rows = ImportReader::rows($path, basename($path));
        $preview = $this->validateAttendanceRows($rows);

        $imported = 0;
        $skipped = 0;

        foreach ($preview['rows'] as $row) {
            if (! empty($row['errors'])) {
                $skipped++;
                continue;
            }

            $this->importAttendanceRow($row['data']);
            $imported++;
        }

        $this->discardUpload($validated['token']);

        Audit::record('attendance_imported', null, [], [
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        return redirect()->route('imports.attendance')
            ->with('success', "Attendance import complete: {$imported} day-records imported, {$skipped} skipped.");
    }

    public function attendanceTemplate(): \Symfony\Component\HttpFoundation\Response
    {
        $headers = ['employee_number', 'log_date', 'am_in', 'am_out', 'pm_in', 'pm_out'];
        $sample = ['RO2-0107', '2026-08-03', '07:02', '12:00', '13:00', '18:05'];

        return $this->templateCsv('attendance_import_template.csv', $headers, $sample);
    }

    /* ------------------------------------------------------------------ */
    /*  Row validation (preview)                                           */
    /* ------------------------------------------------------------------ */

    private function validateEmployeeRows(array $rows): array
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

            if (! empty($data['birth_date']) && $this->parseDate($data['birth_date']) === null) {
                $errors[] = 'Invalid birth date "'.$data['birth_date'].'". Use YYYY-MM-DD or MM/DD/YYYY.';
            }

            if (! empty($data['date_original_appointment']) && $this->parseDate($data['date_original_appointment']) === null) {
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

    private function validateAttendanceRows(array $rows): array
    {
        $employees = Employee::pluck('id', 'employee_number');

        $normalized = collect($rows)->map(function ($row) use ($employees) {
            $errors = [];
            $data = $row;

            $empNumber = trim((string) ($data['employee_number'] ?? ''));
            $employeeId = $employees[$empNumber] ?? null;

            if ($empNumber === '' || $employeeId === null) {
                $errors[] = 'Employee number not found: "'.$empNumber.'".';
            }

            $logDate = $this->parseDate((string) ($data['log_date'] ?? ''));
            if ($logDate === null) {
                $errors[] = 'Invalid log date "'.($data['log_date'] ?? '').'". Use YYYY-MM-DD.';
            }

            foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
                $value = (string) ($data[$punch] ?? '');
                if ($value === '') {
                    continue;
                }
                if ($this->parseTime($value) === null) {
                    $errors[] = "Invalid {$punch} time \"{$value}\". Use HH:MM (24-hour) or HH:MM AM/PM.";
                }
            }

            if ($logDate && $employeeId && empty($errors)) {
                $data['employee_id'] = $employeeId;
                $data['log_date_parsed'] = $logDate;
                foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
                    $data[$punch.'_parsed'] = $this->parseTime((string) ($data[$punch] ?? ''));
                }
            }

            return ['data' => $data, 'errors' => $errors];
        });

        return [
            'rows' => $normalized->values()->all(),
            'validCount' => $normalized->filter(fn ($r) => empty($r['errors']))->count(),
            'errorCount' => $normalized->filter(fn ($r) => ! empty($r['errors']))->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Row import (commit)                                                */
    /* ------------------------------------------------------------------ */

    private function importEmployeeRow(array $data, string $mode, \Illuminate\Support\Collection $existingByNumber): string
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


    private function importAttendanceRow(array $data): void
    {
        foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $punch) {
            $time = $data[$punch.'_parsed'] ?? null;
            if (! $time) {
                continue;
            }

            $log = AttendanceLog::updateOrCreate(
                [
                    'employee_id' => $data['employee_id'],
                    'log_date' => $data['log_date_parsed'],
                    'punch_type' => $punch,
                ],
                [
                    'employee_id' => $data['employee_id'],
                    'log_date' => $data['log_date_parsed'],
                    'punch_type' => $punch,
                    'punched_at' => $data['log_date_parsed'].' '.$time,
                    'source' => 'hr_manual',
                    'remarks' => 'Bulk import',
                ]
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function employeeFormData(): array
    {
        return [
            'employmentTypes' => EmploymentType::orderBy('sort_order')->get(),
            'modes' => [
                'upsert' => 'Create new + update existing (by employee number)',
                'create' => 'Only create new employees (skip existing numbers)',
                'update' => 'Only update existing employees (skip unknown numbers)',
            ],
        ];
    }

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
                $data[$dateField] = $this->parseDate((string) $data[$dateField]);
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

    private function storeUpload(Request $request): array
    {
        $token = Str::random(32);
        $extension = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
        $relative = $request->file('file')->storeAs('imports', $token.'.'.$extension, 'local');

        return [
            'token' => $token,
            'path' => Storage::disk('local')->path($relative),
            'name' => $token.'.'.$extension,
        ];
    }

    private function resolveUpload(string $token): string
    {
        $base = Storage::disk('local')->path('imports/'.$token);
        $candidates = glob($base.'.*') ?: [];

        abort_unless(count($candidates) === 1, 422, 'Uploaded file is missing or expired. Please upload again.');

        return $candidates[0];
    }

    private function discardUpload(string $token): void
    {
        foreach (glob(Storage::disk('local')->path('imports/'.$token).'.*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function templateCsv(string $filename, array $headers, array $sample): \Symfony\Component\HttpFoundation\Response
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        fputcsv($out, $sample);
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response("\xEF\xBB\xBF".$csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'F j, Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'h:i A', 'g:i A', 'h:iA'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);
            if ($date) {
                return $date->format('H:i:s');
            }
        }

        return null;
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
