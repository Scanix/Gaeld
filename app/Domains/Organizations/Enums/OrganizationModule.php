<?php

namespace App\Domains\Organizations\Enums;

/**
 * Modules an owner can switch on/off per organization (Settings → Modules).
 *
 * Each value matches a key in config/features.php. The list intentionally
 * excludes platform-wide flags (saas, api_access, etc.) which are managed by
 * the operator, not the org owner.
 */
enum OrganizationModule: string
{
    case Budgets = 'budgets';
    case YearEndClosing = 'year_end_closing';
    case SocialCharges = 'social_charges';
    case AccountMatching = 'account_matching';
    case FiduciaryExport = 'fiduciary_export';
    case LegalArchives = 'legal_archives';
    case Assets = 'assets';
    case TaxDeclaration = 'tax_declaration';
    case Analytical = 'analytical';
    case MultiCurrency = 'multi_currency';
    case Consolidation = 'consolidation';
    case Payroll = 'payroll';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Recommended module defaults per activity type.
     *
     * @return array<string, array<string, bool>>
     */
    public static function presets(): array
    {
        return [
            'freelancer' => [
                'budgets' => false,
                'year_end_closing' => true,
                'social_charges' => false,
                'account_matching' => true,
                'fiduciary_export' => true,
                'legal_archives' => false,
                'assets' => false,
                'tax_declaration' => false,
                'analytical' => false,
                'multi_currency' => false,
                'consolidation' => false,
                'payroll' => false,
            ],
            'sme' => [
                'budgets' => true,
                'year_end_closing' => true,
                'social_charges' => true,
                'account_matching' => true,
                'fiduciary_export' => false,
                'legal_archives' => true,
                'assets' => true,
                'tax_declaration' => true,
                'analytical' => false,
                'multi_currency' => false,
                'consolidation' => false,
                'payroll' => true,
            ],
            'fiduciary' => [
                'budgets' => true,
                'year_end_closing' => true,
                'social_charges' => true,
                'account_matching' => true,
                'fiduciary_export' => true,
                'legal_archives' => true,
                'assets' => true,
                'tax_declaration' => true,
                'analytical' => true,
                'multi_currency' => true,
                'consolidation' => true,
                'payroll' => true,
            ],
        ];
    }
}
