<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateSupplierData;
use App\Domains\Contacts\DTOs\UpdateSupplierData;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Queries\SupplierQuery;
use App\Domains\Contacts\Requests\StoreSupplierRequest;
use App\Http\Controllers\Concerns\HandlesCrudOperations;
use App\Http\Controllers\Controller;

/**
 * Supplier CRUD with full-text search and soft-delete support.
 */
class SupplierController extends Controller
{
    use HandlesCrudOperations;

    protected function modelClass(): string
    {
        return Supplier::class;
    }

    protected function createDtoClass(): string
    {
        return CreateSupplierData::class;
    }

    protected function updateDtoClass(): string
    {
        return UpdateSupplierData::class;
    }

    protected function queryClass(): string
    {
        return SupplierQuery::class;
    }

    protected function storeRequestClass(): string
    {
        return StoreSupplierRequest::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Contacts/Suppliers';
    }

    protected function routePrefix(): string
    {
        return 'suppliers';
    }

    protected function resourceName(): string
    {
        return 'supplier';
    }

    /** @return array<int, string> */
    protected function showRelations(): array
    {
        return ['expenses', 'contactPersons'];
    }
}
