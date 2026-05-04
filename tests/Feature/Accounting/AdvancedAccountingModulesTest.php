<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ConsolidationElimination;
use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TaxDeclaration;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;
use Tests\Traits\WithAuthenticatedOrganization;

class AdvancedAccountingModulesTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bankAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();

        config()->set('features.tax_declaration', true);
        config()->set('features.analytical', true);
        config()->set('features.multi_currency', true);
        config()->set('features.consolidation', true);

        app(CurrentOrganization::class)->set($this->organization);

        $this->bankAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank Account CHF',
            'type' => AccountType::Asset->value,
        ]);

        $this->revenueAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $this->expenseAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '4000',
            'name' => 'Expenses',
            'type' => AccountType::Expense->value,
        ]);
    }

    public function test_tax_declaration_flow_creates_shows_and_finalizes(): void
    {
        $this->postJournalEntry('2026-02-01', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00', 'Client payment'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00', 'Revenue'),
        ], 'TD-REV');

        $this->postJournalEntry('2026-02-05', [
            $this->journalLine($this->expenseAccount, '400.00', '0.00', 'Expense'),
            $this->journalLine($this->bankAccount, '0.00', '400.00', 'Bank payment'),
        ], 'TD-EXP');

        $this->actAsOrg()->post('/accounting/tax-declarations', [
            'fiscal_year' => 2026,
            'canton' => 'vd',
        ])->assertRedirect();

        $declaration = TaxDeclaration::query()->firstOrFail();

        $this->assertSame('VD', $declaration->canton);
        $this->assertSame('draft', $declaration->status);
        $this->assertSame(600.0, (float) ($declaration->data['net_result'] ?? 0.0));
        $this->assertSame(600.0, (float) ($declaration->data['profit'] ?? 0.0));
        $this->assertSame(600.0, (float) ($declaration->data['assets'] ?? 0.0));
        $this->assertSame(0.0, (float) ($declaration->data['liabilities'] ?? 0.0));
        $this->assertSame(0.0, (float) ($declaration->data['equity'] ?? 0.0));

        $this->actAsOrg()->get('/accounting/tax-declarations/'.$declaration->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Accounting/TaxDeclarations/Show')
                ->where('declaration.id', $declaration->id)
                ->where('declaration.canton', 'VD')
            );

        $this->actAsOrg()->post('/accounting/tax-declarations/'.$declaration->id.'/finalize')
            ->assertRedirect();

        $this->assertSame('finalized', $declaration->fresh()->status);
        $this->assertNotNull($declaration->fresh()->finalized_at);
    }

    public function test_tax_declaration_show_refreshes_draft_summary_data(): void
    {
        $this->postJournalEntry('2026-02-01', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00', 'Client payment'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00', 'Revenue'),
        ], 'TD-BASE');

        $this->actAsOrg()->post('/accounting/tax-declarations', [
            'fiscal_year' => 2026,
            'canton' => 'vd',
        ])->assertRedirect();

        $declaration = TaxDeclaration::query()->firstOrFail();
        $this->assertSame(1000.0, (float) ($declaration->data['net_result'] ?? 0.0));

        $this->postJournalEntry('2026-02-02', [
            $this->journalLine($this->expenseAccount, '200.00', '0.00', 'Late expense'),
            $this->journalLine($this->bankAccount, '0.00', '200.00', 'Bank payment'),
        ], 'TD-LATE');

        $this->actAsOrg()->get('/accounting/tax-declarations/'.$declaration->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('declaration.data.net_result', 800)
                ->where('declaration.data.profit', 800)
            );
    }

    public function test_cost_centers_crud_and_analytical_report_filtering(): void
    {
        $this->actAsOrg()->post('/accounting/cost-centers', [
            'code' => 'mkt',
            'name' => 'Marketing',
            'parent_id' => null,
        ])->assertRedirect();

        $center = CostCenter::query()->firstOrFail();
        $this->assertSame('MKT', $center->code);

        $this->actAsOrg()->put('/accounting/cost-centers/'.$center->id, [
            'code' => 'MKT',
            'name' => 'Marketing & Ads',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Marketing & Ads', $center->fresh()->name);

        $this->postJournalEntry('2026-03-10', [
            $this->journalLine($this->bankAccount, '500.00', '0.00', 'Receipt'),
            $this->journalLine($this->revenueAccount, '0.00', '500.00', 'Revenue'),
        ], 'AR-REV');

        $this->postJournalEntry('2026-03-11', [
            $this->journalLine($this->expenseAccount, '200.00', '0.00', 'Expense'),
            $this->journalLine($this->bankAccount, '0.00', '200.00', 'Payment'),
        ], 'AR-EXP');

        TransactionLine::query()
            ->whereIn('account_id', [$this->revenueAccount->id, $this->expenseAccount->id])
            ->update(['cost_center_id' => (string) $center->id]);

        $this->actAsOrg()->get('/accounting/analytical-report?from=2026-01-01&to=2026-12-31&cost_center_id='.$center->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/AnalyticalReport')
                ->where('filters.cost_center_id', (string) $center->id)
                ->where('report.total_revenue', 500)
                ->where('report.total_expenses', 200)
                ->where('report.net_profit', 300)
            );

        $this->actAsOrg()->delete('/accounting/cost-centers/'.$center->id)
            ->assertRedirect()
            ->assertSessionHasErrors('cost_center');

        $this->assertDatabaseHas('cost_centers', ['id' => $center->id]);
    }

    public function test_exchange_rates_manual_and_ecb_fetch_flow(): void
    {
        $this->actAsOrg()->post('/accounting/exchange-rates', [
            'currency_from' => 'eur',
            'currency_to' => 'chf',
            'rate' => '0.98',
            'date' => '2026-04-01',
        ])->assertRedirect();

        $manualRate = ExchangeRate::query()->where('source', 'manual')->firstOrFail();
        $this->assertSame('EUR', $manualRate->currency_from);
        $this->assertSame('CHF', $manualRate->currency_to);

        Http::fake([
            'www.ecb.europa.eu/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
  <Cube>
    <Cube time="2026-04-02">
      <Cube currency="CHF" rate="0.95"/>
            <Cube currency="USD" rate="1.10"/>
            <Cube currency="GBP" rate="0.86"/>
    </Cube>
  </Cube>
</gesmes:Envelope>
XML, 200),
        ]);

        $this->actAsOrg()->post('/accounting/exchange-rates/fetch-ecb')
            ->assertRedirect();

        $this->assertDatabaseHas('exchange_rates', [
            'organization_id' => $this->organization->id,
            'currency_from' => 'EUR',
            'currency_to' => 'CHF',
            'source' => 'ecb',
            'date' => '2026-04-02',
        ]);

        $this->assertDatabaseHas('exchange_rates', [
            'organization_id' => $this->organization->id,
            'currency_from' => 'USD',
            'currency_to' => 'CHF',
            'source' => 'ecb',
            'date' => '2026-04-02',
        ]);

        $this->assertDatabaseHas('exchange_rates', [
            'organization_id' => $this->organization->id,
            'currency_from' => 'CHF',
            'currency_to' => 'USD',
            'source' => 'ecb',
            'date' => '2026-04-02',
        ]);

        $ecbRate = ExchangeRate::query()->where('source', 'ecb')->firstOrFail();

        $this->actAsOrg()->delete('/accounting/exchange-rates/'.$ecbRate->id)
            ->assertRedirect()
            ->assertSessionHasErrors('rate');

        $this->actAsOrg()->delete('/accounting/exchange-rates/'.$manualRate->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('exchange_rates', ['id' => $manualRate->id]);
    }

    public function test_exchange_rates_reject_same_currency_pair(): void
    {
        $this->actAsOrg()->post('/accounting/exchange-rates', [
            'currency_from' => 'CHF',
            'currency_to' => 'CHF',
            'rate' => '1.00',
            'date' => '2026-04-01',
        ])->assertRedirect()
            ->assertSessionHasErrors('currency_from');

        $this->assertDatabaseCount('exchange_rates', 0);
    }

    public function test_consolidation_group_report_and_elimination_flow(): void
    {
        $memberOrganization = Organization::factory()->create();

        $memberAsset = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '1100',
            'name' => 'Member Cash',
            'type' => AccountType::Asset->value,
        ]);

        $memberRevenue = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '3100',
            'name' => 'Member Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $memberEntry = JournalEntry::create([
            'organization_id' => $memberOrganization->id,
            'date' => '2026-05-10',
            'reference' => 'MC-1',
            'description' => 'Member sale',
            'is_posted' => true,
        ]);

        TransactionLine::create([
            'journal_entry_id' => $memberEntry->id,
            'account_id' => $memberAsset->id,
            'debit' => '900.00',
            'credit' => '0.00',
            'description' => 'Member asset line',
        ]);

        TransactionLine::create([
            'journal_entry_id' => $memberEntry->id,
            'account_id' => $memberRevenue->id,
            'debit' => '0.00',
            'credit' => '900.00',
            'description' => 'Member revenue line',
        ]);

        $this->postJournalEntry('2026-05-08', [
            $this->journalLine($this->bankAccount, '700.00', '0.00', 'Org sale'),
            $this->journalLine($this->revenueAccount, '0.00', '700.00', 'Org revenue'),
        ], 'OC-1');

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'Group Alpha',
            'member_organization_ids' => [$memberOrganization->id],
            'base_currency' => 'chf',
        ])->assertRedirect();

        $group = ConsolidationGroup::query()->firstOrFail();
        $this->assertContains($this->organization->id, $group->member_organization_ids);
        $this->assertContains($memberOrganization->id, $group->member_organization_ids);
        $this->assertSame('CHF', $group->base_currency);

        $this->actAsOrg()->get('/accounting/consolidation/'.$group->id.'/report?fiscal_year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Accounting/Consolidation/Report')
                ->where('group.id', $group->id)
                ->where('fiscal_year', 2026)
                ->where('result.base_currency', 'CHF')
                ->where('result.assets', 1600)
                ->where('result.revenue', 1600)
                ->where('result.expenses', 0)
                ->where('result.profit', 1600)
            );

        $this->actAsOrg()->post('/accounting/consolidation/'.$group->id.'/eliminations', [
            'account_debit_id' => $this->bankAccount->id,
            'account_credit_id' => $this->revenueAccount->id,
            'amount' => '100.00',
            'fiscal_year' => 2026,
            'description' => 'Intercompany cleanup',
        ])->assertRedirect();

        $elimination = ConsolidationElimination::query()->firstOrFail();
        $this->assertSame($group->id, $elimination->consolidation_group_id);

        $this->actAsOrg()->delete('/accounting/consolidation/eliminations/'.$elimination->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('consolidation_eliminations', ['id' => $elimination->id]);
    }

    public function test_consolidation_group_rejects_non_existent_member_organization(): void
    {
        $missingOrganizationId = (string) Str::uuid();

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'Invalid Group',
            'member_organization_ids' => [$missingOrganizationId],
            'base_currency' => 'CHF',
        ])->assertRedirect()
            ->assertSessionHasErrors('member_organization_ids.0');

        $this->assertDatabaseCount('consolidation_groups', 0);
    }

    public function test_consolidation_elimination_rejects_account_outside_group_members(): void
    {
        $memberOrganization = Organization::factory()->create();
        $outsiderOrganization = Organization::factory()->create();

        $outsiderAccount = Account::create([
            'organization_id' => $outsiderOrganization->id,
            'code' => '1999',
            'name' => 'Outsider Account',
            'type' => AccountType::Asset->value,
        ]);

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'Group Alpha',
            'member_organization_ids' => [$memberOrganization->id],
            'base_currency' => 'CHF',
        ])->assertRedirect();

        $group = ConsolidationGroup::query()->firstOrFail();

        $this->actAsOrg()->post('/accounting/consolidation/'.$group->id.'/eliminations', [
            'account_debit_id' => $outsiderAccount->id,
            'account_credit_id' => $this->revenueAccount->id,
            'amount' => '100.00',
            'fiscal_year' => 2026,
            'description' => 'Should fail',
        ])->assertRedirect()
            ->assertSessionHasErrors('account_debit_id');

        $this->assertDatabaseCount('consolidation_eliminations', 0);
    }

    public function test_consolidation_report_uses_sign_aware_totals_for_liability_accounts(): void
    {
        $memberOrganization = Organization::factory()->create(['currency' => 'CHF']);

        $memberAsset = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '1100',
            'name' => 'Member Asset',
            'type' => AccountType::Asset->value,
        ]);

        $memberLiability = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '2100',
            'name' => 'Member Liability',
            'type' => AccountType::Liability->value,
        ]);

        $entry = JournalEntry::create([
            'organization_id' => $memberOrganization->id,
            'date' => '2026-05-10',
            'reference' => 'SIG-1',
            'description' => 'Sign check',
            'is_posted' => true,
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberLiability->id,
            'debit' => '100.00',
            'credit' => '0.00',
            'description' => 'Abnormal liability debit',
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberAsset->id,
            'debit' => '0.00',
            'credit' => '100.00',
            'description' => 'Offset line',
        ]);

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'Sign Group',
            'member_organization_ids' => [$memberOrganization->id],
            'base_currency' => 'CHF',
        ])->assertRedirect();

        $group = ConsolidationGroup::query()->firstOrFail();

        $this->actAsOrg()->get('/accounting/consolidation/'.$group->id.'/report?fiscal_year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result.assets', 0)
                ->where('result.liabilities', 0)
            );
    }

    public function test_consolidation_report_converts_member_balances_to_base_currency(): void
    {
        $memberOrganization = Organization::factory()->create(['currency' => 'USD']);

        $memberAsset = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '1100',
            'name' => 'Member Cash USD',
            'type' => AccountType::Asset->value,
        ]);

        $memberRevenue = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '3100',
            'name' => 'Member Revenue USD',
            'type' => AccountType::Revenue->value,
        ]);

        $entry = JournalEntry::create([
            'organization_id' => $memberOrganization->id,
            'date' => '2026-05-10',
            'reference' => 'FX-1',
            'description' => 'FX conversion check',
            'is_posted' => true,
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberAsset->id,
            'debit' => '100.00',
            'credit' => '0.00',
            'description' => 'USD asset line',
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberRevenue->id,
            'debit' => '0.00',
            'credit' => '100.00',
            'description' => 'USD revenue line',
        ]);

        ExchangeRate::create([
            'organization_id' => $this->organization->id,
            'currency_from' => 'USD',
            'currency_to' => 'CHF',
            'rate' => '0.90',
            'date' => '2026-05-01',
            'source' => 'manual',
        ]);

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'FX Group',
            'member_organization_ids' => [$memberOrganization->id],
            'base_currency' => 'CHF',
        ])->assertRedirect();

        $group = ConsolidationGroup::query()->firstOrFail();

        $this->actAsOrg()->get('/accounting/consolidation/'.$group->id.'/report?fiscal_year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result.base_currency', 'CHF')
                ->where('result.assets', 90)
                ->where('result.revenue', 90)
                ->where('result.profit', 90)
                ->where('result.missing_exchange_rates', [])
            );
    }

    public function test_consolidation_report_exposes_missing_exchange_rates_when_conversion_pair_is_absent(): void
    {
        $memberOrganization = Organization::factory()->create(['currency' => 'USD']);

        $memberAsset = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '1100',
            'name' => 'Member Cash USD',
            'type' => AccountType::Asset->value,
        ]);

        $memberRevenue = Account::create([
            'organization_id' => $memberOrganization->id,
            'code' => '3100',
            'name' => 'Member Revenue USD',
            'type' => AccountType::Revenue->value,
        ]);

        $entry = JournalEntry::create([
            'organization_id' => $memberOrganization->id,
            'date' => '2026-05-10',
            'reference' => 'FX-MISS-1',
            'description' => 'Missing FX check',
            'is_posted' => true,
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberAsset->id,
            'debit' => '100.00',
            'credit' => '0.00',
            'description' => 'USD asset line',
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $memberRevenue->id,
            'debit' => '0.00',
            'credit' => '100.00',
            'description' => 'USD revenue line',
        ]);

        $this->actAsOrg()->post('/accounting/consolidation/groups', [
            'name' => 'FX Missing Group',
            'member_organization_ids' => [$memberOrganization->id],
            'base_currency' => 'CHF',
        ])->assertRedirect();

        $group = ConsolidationGroup::query()->firstOrFail();

        $this->actAsOrg()->get('/accounting/consolidation/'.$group->id.'/report?fiscal_year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('result.base_currency', 'CHF')
                ->where('result.missing_exchange_rates', ['USD->CHF'])
            );
    }
}
