<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_header_text' => 'nullable|string|max:1000',
            'invoice_footer_text' => 'nullable|string|max:1000',
        ];
    }
}
