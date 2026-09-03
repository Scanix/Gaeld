<?php

namespace App\Domains\Api\Requests;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankAccount::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentOrganization = app(CurrentOrganization::class);
        $organizationId = $currentOrganization->isBound() ? $currentOrganization->id() : '0';

        return [
            'name' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'qr_iban' => ['nullable', 'string', 'max:34'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bic' => ['nullable', 'string', 'max:11'],
            'account_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true),
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_mixed_use' => ['sometimes', 'boolean'],
            'is_default_for_invoicing' => ['sometimes', 'boolean'],
        ];
    }
}
