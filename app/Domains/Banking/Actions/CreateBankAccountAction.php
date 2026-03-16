<?php

namespace App\Domains\Banking\Actions;

use App\Domains\Banking\Models\BankAccount;

class CreateBankAccountAction
{
    public function execute(array $data): BankAccount
    {
        return BankAccount::create([
            'organization_id' => $data['organization_id'],
            'account_id' => $data['account_id'] ?? null,
            'name' => $data['name'],
            'iban' => $data['iban'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'currency' => $data['currency'] ?? 'CHF',
            'balance' => $data['balance'] ?? 0,
        ]);
    }
}
