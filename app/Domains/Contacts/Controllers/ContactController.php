<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateContactData;
use App\Domains\Contacts\DTOs\UpdateContactData;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Contacts\Requests\StoreContactRequest;
use App\Http\Controllers\Concerns\HandlesCrudOperations;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified contact CRUD: covers contacts that are customers, suppliers, or both.
 */
class ContactController extends Controller
{
    use HandlesCrudOperations, HandlesFlashErrorResponses;

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

    public function destroy(): RedirectResponse
    {
        $model = $this->resolveModel();
        $this->authorize('delete', $model);

        if ($model->invoices()->exists() || $model->expenses()->exists()) {
            return $this->backWithError(__('app.cannot_delete_contact_with_history'));
        }

        $model->delete();

        return redirect()->route('contacts.index')
            ->with('success', __('app.contact_deleted'));
    }

    public function trashed(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        return Inertia::render('Contacts/Trashed', [
            'contacts' => ContactQuery::trashed($request),
        ]);
    }

    public function restore(string $contact): RedirectResponse
    {
        $model = Contact::onlyTrashed()->where('uuid', $contact)->firstOrFail();
        $this->authorize('restore', $model);

        $model->restore();

        return redirect()->route('contacts.show', $model)
            ->with('success', __('app.contact_restored'));
    }
}
