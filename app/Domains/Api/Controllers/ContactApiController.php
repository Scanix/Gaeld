<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Requests\ContactApiRequest;
use App\Domains\Api\Resources\ContactResource;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON API access to organization contacts used by invoices and expenses.
 *
 * @group Contacts
 */
class ContactApiController extends Controller
{
    /**
     * List contacts
     *
     * Returns a paginated list of contacts for the current organisation.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $contacts = Contact::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('type'), fn ($query, string $type) => $query->where('type', $type))
            ->with('contactPersons')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    /**
     * Show contact
     *
     * Returns a single contact by UUID.
     *
     * @urlParam contact string required The contact UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     */
    public function show(Contact $contact, CurrentOrganization $currentOrganization): ContactResource
    {
        $this->ensureOrganization($contact, $currentOrganization->id());
        $this->authorize('view', $contact);

        return new ContactResource($contact->load('contactPersons'));
    }

    /**
     * Create contact
     *
     * Creates a contact in the current organisation.
     */
    public function store(
        ContactApiRequest $request,
        CurrentOrganization $currentOrganization,
    ): ContactResource|JsonResponse {
        $this->authorize('create', Contact::class);

        $contact = Contact::create([
            ...$request->validated(),
            'organization_id' => $currentOrganization->id(),
        ]);

        return (new ContactResource($contact->load('contactPersons')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update contact
     *
     * Updates an existing contact in the current organisation.
     *
     * @urlParam contact string required The contact UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     */
    public function update(
        ContactApiRequest $request,
        Contact $contact,
        CurrentOrganization $currentOrganization,
    ): ContactResource {
        $this->ensureOrganization($contact, $currentOrganization->id());
        $this->authorize('update', $contact);
        $contact->update($request->validated());

        return new ContactResource($contact->fresh('contactPersons'));
    }

    /**
     * Delete contact
     *
     * Permanently deletes a contact from the current organisation.
     *
     * @urlParam contact string required The contact UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     */
    public function destroy(
        Contact $contact,
        CurrentOrganization $currentOrganization,
    ): JsonResponse {
        $this->ensureOrganization($contact, $currentOrganization->id());
        $this->authorize('delete', $contact);
        $contact->delete();

        return response()->json(null, 204);
    }

    private function ensureOrganization(Contact $contact, string $organizationId): void
    {
        abort_unless($contact->organization_id === $organizationId, 404);
    }
}
