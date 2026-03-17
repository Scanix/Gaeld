<?php

namespace App\Domains\Contacts\Actions;

use App\Domains\Contacts\Models\Supplier;

class UpdateSupplierAction
{
    public function execute(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }
}
