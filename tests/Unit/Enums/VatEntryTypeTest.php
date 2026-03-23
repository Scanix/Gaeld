<?php

namespace Tests\Unit\Enums;

use App\Domains\Accounting\Enums\VatEntryType;
use PHPUnit\Framework\TestCase;

class VatEntryTypeTest extends TestCase
{
    public function test_input_has_correct_value(): void
    {
        $this->assertSame('input', VatEntryType::Input->value);
    }

    public function test_output_has_correct_value(): void
    {
        $this->assertSame('output', VatEntryType::Output->value);
    }

    public function test_from_valid_values(): void
    {
        $this->assertSame(VatEntryType::Input, VatEntryType::from('input'));
        $this->assertSame(VatEntryType::Output, VatEntryType::from('output'));
    }
}
