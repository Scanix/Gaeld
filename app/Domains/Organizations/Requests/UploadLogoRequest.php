<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'logo' => 'required|image|mimes:png,jpg,jpeg|max:'.config('uploads.max_size.image'),
        ];
    }
}
