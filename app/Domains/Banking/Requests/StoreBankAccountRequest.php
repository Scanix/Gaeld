<?php

namespace App\Domains\Banking\Requests;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BankAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'name' => 'required|string|max:255',
            'iban' => 'nullable|string|max:34',
            'bank_name' => 'nullable|string|max:255',
            'bic' => 'nullable|string|max:11',
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where('organization_id', $orgId),
            ],
            'currency' => 'string|size:3',
            'is_mixed_use' => 'boolean',
        ];
    }
}
