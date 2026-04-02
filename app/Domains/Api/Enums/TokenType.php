<?php

namespace App\Domains\Api\Enums;

/** Sanctum token type: personal (user-scoped) vs. organization-scoped. */
enum TokenType: string
{
    case Personal = 'personal';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Personal => __('app.token_type_personal'),
            self::Organization => __('app.token_type_organization'),
        };
    }
}
