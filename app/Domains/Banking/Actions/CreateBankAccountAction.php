<?php

namespace App\Domains\Banking\Actions;

use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\Models\BankAccount;

class CreateBankAccountAction
{
    public function execute(CreateBankAccountData $data): BankAccount
    {
        return BankAccount::create($data->toArray());
    }
}
