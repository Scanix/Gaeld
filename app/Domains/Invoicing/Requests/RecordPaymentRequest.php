<?php

namespace App\Domains\Invoicing\Requests;

use App\Domains\Invoicing\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => 'nullable|string|max:100',
            'bank_account_code' => 'nullable|string|max:20',
        ];
    }
}
