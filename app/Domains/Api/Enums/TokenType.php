<?php

namespace App\Domains\Api\Enums;

enum TokenType: string
{
    case Personal = 'personal';
    case Organization = 'organization';
}
