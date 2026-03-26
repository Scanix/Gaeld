<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunicationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_email_subject' => 'nullable|string|max:255',
            'invoice_email_body' => 'nullable|string|max:5000',
        ];
    }
}
