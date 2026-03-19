<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\DTOs\UpdateSupplierData;
use App\Domains\Contacts\Models\Supplier;

class UpdateSupplierAction
{
    public function execute(Supplier $supplier, UpdateSupplierData $data): Supplier
    {
        $supplier->update($data->toArray());

        return $supplier->fresh();
    }
}
