<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Contacts\Requests\StoreCustomerRequest;
use App\Http\Controllers\Concerns\HandlesCrudOperations;
use App\Http\Controllers\Controller;

/**
 * Customer CRUD with full-text search and soft-delete support.
 */
class CustomerController extends Controller
{
    use HandlesCrudOperations;

    protected function modelClass(): string
    {
        return Customer::class;
    }

    protected function createDtoClass(): string
    {
        return CreateCustomerData::class;
    }

    protected function updateDtoClass(): string
    {
        return UpdateCustomerData::class;
    }

    protected function queryClass(): string
    {
        return CustomerQuery::class;
    }

    protected function storeRequestClass(): string
    {
        return StoreCustomerRequest::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Contacts/Customers';
    }

    protected function routePrefix(): string
    {
        return 'customers';
    }

    protected function resourceName(): string
    {
        return 'customer';
    }

    /** @return array<int, string> */
    protected function showRelations(): array
    {
        return ['invoices', 'contactPersons'];
    }
}
