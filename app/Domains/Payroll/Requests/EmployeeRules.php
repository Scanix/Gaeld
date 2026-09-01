<?php

namespace App\Domains\Payroll\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\FeatureFlag;
use App\Support\Rules\Iban;

trait EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
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
            'has_thirteenth_salary' => ['boolean'],
        ];

        $currentOrganization = app(CurrentOrganization::class);
        $withholdingTaxEnabled = $currentOrganization->isBound()
            ? FeatureFlag::enabledForOrg('withholding_tax', $currentOrganization->get())
            : FeatureFlag::enabled('withholding_tax');

        if ($withholdingTaxEnabled) {
            $rules += [
                'source_tax_canton' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'source_tax_tariff' => ['nullable', 'string', 'size:1', 'in:A,B,C,D,E,F,G,H'],
                'source_tax_municipality_code' => ['nullable', 'string', 'max:10'],
            ];
        }

        return $rules;
    }
}
