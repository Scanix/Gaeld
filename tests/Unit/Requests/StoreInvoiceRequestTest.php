<?php

namespace Tests\Unit\Requests;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreInvoiceRequestTest extends TestCase
{
    private function rules(): array
    {
        // Extract rules without org-scoped exists() to test pure validation
        return [
            'number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'currency' => 'string|size:3',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'customer_id' => 'required|integer',
        ];
    }

    private function validData(): array
    {
        return [
            'number' => 'INV-001',
            'issue_date' => '2025-01-01',
            'due_date' => '2025-01-31',
            'currency' => 'CHF',
            'customer_id' => 1,
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100],
            ],
        ];
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules());
    }

    public function test_valid_invoice_data_passes(): void
    {
        $validator = $this->validate($this->validData());
        $this->assertTrue($validator->passes());
    }

    public function test_number_is_required(): void
    {
        $data = $this->validData();
        unset($data['number']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('number', $validator->errors()->toArray());
    }

    public function test_number_max_length_is_50(): void
    {
        $data = $this->validData();
        $data['number'] = str_repeat('A', 51);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_issue_date_is_required(): void
    {
        $data = $this->validData();
        unset($data['issue_date']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('issue_date', $validator->errors()->toArray());
    }

    public function test_issue_date_must_be_valid_date(): void
    {
        $data = $this->validData();
        $data['issue_date'] = 'not-a-date';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_due_date_must_be_after_or_equal_issue_date(): void
    {
        $data = $this->validData();
        $data['issue_date'] = '2025-02-01';
        $data['due_date'] = '2025-01-15';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('due_date', $validator->errors()->toArray());
    }

    public function test_currency_must_be_exactly_3_characters(): void
    {
        $data = $this->validData();
        $data['currency'] = 'CH';

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_lines_are_required(): void
    {
        $data = $this->validData();
        unset($data['lines']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lines', $validator->errors()->toArray());
    }

    public function test_lines_cannot_be_empty_array(): void
    {
        $data = $this->validData();
        $data['lines'] = [];

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
    }

    public function test_line_description_is_required(): void
    {
        $data = $this->validData();
        $data['lines'] = [['quantity' => 1, 'unit_price' => 100]];

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lines.0.description', $validator->errors()->toArray());
    }

    public function test_line_quantity_must_be_positive(): void
    {
        $data = $this->validData();
        $data['lines'] = [['description' => 'X', 'quantity' => 0, 'unit_price' => 100]];

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lines.0.quantity', $validator->errors()->toArray());
    }

    public function test_line_unit_price_cannot_be_negative(): void
    {
        $data = $this->validData();
        $data['lines'] = [['description' => 'X', 'quantity' => 1, 'unit_price' => -5]];

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lines.0.unit_price', $validator->errors()->toArray());
    }

    public function test_customer_id_is_required(): void
    {
        $data = $this->validData();
        unset($data['customer_id']);

        $validator = $this->validate($data);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('customer_id', $validator->errors()->toArray());
    }
}
