<?php

namespace App\Domains\Organizations\Enums;

/** Organization business profile — drives menu filtering and onboarding flow. */
enum BusinessType: string
{
    case Freelancer = 'freelancer';
    case Sme = 'sme';
    case Fiduciary = 'fiduciary';

    public function label(): string
    {
        return match ($this) {
            self::Freelancer => __('app.business_type_freelancer'),
            self::Sme => __('app.business_type_sme'),
            self::Fiduciary => __('app.business_type_fiduciary'),
        };
    }

    public function chartTemplate(): string
    {
        return match ($this) {
            self::Freelancer => 'swiss_freelancer',
            self::Sme => 'swiss_sme',
            self::Fiduciary => 'swiss_sme',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
