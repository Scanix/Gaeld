<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\Models\Supplier;

class CreateSupplierAction
{
    public function execute(array $data): Supplier
    {
        return Supplier::create($data);
    }
}
