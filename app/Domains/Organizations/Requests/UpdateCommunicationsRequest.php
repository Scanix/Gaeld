<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunicationsRequest extends FormRequest
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
            'invoice_email_subject' => 'nullable|string|max:255',
            'invoice_email_body' => 'nullable|string|max:5000',
        ];
    }
}
