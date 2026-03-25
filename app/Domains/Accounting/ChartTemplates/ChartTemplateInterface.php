<?php

namespace App\Domains\Accounting\ChartTemplates;

interface ChartTemplateInterface
{
    /**
     * A unique key identifying this template (e.g. 'swiss_sme').
     */
    public function key(): string;

    /**
     * The translation key for the template's display label.
     */
    public function labelKey(): string;

    /**
     * The translation key for the template's description.
     */
    public function descriptionKey(): string;

    /**
     * Return the account definitions for this template.
     *
     * Each entry: ['code' => '1000', 'type' => 'asset', 'name' => ['en' => '...', 'fr' => '...', ...]]
     *
     * @return array<int, array{code: string, type: string, name: array<string, string>}>
     */
    public function accounts(): array;

    /**
     * Whether this template also seeds VAT rates.
     */
    public function seedsVatRates(): bool;
}
