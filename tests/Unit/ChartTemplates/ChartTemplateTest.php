<?php

namespace Tests\Unit\ChartTemplates;

use App\Domains\Accounting\ChartTemplates\ChartTemplateInterface;
use App\Domains\Accounting\ChartTemplates\SwissAssociationTemplate;
use App\Domains\Accounting\ChartTemplates\SwissFreelancerTemplate;
use App\Domains\Accounting\ChartTemplates\SwissSmeTemplate;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChartTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{ChartTemplateInterface}>
     */
    public static function templateProvider(): array
    {
        return [
            'swiss_sme' => [new SwissSmeTemplate],
            'swiss_freelancer' => [new SwissFreelancerTemplate],
            'swiss_association' => [new SwissAssociationTemplate],
        ];
    }

    #[DataProvider('templateProvider')]
    public function test_key_is_non_empty_string(ChartTemplateInterface $template): void
    {
        $this->assertNotEmpty($template->key());
        $this->assertIsString($template->key());
    }

    #[DataProvider('templateProvider')]
    public function test_label_key_is_non_empty(ChartTemplateInterface $template): void
    {
        $this->assertNotEmpty($template->labelKey());
    }

    #[DataProvider('templateProvider')]
    public function test_description_key_is_non_empty(ChartTemplateInterface $template): void
    {
        $this->assertNotEmpty($template->descriptionKey());
    }

    #[DataProvider('templateProvider')]
    public function test_accounts_returns_non_empty_array(ChartTemplateInterface $template): void
    {
        $accounts = $template->accounts();
        $this->assertNotEmpty($accounts);
        $this->assertIsArray($accounts);
    }

    #[DataProvider('templateProvider')]
    public function test_each_account_has_required_fields(ChartTemplateInterface $template): void
    {
        foreach ($template->accounts() as $i => $account) {
            $this->assertArrayHasKey('code', $account, "Account #{$i} missing 'code'");
            $this->assertArrayHasKey('type', $account, "Account #{$i} missing 'type'");
            $this->assertArrayHasKey('name', $account, "Account #{$i} missing 'name'");
            $this->assertIsArray($account['name'], "Account #{$i} 'name' must be array");
        }
    }

    #[DataProvider('templateProvider')]
    public function test_account_codes_are_unique(ChartTemplateInterface $template): void
    {
        $codes = array_column($template->accounts(), 'code');
        $this->assertSame(count($codes), count(array_unique($codes)), 'Duplicate account codes found');
    }

    #[DataProvider('templateProvider')]
    public function test_account_names_have_english_translation(ChartTemplateInterface $template): void
    {
        foreach ($template->accounts() as $i => $account) {
            $this->assertArrayHasKey('en', $account['name'], "Account code {$account['code']} missing English name");
            $this->assertNotEmpty($account['name']['en']);
        }
    }

    #[DataProvider('templateProvider')]
    public function test_seeds_vat_rates_returns_bool(ChartTemplateInterface $template): void
    {
        $this->assertIsBool($template->seedsVatRates());
    }

    public function test_sme_template_has_key_accounts(): void
    {
        $template = new SwissSmeTemplate;
        $codes = array_column($template->accounts(), 'code');

        $this->assertContains('1000', $codes, 'Cash account missing');
        $this->assertContains('1020', $codes, 'Bank account missing');
        $this->assertContains('1100', $codes, 'Accounts receivable missing');
        $this->assertContains('2000', $codes, 'Accounts payable missing');
    }

    public function test_freelancer_template_has_key_accounts(): void
    {
        $template = new SwissFreelancerTemplate;
        $codes = array_column($template->accounts(), 'code');

        $this->assertContains('1000', $codes, 'Cash account missing');
        $this->assertContains('1020', $codes, 'Bank account missing');
    }

    public function test_each_template_has_unique_key(): void
    {
        $templates = [new SwissSmeTemplate, new SwissFreelancerTemplate, new SwissAssociationTemplate];
        $keys = array_map(fn ($t) => $t->key(), $templates);
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_ensure_system_accounts_creates_accounts_when_none_exist(): void
    {
        $org = Organization::factory()->create(['locale' => 'en']);
        $service = app(ChartTemplateService::class);

        $this->assertSame(0, Account::where('organization_id', $org->id)->count());

        $service->ensureSystemAccounts($org);

        $accounts = Account::where('organization_id', $org->id)->get();
        $codes = $accounts->pluck('code')->all();

        $this->assertContains(AccountCode::ACCOUNTS_RECEIVABLE, $codes);
        $this->assertContains(AccountCode::BANK_CASH, $codes);
        $this->assertContains(AccountCode::VAT_OUTPUT, $codes);
        $this->assertContains(AccountCode::REVENUE, $codes);
        $this->assertContains(AccountCode::ROUNDING_DIFFERENCE, $codes);
        $this->assertContains(AccountCode::OPENING_BALANCE, $codes);

        $this->assertTrue(
            $accounts->every(fn (Account $a) => $a->is_system),
            'All created accounts must be marked as system accounts',
        );
    }

    public function test_ensure_system_accounts_skips_existing_accounts(): void
    {
        $org = Organization::factory()->create(['locale' => 'en']);
        $service = app(ChartTemplateService::class);

        Account::create([
            'organization_id' => $org->id,
            'code' => AccountCode::ACCOUNTS_RECEIVABLE,
            'name' => 'My Custom AR',
            'type' => 'asset',
            'is_system' => true,
        ]);

        $service->ensureSystemAccounts($org);

        $ar = Account::where('organization_id', $org->id)
            ->where('code', AccountCode::ACCOUNTS_RECEIVABLE)
            ->get();

        $this->assertCount(1, $ar, 'Must not duplicate existing account');
        $this->assertSame('My Custom AR', $ar->first()->name, 'Must not overwrite existing name');
    }

    public function test_ensure_system_accounts_respects_organization_locale(): void
    {
        $org = Organization::factory()->create(['locale' => 'de']);
        $service = app(ChartTemplateService::class);

        $service->ensureSystemAccounts($org);

        $ar = Account::where('organization_id', $org->id)
            ->where('code', AccountCode::ACCOUNTS_RECEIVABLE)
            ->first();

        $this->assertSame('Debitoren', $ar->name);
    }

    public function test_seed_template_also_ensures_system_accounts(): void
    {
        $org = Organization::factory()->create(['locale' => 'en']);
        $service = app(ChartTemplateService::class);

        $service->seedTemplate($org, 'swiss_sme');

        $systemCodes = [
            AccountCode::ACCOUNTS_RECEIVABLE,
            AccountCode::BANK_CASH,
            AccountCode::VAT_OUTPUT,
            AccountCode::REVENUE,
            AccountCode::ROUNDING_DIFFERENCE,
        ];

        foreach ($systemCodes as $code) {
            $account = Account::where('organization_id', $org->id)
                ->where('code', $code)
                ->first();

            $this->assertNotNull($account, "System account {$code} must exist after seeding");
            $this->assertTrue($account->is_system, "Account {$code} must be marked as system");
        }
    }
}
