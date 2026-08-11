<?php

namespace App\Http\Controllers;

use App\Actions\ImportAttendance;
use App\Actions\ImportEmployees;
use App\Http\Requests\ImportAttendanceCommitRequest;
use App\Http\Requests\ImportAttendancePreviewRequest;
use App\Http\Requests\ImportCommitRequest;
use App\Http\Requests\ImportEmployeesPreviewRequest;
use App\Models\EmploymentType;
use App\Support\ImportReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
 * Both flows are preview-then-commit: the upload is stored behind a token,
 * validated row-by-row by the App\Actions\ImportEmployees /
 * App\Actions\ImportAttendance actions, shown with per-row errors, and only
 * the valid rows are imported on commit. Every import is audited with
 * created/updated/skipped counts. This controller handles the HTTP concerns
 * (view rendering, upload storage + token lifecycle, CSV templates); the
 * business rules live in the actions.
 */
class ImportController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Hub */
    /* ------------------------------------------------------------------ */

    public function index(): View
    {
        return view('imports.index');
    }

    /* ------------------------------------------------------------------ */
    /*  Employees */
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

        $preview = (new ImportEmployees)->preview($rows);

        return view('imports.employees', array_merge($this->employeeFormData(), [
            'token' => $stored['token'],
            'mode' => $request->string('mode'),
            'rows' => $preview->data['rows'],
            'validCount' => $preview->data['validCount'],
            'errorCount' => $preview->data['errorCount'],
        ]));
    }

    public function commitEmployees(ImportCommitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $this->resolveUpload($validated['token']);
        $rows = ImportReader::rows($path, basename($path));

        $result = (new ImportEmployees)->commit($rows, $validated['mode']);

        $this->discardUpload($validated['token']);

        return redirect()->route('imports.employees')
            ->with('success', $result->message);
    }

    public function employeesTemplate(): Response
    {
        $headers = ['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birth_date', 'civil_status', 'contact_number', 'gov_email', 'personal_email', 'employment_type', 'division', 'position', 'salary_grade', 'step', 'monthly_salary', 'date_original_appointment', 'source_of_fund', 'status'];
        $sample = ['RO2-0199', 'Juan', 'Dela', 'Cruz', '', 'Male', '1990-01-15', 'Single', '0917-000-0000', 'juan.cruz@dict.gov.ph', 'juan.cruz@gmail.com', 'Permanent', 'Regional Office 2', 'Administrative Assistant II', '8', '1', '21096.00', '2015-06-01', 'GAA', 'active'];

        return $this->templateCsv('employee_import_template.csv', $headers, $sample);
    }

    /* ------------------------------------------------------------------ */
    /*  Attendance */
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

        $preview = (new ImportAttendance)->preview($rows);

        return view('imports.attendance', [
            'token' => $stored['token'],
            'rows' => $preview->data['rows'],
            'validCount' => $preview->data['validCount'],
            'errorCount' => $preview->data['errorCount'],
        ]);
    }

    public function commitAttendance(ImportAttendanceCommitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $path = $this->resolveUpload($validated['token']);
        $rows = ImportReader::rows($path, basename($path));

        $result = (new ImportAttendance)->commit($rows);

        $this->discardUpload($validated['token']);

        return redirect()->route('imports.attendance')
            ->with('success', $result->message);
    }

    public function attendanceTemplate(): Response
    {
        $headers = ['employee_number', 'log_date', 'am_in', 'am_out', 'pm_in', 'pm_out'];
        $sample = ['RO2-0107', '2026-08-03', '07:02', '12:00', '13:00', '18:05'];

        return $this->templateCsv('attendance_import_template.csv', $headers, $sample);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
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

    private function templateCsv(string $filename, array $headers, array $sample): Response
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
}
