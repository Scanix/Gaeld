<?php

namespace App\Domains\Contacts\Enums;

/** Contact entity type: individual person or organization/company. */
enum ContactType: string
{
    case Individual = 'individual';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('app.contact_type_individual'),
            self::Organization => __('app.contact_type_organization'),
        };
    }
}
