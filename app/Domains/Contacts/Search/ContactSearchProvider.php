<?php

namespace App\Domains\Contacts\Search;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Http\Services\BaseSearchProvider;

class ContactSearchProvider extends BaseSearchProvider
{
    public function search(string $query, string $orgId, int $limit): array
    {
        $results = [];

        foreach ($this->searchModel(Customer::class, $query, $orgId, $limit) as $customer) {
            $results[] = [
                'type' => 'customer',
                'id' => $customer->id,
                'title' => $customer->name,
                'subtitle' => collect([$customer->email, $customer->city])->filter()->implode(' · '),
                'url' => route('customers.show', $customer),
            ];
        }

        foreach ($this->searchModel(Supplier::class, $query, $orgId, $limit) as $supplier) {
            $results[] = [
                'type' => 'supplier',
                'id' => $supplier->id,
                'title' => $supplier->name,
                'subtitle' => collect([$supplier->email, $supplier->city])->filter()->implode(' · '),
                'url' => route('suppliers.show', $supplier),
            ];
        }

        return $results;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'city', 'vat_number'];
    }
}
