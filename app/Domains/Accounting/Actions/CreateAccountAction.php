<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\CreateAccountData;
use App\Domains\Accounting\Models\Account;

class CreateAccountAction
{
    public function execute(CreateAccountData $data): Account
    {
        return Account::create($data->toArray());
    }
}
