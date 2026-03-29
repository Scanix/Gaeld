<?php

namespace App\Domains\Contacts\Enums;

/** Contact entity type: individual person or organization/company. */
enum ContactType: string
{
    case Individual = 'individual';
    case Organization = 'organization';
}
