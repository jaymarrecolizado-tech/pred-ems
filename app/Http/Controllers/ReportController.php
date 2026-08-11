<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use App\Support\AttendanceSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Reports & Audit (Phase 4) — admin/HR only. Aggregate views over the live
 * data: headcount breakdowns, leave balances/utilization, document issuance,
 * and attrition. Every report also streams a CSV via `?format=csv`.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        $activeCount = Employee::where('status', 'active')->count();

        return view('reports.index', [
            'totalEmployees' => Employee::count(),
            'activeCount' => $activeCount,
            'leaveBalancesCount' => $activeCount,
            'documentsCount' => Document::count(),
            'separatedCount' => Employee::whereIn('status', ['separated', 'resigned', 'retired'])->count(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Headcount */
    /* ------------------------------------------------------------------ */

    public function headcount(Request $request): Response|View
    {
        $data = $this->headcountData($request);

        if ($format = $request->query('format')) {
            return $this->export(
                $format,
                'Headcount',
                [$data['groupLabel'], 'Count', '%'],
                $data['rows']->map(fn ($row) => [$row['label'], $row['count'], $row['pct']]),
                "Employees grouped by {$data['groupLabel']}"
            );
        }

        return view('reports.headcount', $data);
    }

    private function headcountData(Request $request): array
    {
        $group = $request->string('group', 'employment_type');
        $status = $request->string('status', 'all');

        $employees = Employee::query()
            ->with(['employmentType', 'division'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->get();

        $labels = match ($group) {
            'division' => ['Division', fn (Employee $e) => $e->division?->name ?? 'Unassigned'],
            'status' => ['Status', fn (Employee $e) => $e->status_label],
            'source_of_fund' => ['Source of Fund', fn (Employee $e) => $e->source_of_fund ?: 'Not specified'],
            default => ['Employment Type', fn (Employee $e) => $e->employmentType?->name ?? 'Unassigned'],
        };

        [$groupLabel, $labelOf] = $labels;

        $rows = $employees
            ->groupBy($labelOf)
            ->map(fn (Collection $grouped, string $label) => [
                'label' => $label,
                'count' => $grouped->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $total = max(1, $rows->sum('count'));
        $rows = $rows->map(fn ($row) => [
            'label' => $row['label'],
            'count' => $row['count'],
            'pct' => round(($row['count'] / $total) * 100, 1),
        ]);

        return [
            'group' => $group,
            'status' => $status,
            'groupLabel' => $groupLabel,
            'rows' => $rows,
            'total' => $total,
            'statuses' => ['all' => 'All statuses', 'active' => 'Active', 'on_leave' => 'On Leave', 'separated' => 'Separated', 'resigned' => 'Resigned', 'retired' => 'Retired'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Leave balances & utilization */
    /* ------------------------------------------------------------------ */

    public function leaveBalances(Request $request): Response|View
    {
        $leaveTypes = LeaveType::whereIn('code', ['VL', 'SL'])->orderBy('code')->get();
        $employees = Employee::with('employmentType')
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // One grouped query over the ledger instead of N×M single-row sums.
        $balances = LeaveCreditLedger::query()
            ->whereIn('leave_type_id', $leaveTypes->pluck('id'))
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, leave_type_id, COALESCE(SUM(credit - debit), 0) AS balance')
            ->groupBy('employee_id', 'leave_type_id')
            ->get()
            ->keyBy(fn ($row) => $row->employee_id.':'.$row->leave_type_id);

        $rows = $employees->map(function (Employee $employee) use ($leaveTypes, $balances) {
            $row = ['employee' => $employee, 'balances' => []];

            foreach ($leaveTypes as $type) {
                $row['balances'][$type->code] = (float) ($balances->get($employee->id.':'.$type->id)?->balance ?? 0);
            }

            return $row;
        });

        if ($format = $request->query('format')) {
            $headers = ['Employee Number', 'Name', ...$leaveTypes->pluck('code')->map(fn ($c) => "{$c} Balance (days)")->all()];
            $data = $rows->map(function ($row) use ($leaveTypes) {
                return [
                    $row['employee']->employee_number,
                    $row['employee']->full_name,
                    ...collect($leaveTypes)->map(fn ($t) => number_format($row['balances'][$t->code], 2))->all(),
                ];
            });

            return $this->export($format, 'Leave Balances', $headers, $data, 'VL/SL balances of all active employees');
        }

        return view('reports.leave-balances', [
            'rows' => $rows,
            'leaveTypes' => $leaveTypes,
            'totalActive' => $employees->count(),
        ]);
    }

    public function leaveUtilization(Request $request): Response|View
    {
        $year = (int) $request->input('year', now()->year);

        $rows = LeaveType::orderBy('code')->get()->map(function (LeaveType $type) use ($year) {
            $applications = $type->applications()
                ->where('status', 'approved')
                ->whereYear('date_from', $year);

            return [
                'leave_type' => $type,
                'applications' => (clone $applications)->count(),
                'employees' => (clone $applications)->distinct('employee_id')->count('employee_id'),
                'days' => (float) (clone $applications)->sum('days_applied'),
            ];
        })->filter(fn ($row) => $row['applications'] > 0)->values();

        if ($format = $request->query('format')) {
            return $this->export(
                $format,
                "Leave Utilization {$year}",
                ['Leave Type', 'Approved Applications', 'Employees', 'Days Taken'],
                $rows->map(fn ($row) => [$row['leave_type']->name, $row['applications'], $row['employees'], number_format($row['days'], 2)]),
                "Approved leave applications in {$year}"
            );
        }

        return view('reports.leave-utilization', [
            'rows' => $rows,
            'year' => $year,
            'years' => collect(range(now()->year, now()->year - 4)),
            'totalDays' => $rows->sum('days'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Forced leave monitoring (CSC) */
    /* ------------------------------------------------------------------ */

    /**
     * CSC Omnibus Rules on Leave: officials/employees with ≥ 10 accumulated
     * VL credits must take ≥ 5 working days of vacation leave per calendar
     * year. This report monitors compliance: VL balance now vs. VL days
     * actually taken in the selected year.
     */
    public function forcedLeave(Request $request): Response|View
    {
        $year = (int) $request->input('year', now()->year);

        $vl = LeaveType::where('code', 'VL')->firstOrFail();
        $employees = Employee::with('employmentType')
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $balances = LeaveCreditLedger::query()
            ->where('leave_type_id', $vl->id)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, COALESCE(SUM(credit - debit), 0) AS balance')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $taken = LeaveApplication::query()
            ->where('status', 'approved')
            ->where('leave_type_id', $vl->id)
            ->whereYear('date_from', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, COALESCE(SUM(days_applied), 0) AS days')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($balances, $taken) {
            $balance = (float) ($balances->get($employee->id)?->balance ?? 0);
            $takenDays = (float) ($taken->get($employee->id)?->days ?? 0);
            $required = $balance >= 10 ? 5.0 : 0.0;

            return [
                'employee' => $employee,
                'balance' => $balance,
                'taken' => $takenDays,
                'required' => $required,
                'deficit' => $required > 0 ? max(0.0, $required - $takenDays) : 0.0,
                'status' => match (true) {
                    $required === 0.0 => 'exempt',
                    $takenDays >= $required => 'compliant',
                    default => 'non_compliant',
                },
            ];
        });

        if ($format = $request->query('format')) {
            return $this->export(
                $format,
                'Forced_Leave_'.$year,
                ['Employee No', 'Name', 'Division', 'VL Balance (days)', "VL Taken {$year} (days)", 'Required (days)', 'Status'],
                $rows->map(fn ($row) => [
                    $row['employee']->employee_number,
                    $row['employee']->full_name,
                    $row['employee']->division?->name ?? '—',
                    number_format($row['balance'], 2),
                    number_format($row['taken'], 2),
                    number_format($row['required'], 2),
                    strtoupper(str_replace('_', ' ', $row['status'])),
                ]),
                "CSC forced leave monitoring — employees with ≥ 10 VL credits must take ≥ 5 VL working days in {$year}"
            );
        }

        return view('reports.forced-leave', [
            'rows' => $rows,
            'year' => $year,
            'years' => collect(range(now()->year, now()->year - 4)),
            'compliant' => $rows->where('status', 'compliant')->count(),
            'nonCompliant' => $rows->where('status', 'non_compliant')->count(),
            'exempt' => $rows->where('status', 'exempt')->count(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Documents issued */
    /* ------------------------------------------------------------------ */

    public function documents(Request $request): Response|View
    {
        $type = $request->string('type', 'all');

        $documents = Document::query()
            ->with(['employee', 'generatedBy'])
            ->when($type !== 'all', fn ($q) => $q->where('document_type', $type))
            ->latest('generated_at')
            ->paginate(30)
            ->withQueryString();

        if ($format = $request->query('format')) {
            $all = Document::query()
                ->with(['employee', 'generatedBy'])
                ->when($type !== 'all', fn ($q) => $q->where('document_type', $type))
                ->latest('generated_at')
                ->get();

            return $this->export(
                $format,
                'Documents Issued',
                ['Reference No', 'Document', 'Employee', 'Issued By', 'Date Issued'],
                $all->map(fn (Document $d) => [
                    $d->reference_no,
                    $d->document_type_label,
                    $d->employee?->full_name ?? '—',
                    $d->generatedBy?->name ?? '—',
                    $d->generated_at->format('Y-m-d H:i'),
                ])
            );
        }

        return view('reports.documents', [
            'documents' => $documents,
            'type' => $type,
            'types' => [
                'all' => 'All documents',
                'service_record' => 'Service Record',
                'certificate_of_employment' => 'Certificate of Employment',
                'leave_balances' => 'Certificate of Leave Balances',
                'no_pending_case' => 'Certification of No Pending Case',
                'dtr' => 'Daily Time Record',
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Attrition / onboarding */
    /* ------------------------------------------------------------------ */

    public function attrition(Request $request): Response|View
    {
        $year = (int) $request->input('year', now()->year);

        $separated = Employee::query()
            ->with(['employmentType', 'position'])
            ->whereIn('status', ['separated', 'resigned', 'retired'])
            ->whereYear('updated_at', $year)
            ->orderBy('last_name')
            ->get();

        $hired = Employee::query()
            ->with(['employmentType', 'position'])
            ->whereYear('date_original_appointment', $year)
            ->orderBy('last_name')
            ->get();

        if ($format = $request->query('format')) {
            // One export with both sections (separations then new hires), so it
            // always returns a file regardless of which is non-empty.
            return $this->export(
                $format,
                "Attrition_{$year}",
                ['Section', 'Employee Number', 'Name', 'Status/Position', 'Date'],
                $separated->map(fn (Employee $e) => [
                    'Separated', $e->employee_number, $e->full_name,
                    $e->status_label.' · '.($e->position?->title ?? '—'),
                    $e->updated_at?->format('Y-m-d') ?? '—',
                ])
                    ->concat($hired->map(fn (Employee $e) => [
                        'New Hire', $e->employee_number, $e->full_name,
                        $e->position?->title ?? '—',
                        $e->date_original_appointment?->format('Y-m-d') ?? '—',
                    ]))
            );
        }

        return view('reports.attrition', [
            'separated' => $separated,
            'hired' => $hired,
            'year' => $year,
            'years' => collect(range(now()->year, now()->year - 4)),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Attendance summary */
    /* ------------------------------------------------------------------ */

    public function attendanceSummary(Request $request): Response|View
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $divisionId = $request->integer('division') ?: null;

        $summary = AttendanceSummary::build($month, $year, $divisionId);

        if ($format = $request->query('format')) {
            $totals = $summary['totals'];

            return $this->export(
                $format,
                'Attendance_Summary_'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                ['Employee No', 'Name', 'Division', 'Work Days', 'Days Present', 'Absences', 'Hours', 'Late (min)', 'Undertime (min)', 'Rest-Day/OT (hrs)'],
                $summary['rows']->map(fn ($row) => [
                    $row['employee']->employee_number,
                    $row['employee']->full_name,
                    $row['employee']->division?->name ?? '—',
                    $row['workdays'],
                    $row['present'],
                    $row['absences'],
                    number_format($row['hours'], 2),
                    $row['late'],
                    $row['undertime'],
                    number_format($row['ot_hours'], 2),
                ]),
                "Monthly attendance summary — {$summary['monthLabel']}",
                ['', 'Totals ('.$summary['rows']->count().' employees)', '', $totals['workdays'], $totals['present'], $totals['absences'], number_format($totals['hours'], 2), $totals['late'], $totals['undertime'], number_format($totals['ot_hours'], 2)]
            );
        }

        return view('reports.attendance-summary', $summary + [
            'divisionId' => $divisionId,
            'months' => collect(range(1, 12)),
            'years' => collect(range(now()->year, now()->year - 2)),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Dispatch a report export to CSV (default), Excel (.xls) or PDF.
     *
     * @param  array<int, mixed>|null  $totals  optional totals row (PDF only)
     */
    private function export(string $format, string $filename, array $headers, Collection $rows, ?string $subtitle = null, ?array $totals = null): Response
    {
        return match ($format) {
            'xls' => $this->xls($filename, $headers, $rows),
            'pdf' => $this->pdf($filename, $headers, $rows, $subtitle, $totals),
            default => $this->csv($filename, $headers, $rows),
        };
    }

    /**
     * Excel export as SpreadsheetML 2003 XML (.xls) — pure PHP, no packages.
     * The <?mso-application?> processing instruction tells Windows/Excel it
     * is a spreadsheet, so it opens natively; UTF-8 BOM fixes encoding.
     */
    private function xls(string $filename, array $headers, Collection $rows): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .'<Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style></Styles>'
            .'<Worksheet ss:Name="Report"><Table>';

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$this->xmlSafe($header).'</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $isNumeric = is_int($cell) || is_float($cell);
                // String cells get the same formula-injection guard as CSV:
                // Excel would otherwise evaluate a leading = + - @ as a formula.
                $value = $isNumeric ? (string) $cell : $this->csvSafe((string) $cell);
                $xml .= '<Cell><Data ss:Type="'.($isNumeric ? 'Number' : 'String').'">'
                    .$this->xmlSafe($value)
                    .'</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return response("\xEF\xBB\xBF".$xml)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'.xls"');
    }

    private function xmlSafe(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * PDF export via dompdf — generic landscape table layout (see reports/pdf).
     *
     * @param  array<int, mixed>|null  $totals  optional totals row rendered bold
     */
    private function pdf(string $filename, array $headers, Collection $rows, ?string $subtitle = null, ?array $totals = null): Response
    {
        return Pdf::loadView('reports.pdf', [
            'title' => $filename,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
            'totals' => $totals,
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename.'.pdf');
    }

    private function csv(string $filename, array $headers, Collection $rows): Response
    {
        $out = fopen('php://temp', 'r+');

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($cell) => $this->csvSafe($cell), $row));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        // UTF-8 BOM so Excel opens the file with correct encoding, prefixed
        // before fputcsv's own output.
        return response("\xEF\xBB\xBF".$csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'.csv"');
    }

    /**
     * CSV formula-injection guard: cells starting with = + - @ are treated
     * as formulas by Excel/Sheets. Prefix them so they render as text.
     */
    private function csvSafe(mixed $cell): string
    {
        $value = (string) $cell;

        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true)
            ? "'".$value
            : $value;
    }
}
