<?php

namespace App\Domains\Api\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PostExpenseToLedgerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expense_account_code' => [
                'required',
                'string',
                'max:20',
            ],
            'bank_account_code' => [
                'nullable',
                'string',
                'max:20',
            ],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $expense = $this->route('expense');

            if (! $expense instanceof Expense) {
                return;
            }

            $organizationId = $expense->organization_id;
            $this->validateExistingAccount(
                $validator,
                (string) $this->input('expense_account_code'),
                $organizationId,
                'expense_account_code',
                AccountType::Expense,
            );
            $this->validateExistingAccount(
                $validator,
                (string) $this->input('bank_account_code'),
                $organizationId,
                'bank_account_code',
            );
        }];
    }

    private function validateExistingAccount(
        Validator $validator,
        string $code,
        string $organizationId,
        string $field,
        ?AccountType $requiredType = null,
    ): void {
        if ($code === '') {
            return;
        }

        $account = Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            return;
        }

        if (! $account->is_active) {
            $validator->errors()->add($field, __('app.account_must_be_active'));
        }

        if ($requiredType !== null && $account->type !== $requiredType) {
            $validator->errors()->add($field, __('app.expense_account_must_be_expense'));
        }
    }
}
