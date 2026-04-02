<?php

namespace App\Domains\Banking\Enums;

/** Bank statement import format. */
enum CamtFormat: string
{
    case Camt053 = 'camt053';
    case Camt054 = 'camt054';
    case Csv = 'csv';
    case Mt940 = 'mt940';

    public function label(): string
    {
        return match ($this) {
            self::Camt053 => __('app.camt_format_camt053'),
            self::Camt054 => __('app.camt_format_camt054'),
            self::Csv => __('app.camt_format_csv'),
            self::Mt940 => __('app.camt_format_mt940'),
        };
    }
}
