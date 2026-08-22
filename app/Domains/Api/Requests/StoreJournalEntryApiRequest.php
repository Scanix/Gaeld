<?php

namespace App\Domains\Api\Requests;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJournalEntryApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JournalEntry::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentOrganization = app(CurrentOrganization::class);
        $organizationId = $currentOrganization->isBound() ? $currentOrganization->id() : '0';
        $amountRule = ['required', 'string', 'regex:/^\d{1,11}(?:\.\d{1,2})?$/'];

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'posted'])],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true),
            ],
            'lines.*.debit' => $amountRule,
            'lines.*.credit' => $amountRule,
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('lines', []) as $index => $line) {
                $debit = (string) ($line['debit'] ?? '0');
                $credit = (string) ($line['credit'] ?? '0');
                $hasDebit = Money::isPositive($debit);
                $hasCredit = Money::isPositive($credit);

                if ($hasDebit === $hasCredit) {
                    $validator->errors()->add(
                        "lines.{$index}",
                        'Each line must contain a positive debit or credit, but not both.',
                    );
                }
            }
        }];
    }
}
