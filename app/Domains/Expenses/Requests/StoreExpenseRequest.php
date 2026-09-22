<?php

namespace App\Domains\Expenses\Requests;

use App\Domains\Expenses\Enums\ExpenseAmountBasis;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Requests\Concerns\ExpenseValidationRules;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount_basis' => $this->input('amount_basis', ExpenseAmountBasis::Gross->value),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules(app(CurrentOrganization::class)->id());
    }
}
