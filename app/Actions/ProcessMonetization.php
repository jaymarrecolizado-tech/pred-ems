<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveMonetization;
use App\Models\LeaveType;
use App\Notifications\LeaveMonetizedNotification;
use App\Support\Audit;
use App\Support\Format;
use App\Support\Notifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Process a VL monetization (CSC Omnibus Rules on Leave, MC 41 s. 1998 as
 * amended): validate the rules against the live ledger — ≥ 10 VL days
 * accumulated, retain ≥ 5 days, max 30 days per calendar year — then
 * atomically create the record, debit the ledger and mint the MO-YYYY-NNNN
 * reference.
 */
class ProcessMonetization
{
    public function handle(array $validated): ActionResult
    {
        $vl = LeaveType::where('code', 'VL')->firstOrFail();
        $employee = Employee::with('employmentType')->findOrFail($validated['employee_id']);

        if (! $employee->employmentType?->has_leave_credits) {
            return ActionResult::fail('This employee has no leave entitlement.');
        }
        if ((float) $employee->monthly_salary <= 0) {
            return ActionResult::fail('No monthly salary is on file for this employee.');
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
            $errors[] = 'Eligibility requires at least 10 VL days accumulated (balance: '.Format::days($balance).').';
        }
        if ($days > $balance - 5) {
            $errors[] = 'Cannot monetize more than '.Format::days(max(0, $balance - 5))
                .' day(s) so the employee retains at least 5 VL days.';
        }
        if ($usedThisYear + $days > 30) {
            $errors[] = 'Only 30 VL days may be monetized per year ('.Format::days($usedThisYear)
                ." already monetized in {$year}).";
        }

        if ($errors) {
            return ActionResult::fail(implode(' ', $errors));
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
                    'remarks' => "VL monetization ({$year}) — ref ".$record->reference_no,
                    'created_by' => auth()->id(),
                ]);

                return $record;
            });
        } catch (QueryException $e) {
            // A concurrent request raced the same reference number.
            if ((int) $e->errorInfo[1] === 1062) {
                return ActionResult::fail('Please retry — the reference number was just taken by another entry.');
            }

            throw $e;
        }

        Audit::record('created', $monetization, [], $monetization->toArray());

        if ($employee->user) {
            Notifier::send($employee->user, new LeaveMonetizedNotification($monetization));
        }

        return ActionResult::ok(
            'Monetization processed: '.Format::days($days).' day(s) of VL → ₱'
            .number_format($gross, 2).' for '.$employee->full_name.' ('.$monetization->reference_no.').',
            $monetization
        );
    }
}
