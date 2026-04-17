<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVatRateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vatRate = $this->route('vatRate');
        if (! $vatRate instanceof VatRate) {
            abort(404);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vat_rates', 'code')
                    ->where('organization_id', $vatRate->organization_id)
                    ->ignore($vatRate->id),
            ],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
