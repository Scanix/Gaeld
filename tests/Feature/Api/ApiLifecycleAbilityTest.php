<?php

namespace Tests\Feature\Api;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

class ApiLifecycleAbilityTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
    }

    public function test_invoice_finalize_requires_the_invoice_finalize_ability(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => InvoiceStatus::Draft,
        ]);
        $token = $this->createTokenWithAbilities(['invoicing.create']);

        $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/finalize")
            ->assertForbidden();
    }

    public function test_invoice_payment_requires_the_record_payment_ability(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => InvoiceStatus::Sent,
        ]);
        $token = $this->createTokenWithAbilities(['invoicing.view']);

        $this->withToken($token)
            ->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
                'amount' => '10.00',
                'payment_date' => '2026-08-21',
                'payment_method' => 'bank',
            ])
            ->assertForbidden();
    }

    public function test_expense_approval_requires_the_expense_approve_ability(): void
    {
        $expense = Expense::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => ExpenseStatus::Pending,
        ]);
        $token = $this->createTokenWithAbilities(['expenses.edit']);

        $this->withToken($token)
            ->postJson("/api/v1/expenses/{$expense->id}/approve")
            ->assertForbidden();
    }

    /** @param list<string> $abilities */
    private function createTokenWithAbilities(array $abilities): string
    {
        $result = $this->ownerA->createToken('lifecycle-ability-test', $abilities);
        $result->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);

        return $result->plainTextToken;
    }
}
