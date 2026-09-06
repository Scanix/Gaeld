<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(CurrentOrganization::class)->get()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_header_text' => 'nullable|string|max:1000',
            'invoice_footer_text' => 'nullable|string|max:1000',
            'default_invoice_notes' => 'nullable|string|max:1000',
        ];
    }
}
