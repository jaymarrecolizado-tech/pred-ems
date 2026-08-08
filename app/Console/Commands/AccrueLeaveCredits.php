<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Accrue monthly leave credits (VL/SL = 1.25 days/month) for employees with
 * leave entitlement (Permanent, Temporary, Casual, Co-Terminus — per the
 * employment_types.has_leave_credits flag).
 *
 * Idempotent: for each employee + accrual leave type, it accrues every month
 * from their original appointment month up to the current month that does not
 * already have an `accrual` ledger entry.
 */
class AccrueLeaveCredits extends Command
{
    protected $signature = 'leave:accrue {--as-of= : Accrue as of this month (YYYY-MM), defaults to now}';

    protected $description = 'Accrue monthly VL/SL leave credits (1.25 days/month) for leave-entitled employees';

    public function handle(): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse($this->option('as-of') . '-01')->endOfMonth()
            : now()->endOfMonth();

        $entitled = \App\Models\EmploymentType::where('has_leave_credits', true)->pluck('id');
        $accrualTypes = LeaveType::query()
            ->where('accrual_per_month', '>', 0)
            ->orderBy('code')
            ->get();

        if ($accrualTypes->isEmpty()) {
            $this->warn('No leave types with accrual_per_month > 0 configured.');

            return self::SUCCESS;
        }

        $employees = Employee::query()
            ->whereIn('employment_type_id', $entitled)
            ->where('status', 'active')
            ->whereNotNull('date_original_appointment')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $start = $employee->date_original_appointment->copy()->startOfMonth();

            foreach ($accrualTypes as $type) {
                // Months of service from appointment month through the as-of month.
                $months = max(0, $start->diffInMonths($asOf->copy()->startOfMonth())) + 1;

                // Idempotency keyed on the actual month of each accrual entry, so
                // correcting an appointment date or deleting a stray row never
                // produces duplicate (or skipped) months.
                $existingMonths = LeaveCreditLedger::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->where('movement', 'accrual')
                    ->get(['transaction_date'])
                    ->map(fn ($row) => $row->transaction_date->format('Y-m'))
                    ->flip();

                $balance = (float) LeaveCreditLedger::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $type->id)
                    ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
                    ->value('balance');

                $createdHere = 0;
                for ($i = 0; $i < $months; $i++) {
                    $transactionDate = $start->copy()->addMonths($i)->endOfMonth();
                    $monthKey = $transactionDate->format('Y-m');

                    if (isset($existingMonths[$monthKey])) {
                        continue;
                    }

                    $balance += (float) $type->accrual_per_month;

                    LeaveCreditLedger::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'transaction_date' => $transactionDate->toDateString(),
                        'movement' => 'accrual',
                        'credit' => $type->accrual_per_month,
                        'debit' => 0,
                        'balance_after' => $balance,
                        'remarks' => 'Monthly accrual (' . $transactionDate->format('F Y') . ')',
                    ]);
                    $created++;
                    $createdHere++;
                }

                if ($createdHere === 0) {
                    $skipped++;
                }
            }
        }

        $this->info("Accrued {$created} leave credit entr" . ($created === 1 ? 'y' : 'ies')
            . " for {$employees->count()} employee(s) as of {$asOf->format('F Y')} "
            . "({$skipped} employee/type combos already up to date).");

        return self::SUCCESS;
    }
}
