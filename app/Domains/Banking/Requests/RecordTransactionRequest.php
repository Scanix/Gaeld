<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bankAccount'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:credit,debit',
            'reference' => 'nullable|string|max:100',
            'contra_account_code' => 'required|string|max:10',
        ];
    }
}
