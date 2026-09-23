<?php

namespace App\Domains\Invoicing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updatePayment', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
        ];
    }
}
