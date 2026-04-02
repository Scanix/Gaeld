<?php

namespace App\Domains\Expenses\Requests;

use App\Domains\Expenses\Requests\Concerns\ExpenseValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expense'));
    }

    public function rules(): array
    {
        return $this->sharedRules($this->route('expense')->organization_id);
    }
}
