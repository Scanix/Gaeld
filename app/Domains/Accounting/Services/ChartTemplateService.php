<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\ChartTemplates\AccountDefinition;
use App\Domains\Accounting\ChartTemplates\ChartTemplateInterface;
use App\Domains\Accounting\ChartTemplates\SwissAssociationTemplate;
use App\Domains\Accounting\ChartTemplates\SwissFreelancerTemplate;
use App\Domains\Accounting\ChartTemplates\SwissSmeTemplate;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;

/**
 * Manages chart-of-accounts templates and applies them to organizations.
 *
 * Provides a registry of available Swiss accounting templates (Freelancer,
 * SME, Association) and seeds the selected template into an organization's
 * account table.
 */
class ChartTemplateService
{
    /** @var array<string, ChartTemplateInterface> */
    private array $templates;

    public function __construct()
    {
        $this->templates = collect([
            new SwissSmeTemplate,
            new SwissFreelancerTemplate,
            new SwissAssociationTemplate,
        ])->keyBy(fn (ChartTemplateInterface $t) => $t->key())->all();
    }

    // ──────────────────────────────────────────────────────────────
    //  Template Discovery
    // ──────────────────────────────────────────────────────────────

    /**
     * List available templates with their translation keys.
     *
     * @return array<int, array{key: string, label_key: string, description_key: string}>
     */
    public function availableTemplates(): array
    {
        return collect($this->templates)->map(fn (ChartTemplateInterface $t) => [
            'key' => $t->key(),
            'label_key' => $t->labelKey(),
            'description_key' => $t->descriptionKey(),
        ])->values()->all();
    }

    /**
     * List valid template keys (for validation rules).
     *
     * @return string[]
     */
    public function validKeys(): array
    {
        return array_keys($this->templates);
    }

    /**
     * Get the template instance by key.
     */
    public function resolve(string $key): ?ChartTemplateInterface
    {
        return $this->templates[$key] ?? null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Template Seeding
    // ──────────────────────────────────────────────────────────────

    /**
     * Seed a chart of accounts for an organization using the given template.
     * Account names are resolved to the organization's locale.
     */
    public function seedTemplate(Organization $organization, string $templateKey): void
    {
        $template = $this->resolve($templateKey);

        if (! $template) {
            return;
        }

        $locale = $organization->locale ?? 'en';

        $systemCodes = array_filter(
            (new \ReflectionClass(AccountCode::class))->getConstants(),
            fn ($v) => is_string($v) && ctype_digit($v),
        );

        foreach ($template->accounts() as $account) {
            $def = new AccountDefinition(
                code: $account['code'],
                type: AccountType::from($account['type']),
                name: $account['name'],
            );

            $name = $def->name[$locale] ?? $def->name['en'];

            Account::create([
                'organization_id' => $organization->id,
                'code' => $def->code,
                'name' => $name,
                'type' => $def->type->value,
                'is_system' => in_array($def->code, $systemCodes, true),
            ]);
        }
    }

    /**
     * Ensure the mandatory system accounts exist for an organization.
     *
     * These accounts are required by core operations (invoice posting,
     * payment recording, VAT settlement) and must exist even when the
     * user chooses a custom chart of accounts or selects 'none'.
     */
    public function ensureSystemAccounts(Organization $organization): void
    {
        $locale = $organization->locale ?? 'en';

        $systemAccounts = [
            ['code' => AccountCode::BANK_CASH, 'type' => AccountType::Asset, 'name' => [
                'en' => 'Bank Account CHF', 'fr' => 'Compte bancaire CHF', 'de' => 'Bankkonto CHF', 'it' => 'Conto bancario CHF',
            ]],
            ['code' => AccountCode::ACCOUNTS_RECEIVABLE, 'type' => AccountType::Asset, 'name' => [
                'en' => 'Accounts Receivable', 'fr' => 'Débiteurs', 'de' => 'Debitoren', 'it' => 'Debitori',
            ]],
            ['code' => AccountCode::VAT_INPUT, 'type' => AccountType::Asset, 'name' => [
                'en' => 'VAT Input Tax', 'fr' => 'Impôt préalable', 'de' => 'Vorsteuer', 'it' => 'Imposta precedente',
            ]],
            ['code' => AccountCode::VAT_OUTPUT, 'type' => AccountType::Liability, 'name' => [
                'en' => 'VAT Output Tax', 'fr' => 'TVA due', 'de' => 'Umsatzsteuer (MWST)', 'it' => 'IVA dovuta',
            ]],
            ['code' => AccountCode::VAT_PAYABLE_AFC, 'type' => AccountType::Liability, 'name' => [
                'en' => 'VAT Payable AFC', 'fr' => 'TVA à payer AFC', 'de' => 'MWST-Zahllast ESTV', 'it' => 'IVA da versare AFC',
            ]],
            ['code' => AccountCode::REVENUE, 'type' => AccountType::Revenue, 'name' => [
                'en' => 'Revenue from Services', 'fr' => 'Produits des prestations de services', 'de' => 'Dienstleistungserlöse', 'it' => 'Ricavi da prestazioni di servizi',
            ]],
            ['code' => AccountCode::ROUNDING_DIFFERENCE, 'type' => AccountType::Revenue, 'name' => [
                'en' => 'Revenue Corrections', 'fr' => 'Corrections de produits', 'de' => 'Erlösberichtigungen', 'it' => 'Rettifiche di ricavi',
            ]],
            ['code' => AccountCode::OPENING_BALANCE, 'type' => AccountType::Equity, 'name' => [
                'en' => 'Opening Balance', 'fr' => 'Bilan d\'ouverture', 'de' => 'Eröffnungsbilanz', 'it' => 'Bilancio di apertura',
            ]],
        ];

        $existingCodes = Account::where('organization_id', $organization->id)
            ->whereIn('code', array_column($systemAccounts, 'code'))
            ->pluck('code')
            ->all();

        foreach ($systemAccounts as $account) {
            if (in_array($account['code'], $existingCodes, true)) {
                continue;
            }

            Account::create([
                'organization_id' => $organization->id,
                'code' => $account['code'],
                'name' => $account['name'][$locale] ?? $account['name']['en'],
                'type' => $account['type']->value,
                'is_system' => true,
            ]);
        }
    }

    /**
     * Whether the template also provides VAT rates.
     */
    public function templateSeedsVatRates(string $templateKey): bool
    {
        $template = $this->resolve($templateKey);

        return $template?->seedsVatRates() ?? false;
    }
}
