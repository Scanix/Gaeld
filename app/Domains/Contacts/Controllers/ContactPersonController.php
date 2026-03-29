<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateContactPersonData;
use App\Domains\Contacts\DTOs\UpdateContactPersonData;
use App\Domains\Contacts\Models\ContactPerson;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD for contact persons attached to customers or suppliers.
 */
class ContactPersonController extends Controller
{
    private const VALIDATION_RULES = [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'position' => 'nullable|string|max:255',
        'is_primary' => 'boolean',
        'notes' => 'nullable|string|max:1000',
    ];

    public function store(Request $request, string $contactableType, string $contactableId): JsonResponse
    {
        $parent = $this->resolveParent($contactableType, $contactableId);
        $this->authorizeParent('update', $parent);

        $validated = $request->validate(self::VALIDATION_RULES);

        // If marking as primary, unset other primary contacts
        if ($validated['is_primary'] ?? false) {
            $parent->contactPersons()->update(['is_primary' => false]);
        }

        $contactPerson = $parent->contactPersons()->create(
            CreateContactPersonData::fromArray(array_merge($validated, [
                'contactable_type' => get_class($parent),
                'contactable_id' => $parent->id,
            ]))->toArray()
        );

        return response()->json(['contact_person' => $contactPerson], 201);
    }

    public function update(Request $request, string $contactableType, string $contactableId, ContactPerson $contactPerson): JsonResponse
    {
        $parent = $this->resolveParent($contactableType, $contactableId);
        $this->authorizeParent('update', $parent);

        abort_unless($contactPerson->contactable_id === $parent->id, 404);

        $validated = $request->validate(self::VALIDATION_RULES);

        // If marking as primary, unset other primary contacts
        if ($validated['is_primary'] ?? false) {
            $parent->contactPersons()
                ->where('id', '!=', $contactPerson->id)
                ->update(['is_primary' => false]);
        }

        $contactPerson->update(
            UpdateContactPersonData::fromArray($validated)->toArray()
        );

        return response()->json(['contact_person' => $contactPerson->fresh()]);
    }

    public function destroy(string $contactableType, string $contactableId, ContactPerson $contactPerson): JsonResponse
    {
        $parent = $this->resolveParent($contactableType, $contactableId);
        $this->authorizeParent('update', $parent);

        abort_unless($contactPerson->contactable_id === $parent->id, 404);

        $contactPerson->delete();

        return response()->json(null, 204);
    }

    private function resolveParent(string $type, string $id): Model
    {
        return match ($type) {
            'customers' => Customer::findOrFail($id),
            'suppliers' => Supplier::findOrFail($id),
            default => abort(404),
        };
    }

    private function authorizeParent(string $ability, Model $parent): void
    {
        $this->authorize($ability, $parent);
    }
}
