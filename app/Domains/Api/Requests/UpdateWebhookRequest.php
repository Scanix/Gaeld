<?php

namespace App\Domains\Api\Requests;

use App\Domains\Api\Enums\WebhookEvent;
use App\Support\Rules\ValidWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => 'sometimes|array|min:1',
            'events.*' => ['required', 'string', $this->webhookEventRule()],
            'is_active' => 'sometimes|boolean',
        ];
    }

    private function webhookEventRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! WebhookEvent::isValid($value)) {
                $fail("The event '{$value}' is not a valid webhook event.");
            }
        };
    }
}
