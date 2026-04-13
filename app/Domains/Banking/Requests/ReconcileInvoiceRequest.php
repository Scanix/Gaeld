<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bankAccount = $this->route('transaction')->bankAccount;

        return [
            'invoice_id' => [
                'required',
                'uuid',
                Rule::exists('invoices', 'id')->where('organization_id', $bankAccount->organization_id),
            ],
        ];
    }
}
