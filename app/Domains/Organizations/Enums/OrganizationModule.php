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

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
