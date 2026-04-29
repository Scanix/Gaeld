<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class YearEndClosingTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private User $accountant;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();
        $this->accountant = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Year-End Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        $this->organization->users()->attach($this->accountant->id, ['role' => 'accountant']);
        $this->assignOrganizationRole($this->accountant, $this->organization, 'accountant');

        // Create minimum accounts for the organization
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Debtors',
            'type' => AccountType::Asset->value,
        ]);

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    private function asOwner(): self
    {
        return $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    private function asAccountant(): self
    {
        return $this->actingAs($this->accountant)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }

    public function test_reopen_fiscal_year_as_owner(): void
    {
        $this->organization->closeFiscalYear(2025);
        $this->assertTrue($this->organization->isFiscalYearClosed(2025));

        $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2025));
    }

    public function test_accountant_cannot_reopen_fiscal_year(): void
    {
        $this->organization->closeFiscalYear(2025);

        $this->asAccountant()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertForbidden();

        $this->organization->refresh();
        $this->assertTrue($this->organization->isFiscalYearClosed(2025));
    }

    public function test_reopen_non_closed_year_returns_error(): void
    {
        $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_reopen_succeeds_and_redirects_with_success(): void
    {
        $this->organization->closeFiscalYear(2025);

        $response = $this->asOwner()
            ->post('/accounting/year-end-closing/reopen', ['year' => 2025]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2025));
    }

    public function test_index_passes_closed_years_to_frontend(): void
    {
        $this->organization->closeFiscalYear(2024);
        $this->organization->closeFiscalYear(2025);

        $response = $this->asOwner()
            ->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/YearEndClosing')
            ->has('closedYears', 2)
            ->where('closedYears', [2024, 2025])
            ->where('canReopenYear', true)
        );
    }

    public function test_accountant_sees_can_reopen_year_as_false(): void
    {
        $this->organization->closeFiscalYear(2025);

        $response = $this->asAccountant()
            ->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('canReopenYear', false)
        );
    }

    public function test_store_uses_generic_flash_error_when_internal_exception_message_is_empty(): void
    {
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '2900',
            'name' => 'Year-End Result',
            'type' => AccountType::Liability->value,
        ]);

        foreach ([[1, '01-01', '03-31'], [2, '04-01', '06-30'], [3, '07-01', '09-30'], [4, '10-01', '12-31']] as [, $from, $to]) {
            JournalEntry::create([
                'organization_id' => $this->organization->id,
                'date' => "2025-{$from}",
                'reference' => "VAT-SETTLEMENT-2025-{$from}-2025-{$to}",
                'description' => 'VAT settlement',
                'type' => 'vat_settlement',
                'is_posted' => true,
            ]);
        }

        $asset = Account::where('organization_id', $this->organization->id)
            ->where('code', '1100')
            ->firstOrFail();

        $this->mock(ClosingAccountsService::class, function (MockInterface $mock) use ($asset): void {
            $mock->shouldReceive('compute')
                ->once()
                ->andReturn([
                    [[
                        'account_id' => (string) $asset->id,
                        'code' => '1100',
                        'balance' => '100.00',
                    ]],
                    [],
                ]);
        });

        $this->mock(LedgerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('postEntry')
                ->once()
                ->andThrow(new \RuntimeException(''));
        });

        $response = $this->asOwner()
            ->from('/accounting/year-end-closing?year=2025')
            ->post('/accounting/year-end-closing', [
                'year' => 2025,
                'closing_date' => '2025-12-31',
                'reference' => 'YE-2025',
                'result_account_code' => '2900',
            ]);

        $response->assertRedirect('/accounting/year-end-closing?year=2025');
        $response->assertSessionHas('error', __('app.unexpected_error'));
    }
}
