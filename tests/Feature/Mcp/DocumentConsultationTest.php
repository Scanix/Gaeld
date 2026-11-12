<?php

namespace Tests\Feature\Mcp;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class DocumentConsultationTest extends McpTestCase
{
    public function test_invoice_lists_are_isolated_and_paginated_with_decimal_amounts(): void
    {
        $customer = Contact::factory()->create(['organization_id' => $this->orgA->id]);
        $invoices = Invoice::factory()->count(3)->create([
            'organization_id' => $this->orgA->id, 'customer_id' => $customer->id, 'issue_date' => '2026-11-01',
        ]);
        Invoice::factory()->create(['organization_id' => $this->orgB->id]);
        $token = $this->mcpToken(['invoicing.view']);
        $first = $this->callTool($token, 'list_invoices', ['per_page' => 2]);
        $first->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonCount(2, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.organization.id', $this->orgA->id)
            ->assertJsonPath('result.structuredContent.pagination.total', 3)
            ->assertJsonPath('result.structuredContent.pagination.has_more', true);
        $this->assertIsString($first->json('result.structuredContent.data.0.total'));
        $this->assertArrayNotHasKey('email', $first->json('result.structuredContent.data.0.customer'));
        $second = $this->callTool($token, 'list_invoices', ['per_page' => 2, 'page' => 2]);
        $second->assertJsonCount(1, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.pagination.has_more', false);
        $ids = [...array_column($first->json('result.structuredContent.data'), 'id'), ...array_column($second->json('result.structuredContent.data'), 'id')];
        $this->assertEqualsCanonicalizing($invoices->modelKeys(), $ids);
    }

    public function test_invoice_filters_apply_to_the_full_result_set(): void
    {
        $customer = Contact::factory()->create(['organization_id' => $this->orgA->id, 'name' => 'Alpine client']);
        $attributes = ['organization_id' => $this->orgA->id, 'customer_id' => $customer->id, 'issue_date' => '2026-11-05', 'due_date' => '2026-11-10'];
        $matching = Invoice::factory()->sent()->create($attributes);
        Invoice::factory()->sent()->create([...$attributes, 'issue_date' => '2026-10-05']);
        Invoice::factory()->create($attributes);
        Invoice::factory()->sent()->create([...$attributes, 'due_date' => '2026-12-10']);
        $this->callTool($this->mcpToken(), 'list_invoices', [
            'from' => '2026-11-01', 'to' => '2026-11-30', 'status' => 'sent',
            'customer_id' => $customer->uuid, 'due_before' => '2026-11-12', 'search' => 'Alpine',
        ])->assertJsonCount(1, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.data.0.id', $matching->id);
    }

    public function test_invoice_details_include_lines_and_partial_payments_without_writing(): void
    {
        $invoice = Invoice::factory()->sent()->create([
            'organization_id' => $this->orgA->id, 'total' => '108.10', 'subtotal' => '100.00', 'vat_amount' => '8.10', 'due_date' => null,
        ]);
        $invoice->lines()->create(['description' => 'Consulting', 'quantity' => '1.00', 'unit_price' => '100.00', 'amount' => '100.00', 'vat_amount' => '8.10']);
        $invoice->payments()->create(['organization_id' => $this->orgA->id, 'amount' => '30.00', 'payment_date' => '2026-11-10', 'payment_method' => PaymentMethod::Bank]);
        $before = $invoice->fresh()->getRawOriginal();
        $this->callTool($this->mcpToken(), 'get_invoice', ['id' => $invoice->id])->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.amount_paid', '30.00')
            ->assertJsonPath('result.structuredContent.data.amount_due', '78.10')
            ->assertJsonPath('result.structuredContent.data.due_date', null)
            ->assertJsonPath('result.structuredContent.data.lines.0.description', 'Consulting')
            ->assertJsonPath('result.structuredContent.data.payments.0.amount', '30.00');
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        $this->assertDatabaseCount('invoice_payments', 1);
    }

    public function test_credit_notes_preserve_sign_and_currency(): void
    {
        Invoice::factory()->create(['organization_id' => $this->orgA->id]);
        $credit = Invoice::factory()->create(['organization_id' => $this->orgA->id, 'type' => 'credit_note', 'total' => '-50.00', 'subtotal' => '-50.00', 'currency' => 'EUR']);
        $this->callTool($this->mcpToken(), 'list_invoices', ['type' => 'credit_note'])
            ->assertJsonCount(1, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.data.0.id', $credit->id)
            ->assertJsonPath('result.structuredContent.data.0.total', '-50.00')
            ->assertJsonPath('result.structuredContent.data.0.currency', 'EUR');
    }

    public function test_it_filters_and_reads_expenses_without_exposing_receipt_paths(): void
    {
        $supplier = Contact::factory()->create(['organization_id' => $this->orgA->id]);
        $expense = Expense::factory()->create([
            'organization_id' => $this->orgA->id, 'supplier_id' => $supplier->id, 'user_id' => $this->ownerA->id,
            'date' => '2026-11-05', 'category' => 'software', 'vendor' => 'Acme', 'description' => 'Monthly subscription', 'amount' => '100.00', 'vat_amount' => '8.10',
        ]);
        Expense::factory()->create(['organization_id' => $this->orgA->id, 'date' => '2026-10-05']);
        Expense::factory()->create(['organization_id' => $this->orgB->id, 'date' => '2026-11-05']);
        $token = $this->mcpToken(['expenses.view']);
        $this->callTool($token, 'list_expenses', [
            'from' => '2026-11-01', 'to' => '2026-11-30', 'category' => 'software', 'supplier_id' => $supplier->uuid, 'status' => 'pending', 'search' => 'Acme',
        ])->assertJsonCount(1, 'result.structuredContent.data')->assertJsonPath('result.structuredContent.data.0.id', $expense->id);
        $before = $expense->fresh()->getRawOriginal();
        $response = $this->callTool($token, 'get_expense', ['id' => $expense->id]);
        $response->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.net_amount', '100.00')
            ->assertJsonPath('result.structuredContent.data.gross_amount', '108.10')
            ->assertJsonPath('result.structuredContent.data.description', 'Monthly subscription');
        $this->assertArrayNotHasKey('receipt_path', $response->json('result.structuredContent.data'));
        $this->assertSame($before, $expense->fresh()->getRawOriginal());
    }

    public function test_own_expense_permission_limits_lists_details_and_counts(): void
    {
        $employee = $this->createUserInOrg($this->orgA, 'employee');
        $own = Expense::factory()->create(['organization_id' => $this->orgA->id, 'user_id' => $employee->id]);
        $other = Expense::factory()->create(['organization_id' => $this->orgA->id, 'user_id' => $this->ownerA->id]);
        $token = $this->mcpToken(['expenses.view'], $employee);
        $this->callTool($token, 'list_expenses')->assertJsonCount(1, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.pagination.total', 1)
            ->assertJsonPath('result.structuredContent.data.0.id', $own->id);
        $this->callTool($token, 'get_expense', ['id' => $own->id])->assertJsonPath('result.isError', false);
        $this->callTool($token, 'get_expense', ['id' => $other->id])->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'Document not found.');
    }

    public function test_an_own_only_token_restricts_even_an_organization_owner(): void
    {
        $own = Expense::factory()->create(['organization_id' => $this->orgA->id, 'user_id' => $this->ownerA->id]);
        $other = Expense::factory()->create(['organization_id' => $this->orgA->id, 'user_id' => $this->ownerB->id]);
        $token = $this->mcpToken(['expenses.view-own']);
        $this->callTool($token, 'list_expenses')->assertJsonCount(1, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.data.0.id', $own->id);
        $this->callTool($token, 'get_expense', ['id' => $other->id])->assertJsonPath('result.isError', true);
    }

    public function test_foreign_and_missing_documents_return_the_same_error(): void
    {
        $invoice = Invoice::factory()->create(['organization_id' => $this->orgB->id]);
        $expense = Expense::factory()->create(['organization_id' => $this->orgB->id]);
        $token = $this->mcpToken();
        foreach (['get_invoice' => $invoice->id, 'get_expense' => $expense->id] as $tool => $id) {
            $foreign = $this->callTool($token, $tool, ['id' => $id]);
            $missing = $this->callTool($token, $tool, ['id' => (string) Str::uuid()]);
            $foreign->assertJsonPath('result.isError', true)->assertJsonPath('result.content.0.text', 'Document not found.');
            $this->assertSame($foreign->json('result'), $missing->json('result'));
        }
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_filters_and_identifiers_are_rejected(string $tool, array $arguments): void
    {
        $this->callTool($this->mcpToken(), $tool, $arguments)->assertOk()->assertJsonPath('result.isError', true);
    }

    public static function invalidArguments(): array
    {
        return [
            ['list_invoices', ['per_page' => 51]], ['list_expenses', ['page' => 0]],
            ['list_invoices', ['from' => '2026-11-30', 'to' => '2026-11-01']],
            ['list_expenses', ['from' => '2026-02-30']], ['list_invoices', ['status' => 'unknown']],
            ['list_expenses', ['sort' => 'receipt_path']], ['list_invoices', ['organization_id' => 'another-organization']],
            ['get_invoice', ['id' => '1']], ['get_expense', []],
        ];
    }

    public function test_empty_results_are_a_successful_page(): void
    {
        $this->callTool($this->mcpToken(), 'list_expenses')->assertJsonPath('result.isError', false)
            ->assertJsonCount(0, 'result.structuredContent.data')
            ->assertJsonPath('result.structuredContent.pagination.total', 0)
            ->assertJsonPath('result.structuredContent.pagination.has_more', false);
    }
}
