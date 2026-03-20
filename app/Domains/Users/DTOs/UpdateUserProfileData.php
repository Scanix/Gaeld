<?php

namespace App\Domains\Users\DTOs;

readonly class UpdateUserProfileData
{
    public function __construct(
        public string $name,
        public string $locale,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            locale: $data['locale'],
        );
    }
}
