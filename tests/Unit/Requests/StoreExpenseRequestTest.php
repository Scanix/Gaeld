<?php

namespace Tests\Unit\Requests;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreExpenseRequestTest extends TestCase
{
    private function rules(): array
    {
        return [
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
        ];
    }

    private function validData(): array
    {
        return [
            'category' => 'Office Supplies',
            'amount' => 49.90,
            'date' => '2025-03-15',
            'currency' => 'CHF',
        ];
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules());
    }

    public function test_valid_expense_data_passes(): void
    {
        $validator = $this->validate($this->validData());
        $this->assertTrue($validator->passes());
    }

    public function test_category_is_required(): void
    {
        $data = $this->validData();
        unset($data['category']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category', $validator->errors()->toArray());
    }

    public function test_category_max_length_is_100(): void
    {
        $data = $this->validData();
        $data['category'] = str_repeat('A', 101);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_amount_is_required(): void
    {
        $data = $this->validData();
        unset($data['amount']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    public function test_amount_must_be_positive(): void
    {
        $data = $this->validData();
        $data['amount'] = 0;

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_amount_cannot_be_negative(): void
    {
        $data = $this->validData();
        $data['amount'] = -10;

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_vat_amount_cannot_be_negative(): void
    {
        $data = $this->validData();
        $data['vat_amount'] = -5;

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_date_is_required(): void
    {
        $data = $this->validData();
        unset($data['date']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date', $validator->errors()->toArray());
    }

    public function test_date_must_be_valid(): void
    {
        $data = $this->validData();
        $data['date'] = 'not-a-date';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_currency_must_be_exactly_3_characters(): void
    {
        $data = $this->validData();
        $data['currency'] = 'ABCD';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_vendor_max_length_is_255(): void
    {
        $data = $this->validData();
        $data['vendor'] = str_repeat('X', 256);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }
}
