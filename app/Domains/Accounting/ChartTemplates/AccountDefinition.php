<?php

namespace App\Domains\Accounting\ChartTemplates;

use App\Domains\Accounting\Enums\AccountType;

readonly class AccountDefinition
{
    /**
     * @param  array<string, string>  $name  Translations keyed by locale
     */
    public function __construct(
        public string $code,
        public AccountType $type,
        public array $name,
    ) {}

    /**
     * @return array{code: string, type: string, name: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type->value,
            'name' => $this->name,
        ];
    }
}
