<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Remittance;
use App\Models\User;
use Tests\TestCase;

class PayrollAdjustmentTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        // Dev-seeded employee account (also exists in the real-directory seed).
        return User::where('email', 'juan@dictro2.gov.ph')->firstOrFail();
    }

    /**
     * Create a scratch draft period with generated items; returns [period, firstItem].
     * Dates are derived from the per-test suffix so runs never collide with a
     * leftover period covering the same range.
     */
    private function draftPeriodWithItems(string $suffix): array
    {
        $admin = $this->admin();
        $name = "Adj Test {$suffix}";
        $day = (int) substr($suffix, -3) ?: 1;
        $from = now()->subMonths(2)->startOfMonth()->addDays($day % 20)->toDateString();
        $to = now()->subMonths(2)->startOfMonth()->addDays(($day % 20) + 5)->toDateString();

        $this->actingAs($admin)->post('/payroll', [
            'period_from' => $from,
            'period_to' => $to,
            'payroll_date' => $to,
            'remarks' => $name,
        ])->assertRedirect();

        $period = PayrollPeriod::where('remarks', $name)->firstOrFail();
        $this->actingAs($admin)->post("/payroll/{$period->id}/generate")->assertRedirect();
        $period->refresh();

        $item = $period->items()->with('employee')->first();
        $this->assertNotNull($item, 'Generated items expected');

        return [$period, $item];
    }

    public function test_adjust_page_renders_for_draft_item(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p1'.substr((string) time(), -5));

        try {
            $this->actingAs($this->admin())
                ->get("/payroll/items/{$item->id}/adjust")
                ->assertOk()
                ->assertSee('Honoraria')
                ->assertSee('Leave Without Pay');
        } finally {
            $period->delete();
        }
    }

    public function test_adjustments_recompute_item_and_update_totals(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p2'.substr((string) time(), -5));

        try {
            $beforeGross = (float) $item->gross_amount;
            $beforeNet = (float) $item->net_amount;

            $this->actingAs($this->admin())->post("/payroll/items/{$item->id}/adjust", [
                'honoraria' => 2000.00,
                'overtime_pay' => 1500.00,
                'lwop' => 1000.00,
            ])->assertRedirect();

            $item->refresh();

            // Income lines added
            $this->assertEquals(2000.00, (float) $item->honoraria);
            $this->assertEquals(1500.00, (float) $item->overtime_pay);
            // LWOP deducted
            $this->assertEquals(1000.00, (float) $item->lwop_deduction);

            // Gross grew by the income additions
            $this->assertEqualsWithDelta($beforeGross + 3500.00, (float) $item->gross_amount, 0.01);

            // Net moves by +3,500 income − 1,000 LWOP − extra withholding on the
            // taxable additions (BIR recomputed) — so it must be strictly higher,
            // and by less than the full 2,500 because tax grows too.
            $this->assertGreaterThan($beforeNet, (float) $item->net_amount);
            $this->assertLessThan($beforeNet + 2500.00, (float) $item->net_amount);

            // Trace re-persisted and includes the manual lines
            $trace = $item->computation_json;
            $this->assertNotEmpty($trace);
            $labels = collect($trace['lines'] ?? [])->pluck('label');
            $this->assertTrue($labels->contains('Honoraria'));
            $this->assertTrue($labels->contains('Leave without pay (LWOP)'));

            // Audited
            $this->assertTrue(AuditLog::where('model_type', PayrollItem::class)
                ->where('model_id', $item->id)
                ->where('action', 'adjusted')
                ->exists());
        } finally {
            $period->delete();
        }
    }

    public function test_recompute_preserves_manual_adjustments(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p3'.substr((string) time(), -5));

        try {
            $this->actingAs($this->admin())->post("/payroll/items/{$item->id}/adjust", [
                'honoraria' => 1000.00,
                'other_deductions' => 250.00,
            ])->assertRedirect();

            $item->refresh();
            $grossWithAdj = (float) $item->gross_amount;

            // Recompute the whole period — adjustments must survive
            $this->actingAs($this->admin())->post("/payroll/{$period->id}/generate")->assertRedirect();

            $period->refresh();
            $regenerated = PayrollItem::where('payroll_period_id', $period->id)
                ->where('employee_id', $item->employee_id)
                ->firstOrFail();

            $this->assertEquals(1000.00, (float) $regenerated->honoraria);
            $this->assertEquals(250.00, (float) $regenerated->other_deductions);
            $this->assertEqualsWithDelta($grossWithAdj, (float) $regenerated->gross_amount, 0.01);
        } finally {
            $period->delete();
        }
    }

    public function test_validation_rejects_negative_amounts(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p4'.substr((string) time(), -5));

        try {
            $this->actingAs($this->admin())
                ->post("/payroll/items/{$item->id}/adjust", [
                    'honoraria' => -500,
                ])
                ->assertSessionHasErrors('honoraria');

            $item->refresh();
            $this->assertEquals(0, (float) $item->honoraria);
        } finally {
            $period->delete();
        }
    }

    public function test_locked_period_cannot_be_adjusted(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p5'.substr((string) time(), -5));

        try {
            $this->actingAs($this->admin())->post("/payroll/{$period->id}/finalize")->assertRedirect();
            $period->refresh();
            $this->assertEquals(PayrollPeriod::STATUS_FINALIZED, $period->status);

            $this->actingAs($this->admin())
                ->get("/payroll/items/{$item->id}/adjust")
                ->assertStatus(409);

            $this->actingAs($this->admin())
                ->post("/payroll/items/{$item->id}/adjust", ['honoraria' => 500])
                ->assertStatus(409);
        } finally {
            if (isset($period)) {
                Remittance::where('period_from', $period->period_from)->where('period_to', $period->period_to)->delete();
                $period->delete();
            }
        }
    }

    public function test_employee_role_cannot_access_adjustment_ui(): void
    {
        [$period, $item] = $this->draftPeriodWithItems('p6'.substr((string) time(), -5));

        try {
            $this->actingAs($this->employeeUser())
                ->get("/payroll/items/{$item->id}/adjust")
                ->assertStatus(403);
        } finally {
            $period->delete();
        }
    }
}
