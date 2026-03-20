<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\Models\Customer;

class CreateCustomerAction
{
    public function execute(CreateCustomerData $data): Customer
    {
        return Customer::create($data->toArray());
    }
}
