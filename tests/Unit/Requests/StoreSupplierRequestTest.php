<?php

namespace Tests\Unit\Requests;

use App\Domains\Contacts\Requests\StoreSupplierRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSupplierRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new StoreSupplierRequest)->rules();
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

    public function test_valid_supplier_passes(): void
    {
        $this->assertFalse($this->validate([
            'name' => 'Supplies GmbH',
            'email' => 'info@supplies.ch',
            'country' => 'CH',
            'currency' => 'CHF',
            'iban' => 'CH9300762011623852957',
        ])->fails());
    }

    public function test_email_must_be_valid(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'email' => 'bad-email',
        ])->fails());
    }

    public function test_iban_max_length_enforced(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'iban' => str_repeat('A', 35),
        ])->fails());
    }

    public function test_default_expense_category_max_length(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'default_expense_category' => str_repeat('A', 101),
        ])->fails());
    }

    public function test_country_must_be_two_chars(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'country' => 'CHE',
        ])->fails());
    }

    public function test_type_must_be_valid(): void
    {
        $this->assertTrue($this->validate([
            'name' => 'Test',
            'type' => 'vendor',
        ])->fails());

        $this->assertFalse($this->validate([
            'name' => 'Test',
            'type' => 'individual',
        ])->fails());
    }
}
