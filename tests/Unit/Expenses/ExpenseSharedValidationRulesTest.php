<?php

namespace Tests\Unit\Expenses;

use App\Domains\Expenses\Validation\ExpenseSharedValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * ExpenseSharedValidationRules previously had zero test coverage despite
 * being the shared rule set behind both StoreExpenseRequest and
 * UpdateExpenseRequest. A regression here (e.g. allowing amount=0 or a
 * negative amount) would let invalid expense data reach the ledger.
 */
class ExpenseSharedValidationRulesTest extends TestCase
{
    private function validPayload(): array
    {
        return [
            'category' => 'Software',
            'amount' => 120.00,
            'date' => '2026-03-12',
        ];
    }

    public function test_store_passes_with_the_minimum_required_fields(): void
    {
        $validator = Validator::make($this->validPayload(), ExpenseSharedValidationRules::store());

        $this->assertFalse($validator->fails());
    }

    public function test_store_requires_category_amount_and_date(): void
    {
        $validator = Validator::make([], ExpenseSharedValidationRules::store());

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('category', $errors);
        $this->assertArrayHasKey('amount', $errors);
        $this->assertArrayHasKey('date', $errors);
    }

    public function test_store_rejects_zero_or_negative_amount(): void
    {
        foreach ([0, -10] as $amount) {
            $validator = Validator::make(
                [...$this->validPayload(), 'amount' => $amount],
                ExpenseSharedValidationRules::store(),
            );

            $this->assertTrue($validator->fails(), "Amount {$amount} should fail validation");
            $this->assertArrayHasKey('amount', $validator->errors()->toArray());
        }
    }

    public function test_store_rejects_a_negative_vat_amount(): void
    {
        $validator = Validator::make(
            [...$this->validPayload(), 'vat_amount' => -1],
            ExpenseSharedValidationRules::store(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vat_amount', $validator->errors()->toArray());
    }

    public function test_store_rejects_an_invalid_date(): void
    {
        $validator = Validator::make(
            [...$this->validPayload(), 'date' => 'not-a-date'],
            ExpenseSharedValidationRules::store(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date', $validator->errors()->toArray());
    }

    public function test_update_allows_omitting_all_fields(): void
    {
        $validator = Validator::make([], ExpenseSharedValidationRules::update());

        $this->assertFalse($validator->fails());
    }

    public function test_update_still_rejects_a_zero_amount_when_present(): void
    {
        $validator = Validator::make(['amount' => 0], ExpenseSharedValidationRules::update());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }
}
