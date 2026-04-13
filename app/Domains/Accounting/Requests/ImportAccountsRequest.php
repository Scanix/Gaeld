<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportAccountsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:'.config('uploads.allowed_mimes.import').'|max:'.config('uploads.max_size.import'),
            'mode' => 'required|in:add,replace',
        ];
    }
}
