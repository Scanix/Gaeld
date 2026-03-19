<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;

class UpdateCustomerAction
{
    public function execute(Customer $customer, UpdateCustomerData $data): Customer
    {
        $customer->update($data->toArray());

        return $customer;
    }
}
