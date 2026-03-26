<?php

namespace App\Domains\Contacts\Enums;

enum ContactType: string
{
    case Individual = 'individual';
    case Organization = 'organization';
}
