<?php

namespace Tests\Unit\Requests;

use App\Domains\Contacts\Requests\StoreCustomerRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreCustomerRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new StoreCustomerRequest)->rules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules());
    }

    public function test_name_is_required(): void
    {
        $this->assertTrue($this->validate([])->fails());
        $this->assertArrayHasKey('name', $this->validate([])->errors()->toArray());
    }

    public function test_valid_customer_passes(): void
    {
        $this->assertFalse($this->validate([
            'name' => 'Acme AG',
            'email' => 'info@acme.ch',
            'country' => 'CH',
            'currency' => 'CHF',
        ])->fails());
    }

    public function test_email_must_be_valid(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'email' => 'not-an-email',
        ])->fails());
    }

    public function test_country_must_be_two_chars(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'country' => 'CHE',
        ])->fails());
    }

    public function test_currency_must_be_three_chars(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'currency' => 'CH',
        ])->fails());
    }

    public function test_type_must_be_organization_or_individual(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'type' => 'company',
        ])->fails());

        $this->assertFalse($this->validate([
            'name' => 'Test',
            'type' => 'organization',
        ])->fails());
    }

    public function test_name_max_length_enforced(): void
    {
        $this->assertTrue($this->validate([
            'name' => str_repeat('A', 256),
        ])->fails());
    }
}
