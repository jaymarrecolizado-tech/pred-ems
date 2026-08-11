<?php

namespace App\Http\Controllers;

use App\Actions\AdjustPayrollItem;
use App\Actions\FinalizePayroll;
use App\Actions\GeneratePayrollItems;
use App\Actions\MarkRemittanceRemitted;
use App\Http\Requests\MarkRemittedRequest;
use App\Http\Requests\PayrollAdjustmentRequest;
use App\Http\Requests\StorePayrollPeriodRequest;
use App\Models\ContributionRate;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Remittance;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Payroll module (Phase 6) — payroll periods, per-employee computation,
 * payslips and remittance tracking.
 *
 * Lifecycle: draft → generate items → finalize (locks period, issues
 * payslips + remittance summaries) → mark remittances remitted/verified.
 * The business operations (generate, adjust, finalize, remit) live in
 * App\Actions\*; this controller handles routing + flashes.
 */
class PayrollController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Periods */
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

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $period = PayrollPeriod::create($validated + [
                'name' => $validated['period_from'].' to '.$validated['period_to'],
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
    /*  Computation */
    /* ------------------------------------------------------------------ */

    /**
     * Compute (or recompute) payroll items for a draft period — the
     * orchestration lives in App\Actions\GeneratePayrollItems.
     */
    public function generate(PayrollPeriod $period): RedirectResponse
    {
        abort_unless($period->isDraft(), 409, 'Only draft periods can be recomputed.');

        $result = (new GeneratePayrollItems)->handle($period);

        return back()->with('success', $result->message);
    }

    /* ------------------------------------------------------------------ */
    /*  Per-item adjustments (honoraria / overtime / LWOP) */
    /* ------------------------------------------------------------------ */

    /**
     * Adjustment form for a single draft item. The engine already supports
     * honoraria, overtime, other income (taxable additions) and LWOP / other
     * deductions — this screen is the missing per-item entry UI for them.
     */
    public function adjust(PayrollItem $item): View
    {
        abort_unless($item->period->isDraft(), 409, 'Only draft periods can be adjusted.');

        $item->load(['employee.position', 'employee.division']);

        return view('payroll.adjust', compact('item'));
    }

    /**
     * Save adjustments for one item and recompute it with the new lines —
     * handled by App\Actions\AdjustPayrollItem. The full computation trace
     * (including the manual lines) is re-persisted, so the payslip stays
     * auditable.
     */
    public function updateAdjustment(PayrollAdjustmentRequest $request, PayrollItem $item): RedirectResponse
    {
        abort_unless($item->period->isDraft(), 409, 'Only draft periods can be adjusted.');

        $result = (new AdjustPayrollItem)->handle($item, $request->validated());

        return redirect()->route('payroll.show', $item->period)
            ->with('success', $result->message);
    }

    /**
     * Finalize a draft period: lock items, issue one payslip per item and
     * aggregate remittance summaries per agency — handled by
     * App\Actions\FinalizePayroll. Irreversible (corrections go through a
     * reversal/adjustment period).
     */
    public function finalize(PayrollPeriod $period): RedirectResponse
    {
        abort_unless($period->isDraft(), 409, 'This period is already locked.');

        $result = (new FinalizePayroll)->handle($period);

        if (! $result->success) {
            return back()->withErrors($result->errors);
        }

        return back()->with('success', $result->message);
    }

    /* ------------------------------------------------------------------ */
    /*  Payslips */
    /* ------------------------------------------------------------------ */

    public function payslipPdf(Payslip $payslip): Response
    {
        $this->authorizeAccess($payslip);

        $payslip->load(['item.period', 'item.employee.position', 'item.employee.employmentType', 'generator']);

        $filename = 'Payslip_'.str_replace([' ', '.'], '_', $payslip->item->employee->full_name).'_'.$payslip->reference_no.'.pdf';

        $pdf = Pdf::loadView('payroll.payslip', ['payslip' => $payslip])
            ->setPaper('a4', 'portrait');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /* ------------------------------------------------------------------ */
    /*  Remittances */
    /* ------------------------------------------------------------------ */

    public function remittances(): View
    {
        $remittances = Remittance::query()
            ->orderByDesc('period_from')
            ->orderBy('agency')
            ->paginate(25);

        return view('payroll.remittances', compact('remittances'));
    }

    public function markRemitted(MarkRemittedRequest $request, Remittance $remittance): RedirectResponse
    {
        $result = (new MarkRemittanceRemitted)->handle($remittance, $request->validated());

        return back()->with('success', $result->message);
    }

    /* ------------------------------------------------------------------ */
    /*  Access */
    /* ------------------------------------------------------------------ */

    private function authorizeAccess(Payslip $payslip): void
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['admin', 'hr', 'payroll']) && $payslip->item->employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to view this payslip.');
        }
    }
}
