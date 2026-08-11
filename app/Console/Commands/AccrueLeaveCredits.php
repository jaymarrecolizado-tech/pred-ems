<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Leave credit maintenance for employees with leave entitlement (Permanent,
 * Temporary, Casual, Co-Terminus — per the employment_types.has_leave_credits
 * flag):
 *
 *  1. Monthly accruals — VL/SL at 1.25 days/month, idempotent per month.
 *  2. Annual flat grants — SLP 3 days/year (CSC rules: non-cumulative), granted
 *     on January 1 with any unused balance from the previous year reset first.
 *
 * Idempotent: each accrual/grant is keyed on its actual month/year, so
 * correcting an appointment date or deleting a stray row never produces
 * duplicates.
 */
class AccrueLeaveCredits extends Command
{
    protected $signature = 'leave:accrue {--as-of= : Accrue as of this month (YYYY-MM), defaults to now} {--employee= : Restrict to a single employee id}';

    protected $description = 'Accrue monthly VL/SL credits and annual SLP grants for leave-entitled employees';

    public function handle(): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse($this->option('as-of').'-01')->endOfMonth()
            : now()->endOfMonth();

        $entitled = EmploymentType::where('has_leave_credits', true)->pluck('id');
        $accrualTypes = LeaveType::query()
            ->where('accrual_per_month', '>', 0)
            ->orderBy('code')
            ->get();
        $grantTypes = LeaveType::query()
            ->where('annual_grant', true)
            ->where('annual_max_credit', '>', 0)
            ->orderBy('code')
            ->get();

        if ($accrualTypes->isEmpty() && $grantTypes->isEmpty()) {
            $this->warn('No leave types configured for monthly accrual or annual grant.');

            return self::SUCCESS;
        }

        $employees = Employee::query()
            ->when($this->option('employee'), fn ($q, $id) => $q->whereKey($id))
            ->whereIn('employment_type_id', $entitled)
            ->where('status', 'active')
            ->whereNotNull('date_original_appointment')
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('No matching employees.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $start = $employee->date_original_appointment->copy()->startOfMonth();

            // -- 1. Monthly accruals (VL/SL) ------------------------------
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
                        'remarks' => 'Monthly accrual ('.$transactionDate->format('F Y').')',
                    ]);
                    $created++;
                    $createdHere++;
                }

                if ($createdHere === 0) {
                    $skipped++;
                }
            }

            // -- 2. Annual flat grants (e.g. SLP — non-cumulative) --------
            foreach ($grantTypes as $type) {
                $startYear = $employee->date_original_appointment->year;
                $endYear = $asOf->year;

                for ($year = $startYear; $year <= $endYear; $year++) {
                    $grantDate = Carbon::create($year, 1, 1);

                    $granted = LeaveCreditLedger::query()
                        ->where('employee_id', $employee->id)
                        ->where('leave_type_id', $type->id)
                        ->where('movement', 'grant')
                        ->whereYear('transaction_date', $year)
                        ->exists();

                    if ($granted) {
                        continue;
                    }

                    // Non-cumulative: clear any leftover from the prior year
                    // before granting the fresh annual maximum.
                    if ($year > $startYear) {
                        $leftover = (float) LeaveCreditLedger::query()
                            ->where('employee_id', $employee->id)
                            ->where('leave_type_id', $type->id)
                            ->where('transaction_date', '<=', Carbon::create($year - 1, 12, 31))
                            ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
                            ->value('balance');

                        if ($leftover > 0) {
                            LeaveCreditLedger::create([
                                'employee_id' => $employee->id,
                                'leave_type_id' => $type->id,
                                'transaction_date' => $grantDate->toDateString(),
                                'movement' => 'reset',
                                'credit' => 0,
                                'debit' => $leftover,
                                'balance_after' => 0,
                                'remarks' => 'Non-cumulative reset (unused '.($year - 1).' balance)',
                            ]);
                            $created++;
                        }
                    }

                    $balance = (float) LeaveCreditLedger::query()
                        ->where('employee_id', $employee->id)
                        ->where('leave_type_id', $type->id)
                        ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
                        ->value('balance');

                    LeaveCreditLedger::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $type->id,
                        'transaction_date' => $grantDate->toDateString(),
                        'movement' => 'grant',
                        'credit' => $type->annual_max_credit,
                        'debit' => 0,
                        'balance_after' => $balance + (float) $type->annual_max_credit,
                        'remarks' => 'Annual '.$type->code.' grant ('.$year.')',
                    ]);
                    $created++;
                }
            }
        }

        $this->info('Recorded '.$created.' leave credit entr'.($created === 1 ? 'y' : 'ies')
            .' for '.$employees->count().' employee(s) as of '.$asOf->format('F Y').' '
            .'('.$skipped.' employee/type combos already up to date).');

        return self::SUCCESS;
    }
}
