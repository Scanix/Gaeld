<?php

namespace App\Domains\Contacts\Search;

use App\Domains\Contacts\Models\Contact;
use App\Http\Services\BaseSearchProvider;

class ContactSearchProvider extends BaseSearchProvider
{
    public function search(string $query, string $orgId, int $limit): array
    {
        $results = [];

        foreach ($this->searchModel(Contact::class, $query, $orgId, $limit) as $contact) {
            $results[] = [
                'type' => 'contact',
                'id' => $contact->id,
                'title' => $contact->name,
                'subtitle' => collect([$contact->email, $contact->city])->filter()->implode(' · '),
                'url' => route('contacts.show', $contact),
            ];
        }

        return $results;
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'city', 'vat_number'];
    }
}
