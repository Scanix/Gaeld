<?php

namespace App\Domains\Banking\Requests;

use App\Domains\Banking\Models\BankAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportCamtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bankAccount = $this->route('bankAccount');

        return $bankAccount instanceof BankAccount
            && $this->user()?->can('update', $bankAccount) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'camt_file' => [
                'required',
                'file',
                'extensions:xml,csv,sta,mt940,mt9,fin,swi',
                'mimes:xml,csv,txt',
                'max:'.config('uploads.max_size.document'),
            ],
            'csv_mapping' => ['nullable', 'array', 'max:20'],
            'csv_mapping.date' => 'required_if:csv_mapping,!null|integer|min:0',
            'csv_mapping.amount' => 'required_if:csv_mapping,!null|integer|min:0',
            'csv_mapping.description' => 'nullable|integer|min:0',
            'csv_mapping.reference' => 'nullable|integer|min:0',
            'csv_delimiter' => ['nullable', 'string', Rule::in([',', ';', "\t", '|'])],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new ValidationException($validator);
        }

        throw new HttpResponseException(
            redirect()->back()
                ->withErrors($validator)
                ->withInput(),
        );
    }
}
