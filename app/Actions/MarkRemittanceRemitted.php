<?php

namespace App\Actions;

use App\Models\Remittance;
use App\Support\Audit;

/**
 * Mark a remittance as remitted, recording the OR/batch reference number.
 */
class MarkRemittanceRemitted
{
    public function handle(Remittance $remittance, array $validated): ActionResult
    {
        $remittance->update([
            'status' => Remittance::STATUS_REMITTED,
            'reference_no' => $validated['reference_no'] ?? null,
            'remarks' => $validated['remarks'] ?? $remittance->remarks,
            'remitted_at' => now(),
        ]);

        Audit::record('remitted', $remittance, [], $remittance->only(['status', 'reference_no']));

        return ActionResult::ok("{$remittance->agency} remittance marked as remitted.");
    }
}
