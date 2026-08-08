<?php

namespace App\Http\Controllers;

use App\Models\ContributionRate;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Remittance;
use App\Support\Audit;
use App\Support\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Payroll module (Phase 6) — payroll periods, per-employee computation,
 * payslips and remittance tracking.
 *
 * Lifecycle: draft → generate items → finalize (locks period, issues
 * payslips + remittance summaries) → mark remittances remitted/verified.
 */
class PayrollController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Periods                                                            */
    /* ------------------------------------------------------------------ */

    public function index(): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('items')
            ->withSum('items', 'net_amount')
            ->orderByDesc('period_from')
            ->paginate(12);

        // Only the rate actually effective today per agency (a closed or
        // not-yet-effective row must not present itself as the active one).
        $rates = collect(Remittance::AGENCIES)
            ->map(fn ($agency) => ContributionRate::effectiveOn($agency, now()))
            ->filter()
            ->groupBy('agency');

        $remittancePending = Remittance::pending()->count();

        return view('payroll.index', compact('periods', 'rates', 'remittancePending'));
    }

    /**
     * Self-service payslip list for the signed-in employee (their own items only).
     */
    public function myPayslips(): View
    {
        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee record linked to this account.');

        $payslips = Payslip::query()
            ->whereHas('item', fn ($q) => $q->where('employee_id', $employee->id))
            ->with(['item.period', 'item.employee'])
            ->orderByDesc('generated_at')
            ->paginate(12);

        return view('payroll.my', compact('payslips'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'payroll_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $period = PayrollPeriod::create($validated + [
                'name' => $validated['period_from'] . ' to ' . $validated['period_to'],
                'status' => PayrollPeriod::STATUS_DRAFT,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->withErrors(['period_from' => 'A payroll period already covers these dates.'])
                    ->withInput();
            }
            throw $e;
        }

        Audit::record('created', $period, [], $period->only([
            'name', 'period_from', 'period_to', 'payroll_date', 'remarks',
        ]));

        return redirect()->route('payroll.show', $period)
            ->with('success', 'Payroll period created.');
    }

    public function show(PayrollPeriod $period): View
    {
        $period->load(['items.employee.position', 'items.payslip', 'finalizedBy']);

        $totals = (object) [
            'gross' => $period->items->sum(fn ($i) => (float) $i->gross_amount),
            'deductions' => $period->items->sum(fn ($i) => (float) $i->total_deductions),
            'net' => $period->items->sum(fn ($i) => (float) $i->net_amount),
            'gsis' => $period->items->sum(fn ($i) => (float) $i->gsis_employee_share),
            'philhealth' => $period->items->sum(fn ($i) => (float) $i->philhealth_employee_share),
            'pagibig' => $period->items->sum(fn ($i) => (float) $i->pagibig_employee_share),
            'tax' => $period->items->sum(fn ($i) => (float) $i->withholding_tax),
        ];

        return view('payroll.show', compact('period', 'totals'));
    }

    /* ------------------------------------------------------------------ */
    /*  Computation                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Compute (or recompute) payroll items for a draft period. Each employee
     * gets one item with the full computation trace persisted for audit.
     */
    public function generate(PayrollPeriod $period): RedirectResponse
    {
        abort_unless($period->isDraft(), 409, 'Only draft periods can be recomputed.');

        $asOf = $period->period_from;
        $count = 0;

        DB::transaction(function () use ($period, $asOf, &$count) {
            PayrollItem::where('payroll_period_id', $period->id)->delete();

            foreach (Payroll::eligibleEmployees() as $employee) {
                $computed = Payroll::compute($employee, $asOf);

                $computed['payroll_period_id'] = $period->id;
                $computed['employee_id'] = $employee->id;
                $computed['status'] = PayrollItem::STATUS_DRAFT;
                $computed['computation_json'] = $computed['trace'];

                PayrollItem::create(collect($computed)->except(['trace', 'income_lines', 'deduction_lines'])->all());
                $count++;
            }
        });

        Audit::record('generated', $period, [], ['items' => $count]);

        return back()->with('success', "Payroll computed for {$count} employee(s).");
    }

    /**
     * Finalize a draft period: lock items, issue one payslip per item and
     * aggregate remittance summaries per agency. Irreversible (corrections go
     * through a reversal/adjustment period).
     */
    public function finalize(PayrollPeriod $period): RedirectResponse
    {
        abort_unless($period->isDraft(), 409, 'This period is already locked.');

        $period->load('items');
        if ($period->items->isEmpty()) {
            return back()->withErrors(['items' => 'Compute the payroll before finalizing.']);
        }

        DB::transaction(function () use ($period) {
            $employerTotals = ['GSIS' => 0.0, 'PHILHEALTH' => 0.0, 'PAGIBIG' => 0.0];

            foreach ($period->items as $item) {
                $item->update(['status' => PayrollItem::STATUS_FINALIZED]);

                $trace = $item->computation_json ?? [];
                $summary = $trace['summary'] ?? [];
                $employerTotals['GSIS'] += (float) ($summary['gsis_er'] ?? 0);
                $employerTotals['PHILHEALTH'] += (float) ($summary['philhealth_er'] ?? 0);
                $employerTotals['PAGIBIG'] += (float) ($summary['pagibig_er'] ?? 0);

                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $ref = Payroll::nextPayslipRef();
                    try {
                        Payslip::create([
                            'payroll_item_id' => $item->id,
                            'reference_no' => $ref,
                            'generated_by' => auth()->id(),
                            'generated_at' => now(),
                        ]);
                        break;
                    } catch (QueryException $e) {
                        if ((int) $e->errorInfo[1] !== 1062) {
                            throw $e;
                        }
                    }
                }
            }

            // Aggregate remittance summaries per agency.
            $from = $period->period_from->toDateString();
            $to = $period->period_to->toDateString();
            $remittanceRows = [
                'GSIS' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->gsis_employee_share), 'er' => $employerTotals['GSIS']],
                'PHILHEALTH' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->philhealth_employee_share), 'er' => $employerTotals['PHILHEALTH']],
                'PAGIBIG' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->pagibig_employee_share), 'er' => $employerTotals['PAGIBIG']],
                'BIR' => (object) ['ee' => $period->items->sum(fn ($i) => (float) $i->withholding_tax), 'er' => 0.0],
            ];

            foreach ($remittanceRows as $agency => $row) {
                Remittance::updateOrCreate(
                    ['agency' => $agency, 'period_from' => $from, 'period_to' => $to],
                    [
                        'employee_share_total' => $row->ee,
                        'employer_share_total' => $row->er,
                        'grand_total' => $row->ee + $row->er,
                        'status' => Remittance::STATUS_PENDING,
                        'remarks' => 'Generated on payroll finalization',
                    ]
                );
            }

            $period->update([
                'status' => PayrollPeriod::STATUS_FINALIZED,
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]);
        });

        Audit::record('finalized', $period, [], ['status' => PayrollPeriod::STATUS_FINALIZED]);

        return back()->with('success', 'Period finalized — payslips and remittance summaries issued.');
    }

    /* ------------------------------------------------------------------ */
    /*  Payslips                                                           */
    /* ------------------------------------------------------------------ */

    public function payslipPdf(Payslip $payslip): Response
    {
        $this->authorizeAccess($payslip);

        $payslip->load(['item.period', 'item.employee.position', 'item.employee.employmentType', 'generator']);

        $filename = 'Payslip_' . str_replace([' ', '.'], '_', $payslip->item->employee->full_name) . '_' . $payslip->reference_no . '.pdf';

        $pdf = Pdf::loadView('payroll.payslip', ['payslip' => $payslip])
            ->setPaper('a4', 'portrait');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------------ */
    /*  Remittances                                                        */
    /* ------------------------------------------------------------------ */

    public function remittances(): View
    {
        $remittances = Remittance::query()
            ->orderByDesc('period_from')
            ->orderBy('agency')
            ->paginate(25);

        return view('payroll.remittances', compact('remittances'));
    }

    public function markRemitted(Request $request, Remittance $remittance): RedirectResponse
    {
        $validated = $request->validate([
            'reference_no' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $remittance->update([
            'status' => Remittance::STATUS_REMITTED,
            'reference_no' => $validated['reference_no'] ?? null,
            'remarks' => $validated['remarks'] ?? $remittance->remarks,
            'remitted_at' => now(),
        ]);

        Audit::record('remitted', $remittance, [], $remittance->only(['status', 'reference_no']));

        return back()->with('success', "{$remittance->agency} remittance marked as remitted.");
    }

    /* ------------------------------------------------------------------ */
    /*  Access                                                             */
    /* ------------------------------------------------------------------ */

    private function authorizeAccess(Payslip $payslip): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['admin', 'hr', 'payroll']) && $payslip->item->employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to view this payslip.');
        }
    }
}
