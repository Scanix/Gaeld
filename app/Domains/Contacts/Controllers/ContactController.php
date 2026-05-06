<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateContactData;
use App\Domains\Contacts\DTOs\UpdateContactData;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Contacts\Requests\StoreContactRequest;
use App\Http\Controllers\Concerns\HandlesCrudOperations;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified contact CRUD: covers contacts that are customers, suppliers, or both.
 */
class ContactController extends Controller
{
    use HandlesCrudOperations;

    public function show(): Response
    {
        $model = $this->resolveModel();
        $this->authorize('view', $model);

        $model->load($this->showRelations());

        return Inertia::render($this->inertiaPrefix().'/Show', [
            $this->resourceName() => $model,
            'invoices' => $model->invoices,
            'expenses' => $model->expenses,
        ]);
    }

    protected function modelClass(): string
    {
        return Contact::class;
    }

    protected function createDtoClass(): string
    {
        return CreateContactData::class;
    }

    protected function updateDtoClass(): string
    {
        return UpdateContactData::class;
    }

    protected function queryClass(): string
    {
        return ContactQuery::class;
    }

    protected function storeRequestClass(): string
    {
        return StoreContactRequest::class;
    }

    protected function inertiaPrefix(): string
    {
        return 'Contacts';
    }

    protected function routePrefix(): string
    {
        return 'contacts';
    }

    protected function resourceName(): string
    {
        return 'contact';
    }

    /** @return array<int, string> */
    protected function showRelations(): array
    {
        return ['invoices', 'expenses', 'contactPersons'];
    }
}
