<?php

namespace App\Domains\Accounting\Enums;

/** Swiss cantonal tax declaration lifecycle status: draft → finalized. */
enum TaxDeclarationStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('app.tax_declaration_status_draft'),
            self::Finalized => __('app.tax_declaration_status_finalized'),
        };
    }
}
