<?php

namespace App\Http\Controllers;

use App\Actions\ApproveLeave;
use App\Actions\CancelLeaveApplication;
use App\Actions\FileLeaveApplication;
use App\Actions\RejectLeave;
use App\Http\Requests\RejectLeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Support\DocumentIssuer;
use App\Support\Search;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LeaveController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Self-service */
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

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $employee = auth()->user()->employee;
        abort_if(! $employee, 403, 'No 201-file record is linked to your account. Contact HR.');

        $result = (new FileLeaveApplication)->handle($employee, $request->validated());

        if (! $result->success) {
            return back()->with('error', $result->message)->withInput();
        }

        return redirect()
            ->route('leave.index')
            ->with('success', $result->message);
    }

    /**
     * Employee cancels their own pending application.
     */
    public function cancel(LeaveApplication $application): RedirectResponse
    {
        abort_if($application->employee_id !== auth()->user()->employee?->id, 403);
        abort_unless($application->isPending(), 403, 'Only pending applications can be cancelled.');

        $result = (new CancelLeaveApplication)->handle($application);

        return redirect()->route('leave.index')->with('success', $result->message);
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

        $filename = 'CSC_Form_6_'.str_replace([' ', '.'], '_', $application->employee->full_name).'.pdf';

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
    /*  Approvals (admin / HR) */
    /* ------------------------------------------------------------------ */

    public function approvals(Request $request): View
    {
        $applications = LeaveApplication::query()
            ->with(['employee', 'leaveType'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->string('search'));
                $q->whereHas('employee', function ($inner) use ($search) {
                    $inner->where('first_name', 'like', Search::contains($search))
                        ->orWhere('last_name', 'like', Search::contains($search))
                        ->orWhere('employee_number', 'like', Search::contains($search));
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
     * Approve → deduct the leave credit from the append-only ledger. The
     * business rules (balance re-check, ledger debit, audit, notification)
     * live in App\Actions\ApproveLeave.
     */
    public function approve(LeaveApplication $application): RedirectResponse
    {
        abort_unless($application->isPending(), 403, 'This application is no longer pending.');

        $result = (new ApproveLeave)->handle($application);

        if (! $result->success) {
            return back()->with('error', $result->message);
        }

        return back()->with('success', $result->message);
    }

    public function reject(RejectLeaveRequest $request, LeaveApplication $application): RedirectResponse
    {
        abort_unless($application->isPending(), 403, 'This application is no longer pending.');

        $result = (new RejectLeave)->handle($application, $request->validated()['denial_reason']);

        return back()->with('success', $result->message);
    }
}
