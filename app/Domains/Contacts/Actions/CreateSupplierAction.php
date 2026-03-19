<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\DTOs\CreateSupplierData;
use App\Domains\Contacts\Models\Supplier;

class CreateSupplierAction
{
    public function execute(CreateSupplierData $data): Supplier
    {
        return Supplier::create($data->toArray());
    }
}
