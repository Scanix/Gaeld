<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchivePackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ArchivePackage::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'uuid', Rule::exists('fiscal_years', 'id')
                ->where('organization_id', app(CurrentOrganization::class)->id())],
            'archival_pdf' => ['sometimes', 'boolean', 'declined'],
        ];
    }
}
