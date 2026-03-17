<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\Models\Customer;

class CreateCustomerAction
{
    public function execute(array $data): Customer
    {
        return Customer::create($data);
    }
}
