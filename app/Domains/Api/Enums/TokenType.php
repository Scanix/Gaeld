<?php

namespace App\Domains\Api\Enums;

/** Sanctum token type: personal (user-scoped) vs. organization-scoped. */
enum TokenType: string
{
    case Personal = 'personal';
    case Organization = 'organization';
}
