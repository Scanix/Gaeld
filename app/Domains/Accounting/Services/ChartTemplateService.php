<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\ChartTemplates\AccountDefinition;
use App\Domains\Accounting\ChartTemplates\ChartTemplateInterface;
use App\Domains\Accounting\ChartTemplates\SwissAssociationTemplate;
use App\Domains\Accounting\ChartTemplates\SwissFreelancerTemplate;
use App\Domains\Accounting\ChartTemplates\SwissSmeTemplate;
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
