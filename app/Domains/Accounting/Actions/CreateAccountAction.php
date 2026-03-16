<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Account;

class CreateAccountAction
{
    public function execute(array $data): Account
    {
        return Account::create([
            'organization_id' => $data['organization_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'parent_id' => $data['parent_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
