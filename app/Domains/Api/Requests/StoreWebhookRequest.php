<?php

namespace App\Domains\Api\Requests;

use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Models\Webhook;
use App\Support\Rules\ValidWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Webhook::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => 'required|array|min:1',
            'events.*' => ['required', 'string', $this->webhookEventRule()],
            'is_active' => 'boolean',
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
