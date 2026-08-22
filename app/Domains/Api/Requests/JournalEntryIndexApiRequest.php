<?php

namespace App\Domains\Api\Requests;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryIndexApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', JournalEntry::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'posted'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'reference' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
