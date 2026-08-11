<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMonetization;
use App\Http\Requests\StoreMonetizationRequest;
use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Support\DocumentIssuer;
use App\Support\Search;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Vacation leave monetization (CSC Omnibus Rules on Leave, MC 41 s. 1998 as
 * amended) — admin/HR convert VL credits into cash at (monthly salary ÷ 22)
 * per day. The rules are enforced and re-checked server-side on every request
 * by App\Actions\ProcessMonetization: the employee must have accumulated ≥ 10
 * VL days, must retain ≥ 5 days after monetization, and no more than 30 days
 * may be monetized per calendar year. Each processing debits the append-only
 * ledger and mints a MO-YYYY-NNNN voucher.
 */
class MonetizationController extends Controller
{
    /**
     * Registry of processed monetizations + the process form, side by side.
     */
    public function index(Request $request): View
    {
        // A real year is always passed to the process-form caps — "All years"
        // in the filter only relaxes the list, never the CSC rule hints.
        $year = $request->filled('year') ? (int) $request->input('year') : now()->year;

        $monetizations = LeaveMonetization::query()
            ->with(['employee', 'processedBy'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->string('search'));
                $q->whereHas('employee', function ($inner) use ($search) {
                    $inner->where('first_name', 'like', Search::contains($search))
                        ->orWhere('last_name', 'like', Search::contains($search))
                        ->orWhere('employee_number', 'like', Search::contains($search));
                });
            })
            ->when($request->filled('year'), fn ($q) => $q->where('year', $year))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('leave.monetization', [
            'monetizations' => $monetizations,
            'employees' => $this->eligibleEmployees($year),
            'year' => $year,
            'years' => collect(range(now()->year, now()->year - 4)),
            'vl' => LeaveType::where('code', 'VL')->first(),
        ]);
    }

    /**
     * Process a VL monetization: the CSC rules are validated against the live
     * ledger inside App\Actions\ProcessMonetization, then (atomically) the
     * record is created and the ledger debited.
     */
    public function store(StoreMonetizationRequest $request): RedirectResponse
    {
        $result = (new ProcessMonetization)->handle($request->validated());

        if (! $result->success) {
            return back()->with('error', $result->message)->withInput();
        }

        return back()->with('success', $result->message);
    }

    /**
     * Download the MO-YYYY-NNNN voucher — the computation sheet for the
     * cashier. The employee may open their own; admin/HR any.
     */
    public function voucher(LeaveMonetization $monetization): Response
    {
        $employee = auth()->user()->employee;
        $isOwner = $employee && $monetization->employee_id === $employee->id;
        abort_unless($isOwner || auth()->user()->hasAnyRole(['admin', 'hr']), 403);

        $monetization->load(['employee.position', 'employee.employmentType', 'processedBy']);

        $filename = 'VL_Monetization_Voucher_'.$monetization->reference_no.'.pdf';

        return Pdf::loadView('leave.monetization-voucher', [
            'monetization' => $monetization,
            'preparer' => DocumentIssuer::preparer(),
            'certifier' => DocumentIssuer::certifier(),
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * Leave-entitled active employees with a salary on file and room left
     * under the CSC rules for the selected year (VL balance ≥ 10 and the
     * retain-5 / max-30 caps still allow ≥ 0.5 day).
     *
     * @return Collection<int, object>
     */
    private function eligibleEmployees(int $year): Collection
    {
        $vl = LeaveType::where('code', 'VL')->first();
        if (! $vl) {
            return collect();
        }

        $employees = Employee::query()
            ->whereHas('employmentType', fn ($q) => $q->where('has_leave_credits', true))
            ->where('status', 'active')
            ->whereNotNull('monthly_salary')
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

        $usedThisYear = LeaveMonetization::query()
            ->where('year', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, COALESCE(SUM(days), 0) AS days')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $employee) use ($balances, $usedThisYear, $year) {
            $balance = (float) ($balances->get($employee->id)?->balance ?? 0);

            return (object) [
                'employee' => $employee,
                'balance' => $balance,
                'max_days' => $balance >= 10
                    ? max(0.0, min($balance - 5, 30 - (float) ($usedThisYear->get($employee->id)?->days ?? 0)))
                    : 0.0,
                'per_day_rate' => round((float) $employee->monthly_salary / 22, 2),
                'year' => $year,
            ];
        })->filter(fn ($row) => $row->max_days >= 0.5)->values();
    }
}
