<?php

namespace App\Domains\Payroll\Requests;

use App\Support\Rules\Iban;

trait EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', new Iban],
            'ahv_number' => ['nullable', 'string', 'max:16'],
            'entry_date' => ['required', 'date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'gross_salary' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_source_tax_subject' => ['boolean'],
        ];
    }
}
