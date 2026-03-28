<?php

namespace Tests\Unit\Requests;

use App\Domains\Api\Requests\UpdateWebhookRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateWebhookRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new UpdateWebhookRequest;
        $request->merge($data);

        return Validator::make($data, $request->rules());
    }

    public function test_valid_update_with_all_fields(): void
    {
        $validator = $this->validate([
            'url' => 'https://example.com/webhook',
            'events' => ['invoice.created', 'customer.updated'],
            'is_active' => true,
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_all_fields_are_optional(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->passes());
    }

    public function test_url_must_be_valid(): void
    {
        $validator = $this->validate(['url' => 'not-a-url']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('url', $validator->errors()->toArray());
    }

    public function test_url_max_length_is_2048(): void
    {
        $validator = $this->validate(['url' => 'https://example.com/' . str_repeat('a', 2030)]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('url', $validator->errors()->toArray());
    }

    public function test_events_must_be_array(): void
    {
        $validator = $this->validate(['events' => 'invoice.created']);

        $this->assertTrue($validator->fails());
    }

    public function test_events_cannot_be_empty_array(): void
    {
        $validator = $this->validate(['events' => []]);

        $this->assertTrue($validator->fails());
    }

    public function test_invalid_event_fails(): void
    {
        $validator = $this->validate(['events' => ['not.a.real.event']]);

        $this->assertTrue($validator->fails());
    }

    public function test_is_active_must_be_boolean(): void
    {
        $validator = $this->validate(['is_active' => 'yes']);

        $this->assertTrue($validator->fails());
    }
}
