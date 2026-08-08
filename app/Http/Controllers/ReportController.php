<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
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
    /*  Headcount                                                          */
    /* ------------------------------------------------------------------ */

    public function headcount(Request $request): Response|View
    {
        $data = $this->headcountData($request);

        if ($request->query('format') === 'csv') {
            return $this->csv(
                $data['groupLabel'],
                [$data['groupLabel'], 'Count', '%'],
                $data['rows']->map(fn ($row) => [$row['label'], $row['count'], $row['pct']])
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
    /*  Leave balances & utilization                                       */
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
            ->keyBy(fn ($row) => $row->employee_id . ':' . $row->leave_type_id);

        $rows = $employees->map(function (Employee $employee) use ($leaveTypes, $balances) {
            $row = ['employee' => $employee, 'balances' => []];

            foreach ($leaveTypes as $type) {
                $row['balances'][$type->code] = (float) ($balances->get($employee->id . ':' . $type->id)?->balance ?? 0);
            }

            return $row;
        });

        if ($request->query('format') === 'csv') {
            $headers = ['Employee Number', 'Name', ...$leaveTypes->pluck('code')->map(fn ($c) => "{$c} Balance (days)")->all()];
            $data = $rows->map(function ($row) use ($leaveTypes) {
                return [
                    $row['employee']->employee_number,
                    $row['employee']->full_name,
                    ...collect($leaveTypes)->map(fn ($t) => number_format($row['balances'][$t->code], 2))->all(),
                ];
            });

            return $this->csv('Leave Balances', $headers, $data);
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

        if ($request->query('format') === 'csv') {
            return $this->csv(
                "Leave Utilization {$year}",
                ['Leave Type', 'Approved Applications', 'Employees', 'Days Taken'],
                $rows->map(fn ($row) => [$row['leave_type']->name, $row['applications'], $row['employees'], number_format($row['days'], 2)])
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
    /*  Documents issued                                                   */
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

        if ($request->query('format') === 'csv') {
            $all = Document::query()
                ->with(['employee', 'generatedBy'])
                ->when($type !== 'all', fn ($q) => $q->where('document_type', $type))
                ->latest('generated_at')
                ->get();

            return $this->csv(
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
            'types' => ['all' => 'All documents', 'service_record' => 'Service Record', 'certificate_of_employment' => 'Certificate of Employment'],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Attrition / onboarding                                             */
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

        if ($request->query('format') === 'csv') {
            // One CSV with both sections (separations then new hires), so the
            // export always returns a file regardless of which is non-empty.
            return $this->csv(
                "Attrition_{$year}",
                ['Section', 'Employee Number', 'Name', 'Status/Position', 'Date'],
                $separated->map(fn (Employee $e) => [
                    'Separated', $e->employee_number, $e->full_name,
                    $e->status_label . ' · ' . ($e->position?->title ?? '—'),
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
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

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
        return response("\xEF\xBB\xBF" . $csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
    }

    /**
     * CSV formula-injection guard: cells starting with = + - @ are treated
     * as formulas by Excel/Sheets. Prefix them so they render as text.
     */
    private function csvSafe(mixed $cell): string
    {
        $value = (string) $cell;

        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true)
            ? "'" . $value
            : $value;
    }
}
