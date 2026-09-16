<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        if (! $account instanceof Account) {
            return [];
        }

        $hasTransactions = $account->transactionLines()->exists();

        $rules = [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('organization_id', $account->organization_id),
                Rule::notIn([$account->id]),
            ],
        ];

        if (! $hasTransactions && ! $account->is_system) {
            $rules['code'] = [
                'required',
                'string',
                'max:10',
                Rule::unique('accounts', 'code')
                    ->where('organization_id', $account->organization_id)
                    ->ignore($account->id),
            ];
            $rules['type'] = ['required', new Enum(AccountType::class)];
        }

        return $rules;
    }
}
