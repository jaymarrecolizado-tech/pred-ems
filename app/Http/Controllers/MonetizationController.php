<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonetizationRequest;
use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Notifications\LeaveMonetizedNotification;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\Notifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Vacation leave monetization (CSC Omnibus Rules on Leave, MC 41 s. 1998 as
 * amended) — admin/HR convert VL credits into cash at (monthly salary ÷ 22)
 * per day. Rules enforced here and re-checked server-side on every request:
 * the employee must have accumulated ≥ 10 VL days, must retain ≥ 5 days after
 * monetization, and no more than 30 days may be monetized per calendar year.
 * Each processing debits the append-only ledger and mints a MO-YYYY-NNNN
 * voucher.
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
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
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
     * Process a VL monetization: validate the CSC rules against the live
     * ledger, then (atomically) create the record and debit the ledger.
     */
    public function store(StoreMonetizationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $vl = LeaveType::where('code', 'VL')->firstOrFail();
        $employee = Employee::with('employmentType')->findOrFail($validated['employee_id']);

        if (! $employee->employmentType?->has_leave_credits) {
            return back()->with('error', 'This employee has no leave entitlement.')->withInput();
        }
        if ((float) $employee->monthly_salary <= 0) {
            return back()->with('error', 'No monthly salary is on file for this employee.')->withInput();
        }

        $year = (int) $validated['year'];
        $days = (float) $validated['days'];

        $balance = $employee->leaveBalanceFor($vl);
        $usedThisYear = (float) LeaveMonetization::where('employee_id', $employee->id)
            ->where('year', $year)
            ->sum('days');

        // CSC rules — collect every violation so HR sees the full picture.
        $errors = [];
        if ($balance < 10) {
            $errors[] = 'Eligibility requires at least 10 VL days accumulated (balance: ' . $this->fmt($balance) . ').';
        }
        if ($days > $balance - 5) {
            $errors[] = 'Cannot monetize more than ' . $this->fmt(max(0, $balance - 5))
                . ' day(s) so the employee retains at least 5 VL days.';
        }
        if ($usedThisYear + $days > 30) {
            $errors[] = 'Only 30 VL days may be monetized per year (' . $this->fmt($usedThisYear)
                . " already monetized in {$year}).";
        }

        if ($errors) {
            return back()->with('error', implode(' ', $errors))->withInput();
        }

        $perDayRate = round($employee->monthly_salary / 22, 2);
        $gross = round($perDayRate * $days, 2);

        try {
            $monetization = DB::transaction(function () use ($employee, $vl, $year, $days, $perDayRate, $gross, $validated, $balance) {
                $record = LeaveMonetization::create([
                    'employee_id' => $employee->id,
                    'year' => $year,
                    'days' => $days,
                    'per_day_rate' => $perDayRate,
                    'gross_amount' => $gross,
                    'reference_no' => LeaveMonetization::nextReferenceNo($year),
                    'remarks' => $validated['remarks'] ?? null,
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                ]);

                LeaveCreditLedger::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $vl->id,
                    'transaction_date' => now()->toDateString(),
                    'movement' => 'monetized',
                    'credit' => 0,
                    'debit' => $days,
                    'balance_after' => $balance - $days,
                    'source_id' => $record->id,
                    'source_type' => LeaveMonetization::class,
                    'remarks' => "VL monetization ({$year}) — ref " . $record->reference_no,
                    'created_by' => auth()->id(),
                ]);

                return $record;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // A concurrent request raced the same reference number.
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Please retry — the reference number was just taken by another entry.')->withInput();
            }

            throw $e;
        }

        Audit::record('created', $monetization, [], $monetization->toArray());

        if ($employee->user) {
            Notifier::send($employee->user, new LeaveMonetizedNotification($monetization));
        }

        return back()->with('success', 'Monetization processed: ' . $this->fmt($days) . ' day(s) of VL → ₱'
            . number_format($gross, 2) . ' for ' . $employee->full_name . ' (' . $monetization->reference_no . ').');
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

        $filename = 'VL_Monetization_Voucher_' . $monetization->reference_no . '.pdf';

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
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function eligibleEmployees(int $year): \Illuminate\Support\Collection
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

    private function fmt(float $value): string
    {
        return number_format($value, $value == (int) $value ? 0 : 2);
    }
}
