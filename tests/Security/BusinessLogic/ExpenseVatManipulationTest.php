<?php

namespace Tests\Security\BusinessLogic;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Notification;
use Tests\Security\SecurityTestCase;

/**
 * Verifies that VAT amount cannot be manipulated via HTTP parameters.
 *
 * Even if a client submits an arbitrary vat_amount, the server must always
 * compute it server-side from the stored VatRate. Accepting a client-supplied
 * value could be exploited to under-report or over-report tax liabilities.
 */
class ExpenseVatManipulationTest extends SecurityTestCase
{
    private VatRate $vatRateA;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Create a VAT rate owned by Org A (bypassing global scope in setUp)
        $this->vatRateA = VatRate::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Standard',
            'rate' => '8.10',
            'code' => 'NORMAL',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST /expenses — store
    // ──────────────────────────────────────────────────────────────

    public function test_store_ignores_client_vat_amount_and_computes_from_rate(): void
    {
        // 100.00 × 8.10% = 8.10 (server-computed)
        $expectedVat = '8.10';
        $attackerVat = '99999.99';

        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/expenses', [
                'category' => 'office',
                'description' => 'Security test expense',
                'amount' => '100.00',
                'vat_amount' => $attackerVat,
                'vat_rate_id' => $this->vatRateA->id,
                'date' => now()->toDateString(),
                'currency' => 'CHF',
            ]);

        $expense = Expense::withoutGlobalScopes()
            ->where('organization_id', $this->orgA->id)
            ->where('category', 'office')
            ->where('description', 'Security test expense')
            ->latest()
            ->first();

        $this->assertNotNull($expense, 'Expense should have been created.');
        $this->assertSame($expectedVat, $expense->vat_amount,
            'vat_amount must be computed from VatRate, not taken from client input.');
        $this->assertNotSame($attackerVat, $expense->vat_amount);
    }

    public function test_store_without_vat_rate_id_forces_vat_amount_to_zero(): void
    {
        // Client tries to record VAT without a valid rate — server must reject it
        $attackerVat = '50.00';

        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post('/expenses', [
                'category' => 'travel',
                'description' => 'No-rate VAT injection attempt',
                'amount' => '250.00',
                'vat_amount' => $attackerVat,
                // intentionally no vat_rate_id
                'date' => now()->toDateString(),
                'currency' => 'CHF',
            ]);

        $expense = Expense::withoutGlobalScopes()
            ->where('organization_id', $this->orgA->id)
            ->where('category', 'travel')
            ->where('description', 'No-rate VAT injection attempt')
            ->latest()
            ->first();

        $this->assertNotNull($expense, 'Expense should have been created.');
        $this->assertSame('0.00', $expense->vat_amount,
            'Without a vat_rate_id, vat_amount must be zero regardless of client input.');
    }

    // ──────────────────────────────────────────────────────────────
    //  PUT /expenses/{expense} — update
    // ──────────────────────────────────────────────────────────────

    public function test_update_recomputes_vat_when_amount_changes(): void
    {
        // Seed an expense for orgA
        app(CurrentOrganization::class)->set($this->orgA);
        $expense = Expense::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->ownerA->id,
            'category' => 'consulting',
            'description' => 'Original expense',
            'amount' => '100.00',
            'vat_amount' => '8.10', // 100.00 × 8.10%
            'vat_rate_id' => $this->vatRateA->id,
            'date' => now()->toDateString(),
            'currency' => 'CHF',
            'status' => 'pending',
        ]);

        // Update amount to 200.00 but submit stale vat_amount of 8.10
        // Expected server-recomputed value: 200.00 × 8.10% = 16.20
        $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->put("/expenses/{$expense->id}", [
                'category' => 'consulting',
                'description' => 'Original expense',
                'amount' => '200.00',
                'vat_amount' => '8.10', // stale / attacker-supplied value
                'vat_rate_id' => $this->vatRateA->id,
                'date' => now()->toDateString(),
                'currency' => 'CHF',
            ]);

        $expense->refresh();

        $this->assertSame('16.20', $expense->vat_amount,
            'vat_amount must be recomputed from the new amount and VatRate, not from client input.');
    }
}
