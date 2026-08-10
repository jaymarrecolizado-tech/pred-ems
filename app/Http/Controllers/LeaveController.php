<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Notifications\LeaveApprovedNotification;
use App\Notifications\LeaveFiledNotification;
use App\Notifications\LeaveRejectedNotification;
use App\Support\Audit;
use App\Support\DocumentIssuer;
use App\Support\Notifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Self-service                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * My leave: balances (leave card) + my applications.
     */
    public function index(): View
    {
        $employee = auth()->user()->employee;

        return view('leave.index', [
            'employee' => $employee,
            'balances' => $employee ? $employee->leaveBalances() : collect(),
            'applications' => $employee
                ? LeaveApplication::with('leaveType')->where('employee_id', $employee->id)->latest()->paginate(10)
                : null,
            // Distinct page name so paging monetizations never resets the
            // applications paginator on the same page (and vice-versa).
            'monetizations' => $employee
                ? LeaveMonetization::with('processedBy')->where('employee_id', $employee->id)->latest()->paginate(5, ['*'], 'monet_page')
                : null,
        ]);
    }

    public function create(): View
    {
        $employee = auth()->user()->employee;
        abort_if(! $employee, 403, 'No 201-file record is linked to your account. Contact HR.');

        return view('leave.create', [
            'employee' => $employee,
            'balances' => $employee->leaveBalances(),
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = auth()->user()->employee;
        abort_if(! $employee, 403, 'No 201-file record is linked to your account. Contact HR.');

        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:255'],
            'contact_during_leave' => ['nullable', 'string', 'max:100'],
            'commutation_requested' => ['nullable', 'boolean'],
        ]);

        $from = Carbon::parse($validated['date_from']);
        $to = Carbon::parse($validated['date_to']);
        $days = $this->workingDays($from, $to);

        $type = LeaveType::findOrFail($validated['leave_type_id']);
        if (! $type->is_active) {
            return back()->with('error', 'This leave type is not available.')->withInput();
        }

        // Ledger-managed leaves (monthly accrual like VL/SL, or annual grants
        // like SLP) require a sufficient credit balance. Statutory leaves
        // (maternity, paternity, …) are granted per occurrence and skip this.
        if ($type->accrual_per_month > 0 || $type->annual_grant) {
            $balance = $this->balanceFor($employee, $type);
            if ($balance < $days) {
                return back()
                    ->with('error', "Insufficient {$type->code} balance: you have {$this->fmt($balance)} day(s) but filed {$this->fmt($days)}.")
                    ->withInput();
            }
        }

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'days_applied' => $days,
            'reason' => $validated['reason'],
            'contact_during_leave' => $validated['contact_during_leave'] ?? null,
            'commutation_requested' => $validated['commutation_requested'] ?? false,
            'status' => 'pending',
        ]);

        Audit::record('created', $application, [], $application->toArray());

        // Alert the approval reviewers.
        Notifier::send(Notifier::hrUsers(), new LeaveFiledNotification($application));

        return redirect()
            ->route('leave.index')
            ->with('success', "Leave application filed: {$this->fmt($days)} day(s) of {$type->name} ({$from->format('M d')} – {$to->format('M d, Y')}).");
    }

    /**
     * Employee cancels their own pending application.
     */
    public function cancel(LeaveApplication $application): RedirectResponse
    {
        abort_if($application->employee_id !== auth()->user()->employee?->id, 403);
        abort_unless($application->isPending(), 403, 'Only pending applications can be cancelled.');

        $old = $application->toArray();
        $application->update(['status' => 'cancelled']);
        Audit::record('updated', $application, $old, $application->toArray());

        return redirect()->route('leave.index')->with('success', 'Leave application cancelled.');
    }

    /**
     * Download the CSC Form No. 6 (Application for Leave) for an application
     * — the official printed form. The applicant may print their own; admin/HR
     * any application.
     */
    public function form6(LeaveApplication $application): Response
    {
        $employee = auth()->user()->employee;
        $isOwner = $employee && $application->employee_id === $employee->id;
        abort_unless($isOwner || auth()->user()->hasAnyRole(['admin', 'hr']), 403);

        $application->load(['employee.position', 'employee.division', 'leaveType']);

        // Section 7.A — live credit certification from the append-only ledger,
        // "as of" today. Earned = current balance (prior usage already netted),
        // Less = this application's days for the applied type, Balance = diff.
        $summary = function (LeaveType $type) use ($application) {
            $earned = $application->employee->leaveBalanceFor($type);
            $less = $application->leave_type_id === $type->id ? (float) $application->days_applied : 0.0;

            return [
                'earned' => number_format($earned, $earned == (int) $earned ? 0 : 2),
                'less' => number_format($less, $less == (int) $less ? 0 : 2),
                'balance' => number_format($earned - $less, ($earned - $less) == (int) ($earned - $less) ? 0 : 2),
            ];
        };

        $vlType = LeaveType::where('code', 'VL')->first();
        $slType = LeaveType::where('code', 'SL')->first();

        $filename = 'CSC_Form_6_' . str_replace([' ', '.'], '_', $application->employee->full_name) . '.pdf';

        return Pdf::loadView('leave.form6', [
            'application' => $application,
            'credits' => [
                'vl' => $vlType ? $summary($vlType) : ['earned' => '', 'less' => '', 'balance' => ''],
                'sl' => $slType ? $summary($slType) : ['earned' => '', 'less' => '', 'balance' => ''],
            ],
            'asOf' => now()->format('F j, Y'),
            'hrmo' => DocumentIssuer::preparer(),
            'adminFinance' => DocumentIssuer::certifier(),
        ])->setPaper('legal', 'portrait') // official CSC Form 6 is long-bond sized
            ->download($filename);
    }

    /* ------------------------------------------------------------------ */
    /*  Approvals (admin / HR)                                             */
    /* ------------------------------------------------------------------ */

    public function approvals(Request $request): View
    {
        $applications = LeaveApplication::query()
            ->with(['employee', 'leaveType'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->string('search'));
                $q->whereHas('employee', function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('leave.approvals', [
            'applications' => $applications,
            'statuses' => ['pending', 'approved', 'rejected', 'cancelled'],
        ]);
    }

    /**
     * Approve → deduct the leave credit from the append-only ledger.
     *
     * Re-checks the credit balance so an approval made after the balance was
     * consumed by another application can never push the ledger negative.
     */
    public function approve(LeaveApplication $application): RedirectResponse
    {
        abort_unless($application->isPending(), 403, 'This application is no longer pending.');

        $type = $application->leaveType;
        if ($type->accrual_per_month > 0 || $type->annual_grant) {
            $balance = $application->employee->leaveBalanceFor($type);
            if ($balance < (float) $application->days_applied) {
                return back()->with('error', "Cannot approve: only {$this->fmt($balance)} {$type->code} day(s) remain for {$application->employee->full_name}.");
            }
        }

        $old = $application->toArray();

        DB::transaction(function () use ($application) {
            $application->update([
                'status' => 'approved',
                'approver_id' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->debitLedger($application, 'Leave application approved');
        });

        Audit::record('approved', $application, $old, $application->toArray());

        if ($application->employee->user) {
            Notifier::send($application->employee->user, new LeaveApprovedNotification($application));
        }

        return back()->with('success', "Leave approved for {$application->employee->full_name}.");
    }

    public function reject(Request $request, LeaveApplication $application): RedirectResponse
    {
        abort_unless($application->isPending(), 403, 'This application is no longer pending.');

        $validated = $request->validate([
            'denial_reason' => ['required', 'string', 'max:255'],
        ]);

        $old = $application->toArray();
        $application->update([
            'status' => 'rejected',
            'approver_id' => auth()->id(),
            'denial_reason' => $validated['denial_reason'],
        ]);

        Audit::record('rejected', $application, $old, $application->toArray());

        if ($application->employee->user) {
            Notifier::send($application->employee->user, new LeaveRejectedNotification($application));
        }

        return back()->with('success', "Leave application rejected for {$application->employee->full_name}.");
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Working days (Mon–Fri) between two dates, inclusive.
     */
    private function workingDays(Carbon $from, Carbon $to): float
    {
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    private function balanceFor(Employee $employee, LeaveType $type): float
    {
        return $employee->leaveBalanceFor($type);
    }

    private function debitLedger(LeaveApplication $application, string $remarks): void
    {
        $balance = $this->balanceFor($application->employee, $application->leaveType);

        LeaveCreditLedger::create([
            'employee_id' => $application->employee_id,
            'leave_type_id' => $application->leave_type_id,
            'transaction_date' => $application->date_from,
            'movement' => 'used',
            'credit' => 0,
            'debit' => $application->days_applied,
            'balance_after' => $balance - (float) $application->days_applied,
            'source_id' => $application->id,
            'source_type' => LeaveApplication::class,
            'remarks' => $remarks,
            'created_by' => auth()->id(),
        ]);
    }

    private function fmt(float $value): string
    {
        return number_format($value, $value == (int) $value ? 0 : 2);
    }
}
