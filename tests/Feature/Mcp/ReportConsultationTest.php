<?php

namespace Tests\Feature\Mcp;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\ReportingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;

class ReportConsultationTest extends McpTestCase
{
    public function test_report_discovery_requires_explicit_scopes_and_annotations_are_read_only(): void
    {
        $token = $this->mcpToken(['reporting.view']);
        $tools = $this->rpc($token, 'tools/list')->assertOk()->json('result.tools');
        $this->assertEqualsCanonicalizing(['get_profit_and_loss', 'get_balance_sheet'], array_column($tools, 'name'));
        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint']);
        }
        $token = $this->mcpToken(['reporting.view', 'invoicing.view', 'expenses.view']);
        $this->rpc($token, 'tools/list')->assertJsonCount(7, 'result.tools');
    }

    public function test_profit_and_loss_uses_posted_ledger_dates_and_matches_the_shared_service(): void
    {
        $this->ledgerFixture($this->orgA->id);
        $this->ledgerFixture($this->orgB->id);
        app(CurrentOrganization::class)->set($this->orgA);
        $expected = app(ReportingService::class)->profitAndLoss($this->orgA->id, '2026-11-01', '2026-11-30');
        $before = DB::table('journal_entries')->orderBy('id')->get()->toJson();
        $response = $this->callTool($this->mcpToken(['reporting.view']), 'get_profit_and_loss', ['from' => '2026-11-01', 'to' => '2026-11-30']);
        $response->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.currency', 'CHF')
            ->assertJsonPath('result.structuredContent.organization.id', $this->orgA->id)
            ->assertJsonPath('result.structuredContent.data.total_revenue', '100.10')
            ->assertJsonPath('result.structuredContent.data.total_expenses', '20.05')
            ->assertJsonPath('result.structuredContent.data.net_profit', '80.05');
        $this->assertSame($expected['net_profit'], $response->json('result.structuredContent.data.net_profit'));
        $this->assertSame($before, DB::table('journal_entries')->orderBy('id')->get()->toJson());
    }

    public function test_balance_sheet_matches_the_shared_service_and_includes_fiscal_year_result(): void
    {
        $this->ledgerFixture($this->orgA->id);
        $response = $this->callTool($this->mcpToken(['reporting.view']), 'get_balance_sheet', ['as_of_date' => '2026-11-30']);
        $response->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.assets.total', '80.05')
            ->assertJsonPath('result.structuredContent.data.equity.total', '80.05')
            ->assertJsonPath('result.structuredContent.data.liabilities.total', '0.00');
        $this->assertSame(app(ReportingService::class)->balanceSheet($this->orgA->id, '2026-11-30')['equity'], $response->json('result.structuredContent.data.equity'));
    }

    public function test_fiscal_year_selection_respects_custom_periods_and_organization_boundaries(): void
    {
        $year = FiscalYear::factory()->create(['organization_id' => $this->orgA->id, 'start_date' => '2025-10-01', 'end_date' => '2026-12-31']);
        $foreign = FiscalYear::factory()->create(['organization_id' => $this->orgB->id]);
        $token = $this->mcpToken(['reporting.view']);
        $this->callTool($token, 'get_profit_and_loss', ['fiscal_year_id' => $year->id])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.period.from', '2025-10-01')
            ->assertJsonPath('result.structuredContent.data.period.to', '2026-12-31');
        $this->callTool($token, 'get_balance_sheet', ['fiscal_year_id' => $year->id])
            ->assertJsonPath('result.structuredContent.data.as_of_date', '2026-12-31');
        foreach (['get_profit_and_loss', 'get_balance_sheet'] as $tool) {
            $this->callTool($token, $tool, ['fiscal_year_id' => $foreign->id])->assertJsonPath('result.isError', true);
        }
    }

    public function test_aging_deducts_partial_payments_and_separates_currencies_and_tenants(): void
    {
        $customer = Contact::factory()->create(['organization_id' => $this->orgA->id]);
        $invoice = Invoice::factory()->sent()->create(['organization_id' => $this->orgA->id, 'customer_id' => $customer->id, 'issue_date' => '2026-10-01', 'due_date' => '2026-11-01', 'total' => '108.10']);
        $invoice->payments()->create(['organization_id' => $this->orgA->id, 'amount' => '30.00', 'payment_date' => '2026-11-05', 'payment_method' => 'bank']);
        Invoice::factory()->sent()->create(['organization_id' => $this->orgA->id, 'currency' => 'EUR', 'issue_date' => '2026-10-01', 'due_date' => '2026-11-01', 'total' => '90.00']);
        Invoice::factory()->sent()->create(['organization_id' => $this->orgB->id, 'issue_date' => '2026-10-01', 'due_date' => '2026-11-01']);
        $token = $this->mcpToken(['reporting.view', 'invoicing.view']);
        $arguments = ['type' => 'receivables', 'as_of_date' => '2026-11-12'];
        $this->callTool($token, 'get_aging_report', $arguments)->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.grand_total', '78.10')
            ->assertJsonPath('result.structuredContent.data.items.0.id', $invoice->id)
            ->assertJsonPath('result.structuredContent.data.brackets.1_30.total', '78.10')
            ->assertJsonPath('result.structuredContent.data.pagination.total', 1);
        $this->callTool($token, 'get_aging_report', [...$arguments, 'currency' => 'EUR'])
            ->assertJsonPath('result.structuredContent.currency', 'EUR')
            ->assertJsonPath('result.structuredContent.data.grand_total', '90.00');
        $this->callTool($token, 'get_aging_report', $arguments)->assertJsonPath('result.structuredContent.data.grand_total', '78.10');
    }

    public function test_payables_include_vat_and_full_totals_are_independent_of_pagination(): void
    {
        Expense::factory()->count(3)->create(['organization_id' => $this->orgA->id, 'date' => '2026-11-01', 'amount' => '100.00', 'vat_amount' => '8.10']);
        Expense::factory()->create(['organization_id' => $this->orgA->id, 'date' => '2026-12-01']);
        Expense::factory()->create(['organization_id' => $this->orgA->id, 'date' => '2026-11-01', 'status' => 'posted']);
        $token = $this->mcpToken(['reporting.view', 'expenses.view']);
        $args = ['type' => 'payables', 'as_of_date' => '2026-11-12', 'per_page' => 2];
        $this->callTool($token, 'get_aging_report', $args)->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.grand_total', '324.30')
            ->assertJsonCount(2, 'result.structuredContent.data.items')
            ->assertJsonPath('result.structuredContent.data.pagination.has_more', true);
        $this->callTool($token, 'get_aging_report', [...$args, 'page' => 2])
            ->assertJsonPath('result.structuredContent.data.grand_total', '324.30')
            ->assertJsonCount(1, 'result.structuredContent.data.items')
            ->assertJsonPath('result.structuredContent.data.pagination.has_more', false);
    }

    public function test_own_expense_tokens_cannot_read_organization_payables_even_for_an_owner(): void
    {
        $token = $this->mcpToken(['reporting.view', 'invoicing.view', 'expenses.view-own']);
        $this->callTool($token, 'get_aging_report', ['type' => 'payables', 'as_of_date' => '2026-11-12'])
            ->assertJsonPath('result.isError', true)->assertJsonMissingPath('result.structuredContent');
        $employee = $this->createUserInOrg($this->orgA, 'employee');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);
        $employee->givePermissionTo(['reporting.view', 'accounting.view']);
        $this->callTool($this->mcpToken(['reporting.view', 'expenses.view'], $employee), 'get_aging_report', ['type' => 'payables', 'as_of_date' => '2026-11-12'])
            ->assertJsonPath('error.code', -32602);
    }

    public function test_report_permissions_and_document_scopes_are_independent(): void
    {
        $token = $this->mcpToken(['invoicing.view', 'expenses.view']);
        $this->callTool($token, 'get_profit_and_loss', ['from' => '2026-11-01', 'to' => '2026-11-12'])->assertJsonPath('error.code', -32602);
        $token = $this->mcpToken(['reporting.view']);
        $this->callTool($token, 'get_aging_report', ['type' => 'receivables', 'as_of_date' => '2026-11-12'])->assertJsonPath('error.code', -32602);
        $user = $this->createUserInOrg($this->orgA, 'employee');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);
        $user->givePermissionTo('accounting.view');
        $this->callTool($this->mcpToken(['reporting.view'], $user), 'get_balance_sheet', ['as_of_date' => '2026-11-12'])->assertJsonPath('error.code', -32602);
    }

    public function test_an_empty_report_is_successful_and_no_document_without_due_date_crashes(): void
    {
        $token = $this->mcpToken(['reporting.view', 'invoicing.view']);
        $response = $this->callTool($token, 'get_profit_and_loss', ['from' => '2026-11-01', 'to' => '2026-11-12']);
        $this->assertFalse($response->json('result.isError'), $response->getContent());
        $response->assertJsonPath('result.structuredContent.data.net_profit', '0.00');
        Invoice::factory()->sent()->create(['organization_id' => $this->orgA->id, 'issue_date' => '2026-11-01', 'due_date' => null]);
        $this->callTool($token, 'get_aging_report', ['type' => 'receivables', 'as_of_date' => '2026-11-12'])->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.data.brackets.current.count', 1);
    }

    public function test_revoking_a_users_report_permission_takes_effect_immediately(): void
    {
        $user = $this->createUserInOrg($this->orgA, 'employee');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);
        $user->givePermissionTo(['reporting.view', 'accounting.view']);
        $token = $this->mcpToken(['reporting.view'], $user);
        $this->callTool($token, 'get_balance_sheet', ['as_of_date' => '2026-11-12'])->assertJsonPath('result.isError', false);
        $user->revokePermissionTo('reporting.view');
        $this->callTool($token, 'get_balance_sheet', ['as_of_date' => '2026-11-12'])->assertJsonPath('error.code', -32602);
    }

    #[DataProvider('invalidReportArguments')]
    public function test_invalid_report_arguments_are_rejected(string $tool, array $arguments): void
    {
        $this->callTool($this->mcpToken(['reporting.view', 'invoicing.view', 'expenses.view']), $tool, $arguments)
            ->assertJsonPath('result.isError', true)->assertJsonMissingPath('result.structuredContent');
    }

    public static function invalidReportArguments(): array
    {
        return [
            ['get_profit_and_loss', []],
            ['get_profit_and_loss', ['from' => '2026-11-12', 'to' => '2026-01-01']],
            ['get_profit_and_loss', ['from' => '2020-01-01', 'to' => '2026-11-12']],
            ['get_balance_sheet', ['as_of_date' => '2026-02-30']],
            ['get_balance_sheet', ['as_of_date' => '2026-11-12', 'fiscal_year_id' => (string) Str::uuid()]],
            ['get_aging_report', ['type' => 'unknown', 'as_of_date' => '2026-11-12']],
            ['get_aging_report', ['type' => 'payables', 'as_of_date' => '2026-11-12', 'per_page' => 51]],
            ['get_aging_report', ['type' => 'receivables', 'as_of_date' => '2026-11-12', 'currency' => 'CHFx']],
            ['get_balance_sheet', ['as_of_date' => '2026-11-12', 'organization_id' => (string) Str::uuid()]],
        ];
    }

    private function ledgerFixture(string $organizationId): void
    {
        $bank = Account::factory()->asset()->create(['organization_id' => $organizationId, 'code' => '1020']);
        $revenue = Account::factory()->revenue()->create(['organization_id' => $organizationId, 'code' => '3000']);
        $expense = Account::factory()->expense()->create(['organization_id' => $organizationId, 'code' => '6500']);
        foreach ([['2026-11-05', true, $bank, $revenue, '100.10'], ['2026-11-06', true, $expense, $bank, '20.05'], ['2026-11-07', false, $bank, $revenue, '50.00'], ['2026-12-01', true, $bank, $revenue, '200.00']] as [$date, $posted, $debit, $credit, $amount]) {
            $entry = JournalEntry::create(['organization_id' => $organizationId, 'date' => $date, 'reference' => (string) Str::uuid(), 'is_posted' => false]);
            $entry->lines()->createMany([
                ['account_id' => $debit->id, 'debit' => $amount, 'credit' => '0.00'],
                ['account_id' => $credit->id, 'debit' => '0.00', 'credit' => $amount],
            ]);
            if ($posted) {
                $entry->update(['is_posted' => true]);
            }
        }
    }
}
