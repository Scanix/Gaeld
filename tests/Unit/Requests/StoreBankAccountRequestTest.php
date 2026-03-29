<?php

namespace Tests\Unit\Requests;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreBankAccountRequestTest extends TestCase
{
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'iban' => 'nullable|string|max:34',
            'bank_name' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
        ];
    }

    private function validData(): array
    {
        return [
            'name' => 'Business Account',
            'iban' => 'CH9300762011623852957',
            'bank_name' => 'PostFinance',
            'currency' => 'CHF',
        ];
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules());
    }

    public function test_valid_bank_account_data_passes(): void
    {
        $validator = $this->validate($this->validData());
        $this->assertTrue($validator->passes());
    }

    public function test_name_is_required(): void
    {
        $data = $this->validData();
        unset($data['name']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_name_max_length_is_255(): void
    {
        $data = $this->validData();
        $data['name'] = str_repeat('A', 256);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_iban_max_length_is_34(): void
    {
        $data = $this->validData();
        $data['iban'] = str_repeat('X', 35);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_currency_must_be_exactly_3_characters(): void
    {
        $data = $this->validData();
        $data['currency'] = 'CH';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_minimal_valid_data(): void
    {
        $validator = $this->validate(['name' => 'Test', 'currency' => 'CHF']);
        $this->assertTrue($validator->passes());
    }
}
