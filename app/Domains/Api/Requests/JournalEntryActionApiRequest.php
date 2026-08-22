<?php

namespace App\Domains\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalEntryActionApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
