<?php

namespace App\Domains\Invoicing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecurringInvoiceRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $rules = [
            'customer_id' => ['required', 'exists:customers,id'],
            'frequency' => ['required', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'next_issue_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_issue_date'],
            'template_data' => ['required', 'array'],
            'template_data.lines' => ['required', 'array', 'min:1'],
            'template_data.lines.*.description' => ['required', 'string', 'max:500'],
            'template_data.lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'template_data.lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'template_data.notes' => ['nullable', 'string', 'max:2000'],
            'template_data.payment_terms' => ['nullable', 'string', 'max:255'],
            'template_data.currency' => ['nullable', 'string', 'size:3'],
        ];

        if ($this->isMethod('POST')) {
            $rules['next_issue_date'][] = 'after_or_equal:today';
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['is_active'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
