<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\Models\Customer;
use Illuminate\Support\Facades\Validator;

class CreateCustomerAction
{
    public function execute(array $data): Customer
    {
        Validator::make($data, [
            'organization_id' => ['required', 'string'],
            'name' => ['required', 'string'],
        ])->validate();

        return Customer::create($data);
    }
}
