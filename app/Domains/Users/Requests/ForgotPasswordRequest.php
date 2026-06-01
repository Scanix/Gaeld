<?php

namespace App\Domains\Users\Requests;

use App\Support\Rules\HCaptchaRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'h-captcha-response' => ['nullable', 'string', new HCaptchaRule],
        ];
    }
}
