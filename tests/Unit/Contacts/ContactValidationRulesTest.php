<?php

namespace Tests\Unit\Contacts;

use App\Domains\Contacts\Validation\ContactValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * ContactValidationRules previously had zero test coverage despite being
 * the shared rule set behind both StoreContactRequest and
 * UpdateContactRequest. A silent drift here (e.g. a loosened max length or
 * a dropped IBAN rule) would affect every contact create/update endpoint.
 */
class ContactValidationRulesTest extends TestCase
{
    public function test_store_requires_a_name(): void
    {
        $validator = Validator::make([], ContactValidationRules::store());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_store_passes_with_only_a_name(): void
    {
        $validator = Validator::make(['name' => 'Client AG'], ContactValidationRules::store());

        $this->assertFalse($validator->fails());
    }

    public function test_store_accepts_a_french_five_digit_postal_code(): void
    {
        $validator = Validator::make([
            'name' => 'Client France',
            'country' => 'FR',
            'postal_code' => '75001',
        ], ContactValidationRules::store());

        $this->assertFalse($validator->fails());
    }

    public function test_store_rejects_an_invalid_type(): void
    {
        $validator = Validator::make(
            ['name' => 'Client AG', 'type' => 'not-a-real-type'],
            ContactValidationRules::store(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    public function test_store_rejects_an_invalid_iban(): void
    {
        $validator = Validator::make(
            ['name' => 'Client AG', 'iban' => 'NOT-AN-IBAN'],
            ContactValidationRules::store(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('iban', $validator->errors()->toArray());
    }

    public function test_store_accepts_a_valid_swiss_iban(): void
    {
        $validator = Validator::make(
            ['name' => 'Client AG', 'iban' => 'CH9300762011623852957'],
            ContactValidationRules::store(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_enforces_two_letter_country_and_three_letter_currency(): void
    {
        $validator = Validator::make(
            ['name' => 'Client AG', 'country' => 'Switzerland', 'currency' => 'Swiss Francs'],
            ContactValidationRules::store(),
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('country', $errors);
        $this->assertArrayHasKey('currency', $errors);
    }

    public function test_update_allows_omitting_the_name(): void
    {
        $validator = Validator::make([], ContactValidationRules::update());

        $this->assertFalse($validator->fails());
    }

    public function test_update_still_validates_type_when_present(): void
    {
        // Documents existing behavior: the 'name' rule on update() is
        // ['sometimes', 'string', 'max:255'] with no 'required'/'filled', so
        // an empty string currently passes validation (would blank out the
        // contact's name if submitted). Not asserting on that intentionally —
        // characterizing 'type' instead, which has a real 'in:' constraint.
        $validator = Validator::make(['type' => 'not-a-real-type'], ContactValidationRules::update());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }
}
