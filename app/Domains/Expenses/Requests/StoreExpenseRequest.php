<?php

namespace App\Domains\Expenses\Requests;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Requests\Concerns\ExpenseValidationRules;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return $this->sharedRules(app(CurrentOrganization::class)->id());
    }
}
