<?php

namespace App\Domains\Accounting\DTOs;

use App\Domains\Accounting\Enums\AccountType;

readonly class CreateAccountData
{
    public function __construct(
        public string $organizationId,
        public string $code,
        public string $name,
        public AccountType $type,
        public ?string $parentId = null,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
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

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'parent_id' => $this->parentId,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
