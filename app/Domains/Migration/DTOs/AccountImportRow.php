<?php

namespace App\Domains\Migration\DTOs;

class AccountImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $description = null,
        public readonly bool $isActive = true,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
