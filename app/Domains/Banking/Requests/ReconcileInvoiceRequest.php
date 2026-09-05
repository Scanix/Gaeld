<?php

namespace App\Domains\Banking\Requests;

use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof BankTransaction
            && $this->user()?->can('update', $transaction->bankAccount) === true;
    }

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
                Rule::exists('invoices', 'id')
                    ->where('organization_id', $bankAccount->organization_id)
                    ->whereNull('deleted_at')
                    ->whereIn('status', [
                        InvoiceStatus::Sent->value,
                        InvoiceStatus::Overdue->value,
                        InvoiceStatus::Paid->value,
                    ]),
            ],
        ];
    }
}
