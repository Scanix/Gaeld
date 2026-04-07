<?php

namespace Tests\Unit\Rules;

use App\Support\Rules\QrIban;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class QrIbanRuleTest extends TestCase
{
    private function validate(mixed $value): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['qr_iban' => $value],
            ['qr_iban' => ['nullable', 'string', new QrIban]]
        );
    }

    public function test_valid_qr_iban_passes(): void
    {
        // IID 31999 is in range 30000–31999
        $this->assertFalse($this->validate('CH4431999123000889012')->fails());
    }

    public function test_valid_qr_iban_with_spaces_passes(): void
    {
        $this->assertFalse($this->validate('CH44 3199 9123 0008 8901 2')->fails());
    }

    public function test_valid_qr_iban_with_iid_30000_passes(): void
    {
        // IID 30000 is the lower bound
        $this->assertFalse($this->validate('CH5630000123456780009')->fails());
    }

    public function test_empty_value_passes(): void
    {
        $this->assertFalse($this->validate('')->fails());
        $this->assertFalse($this->validate(null)->fails());
    }

    public function test_regular_swiss_iban_fails(): void
    {
        // IID 00762 is outside QR range
        $v = $this->validate('CH9300762011623852957');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('qr_iban', $v->errors()->toArray());
    }

    public function test_foreign_iban_fails(): void
    {
        $v = $this->validate('DE89370400440532013000');
        $this->assertTrue($v->fails());
    }

    public function test_invalid_format_fails(): void
    {
        $v = $this->validate('NOT-AN-IBAN');
        $this->assertTrue($v->fails());
    }

    public function test_iid_just_below_range_fails(): void
    {
        // IID 29999 is just below the QR range
        $this->assertTrue($this->validate('CH1029999123456780009')->fails());
    }

    public function test_iid_just_above_range_fails(): void
    {
        // IID 32000 is just above the QR range
        $this->assertTrue($this->validate('CH1032000123456780009')->fails());
    }

    public function test_liechtenstein_prefix_is_accepted(): void
    {
        // Verify the rule doesn't reject LI as a country prefix (it fails checksum, not country)
        $v = $this->validate('LI21300101234567890AB');
        $errors = $v->errors()->toArray();
        // If it fails, it should be for IBAN format, not for the "swiss only" rule
        $this->assertTrue($v->fails());
        $errorMsg = $errors['qr_iban'][0] ?? '';
        $this->assertStringNotContainsString('Swiss', $errorMsg);
        $this->assertStringNotContainsString('Liechtenstein', $errorMsg);
    }
}
