<?php

namespace App\Domains\Expenses\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanReceiptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt' => 'required|file|mimes:'.config('uploads.allowed_mimes.image').'|max:'.config('uploads.max_size.document'),
        ];
    }
}
