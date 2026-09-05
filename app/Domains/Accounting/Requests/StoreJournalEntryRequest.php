<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $journalEntry = $this->route('journalEntry');

        return $this->user()?->can(
            $journalEntry instanceof JournalEntry ? 'update' : 'create',
            $journalEntry instanceof JournalEntry ? $journalEntry : JournalEntry::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_posted' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('organization_id', $orgId)
                    ->where('is_active', true),
            ],
            'lines.*.debit' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.credit' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
