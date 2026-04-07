<?php

namespace App\Domains\Accounting\DTOs;

use App\Domains\Accounting\Enums\AccountType;
use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for creating a new chart-of-accounts entry.
 */
readonly class CreateAccountData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $code,
        public string $name,
        public AccountType $type,
        public ?string $parentId = null,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'code', 'name', 'type']);

        return new self(
            organizationId: $data['organization_id'],
            code: $data['code'],
            name: $data['name'],
            type: $data['type'] instanceof AccountType ? $data['type'] : AccountType::from($data['type']),
            parentId: $data['parent_id'] ?? null,
            description: $data['description'] ?? null,
            isActive: $data['is_active'] ?? true,
        );
    }
}
