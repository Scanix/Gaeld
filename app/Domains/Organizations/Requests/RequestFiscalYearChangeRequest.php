<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RequestFiscalYearChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', app(CurrentOrganization::class)->get());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requested_start' => [
                'required',
                'string',
                'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $parts = explode('-', (string) $value);
                    $date = Carbon::create(2000, (int) $parts[0], (int) $parts[1]);

                    if ($date->format('m-d') !== (string) $value) {
                        $fail(__('app.fiscal_year_change_invalid_start'));
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
