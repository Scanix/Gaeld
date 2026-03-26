<?php

namespace App\Domains\Expenses\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expense'));
    }

    public function rules(): array
    {
        $orgId = $this->route('expense')->organization_id;

        return [
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('organization_id', $orgId),
            ],
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
            'receipt' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.receipt').'|max:'.config('uploads.max_size.receipt'),
        ];
    }
}
