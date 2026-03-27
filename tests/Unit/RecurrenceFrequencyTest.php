<?php

namespace Tests\Unit;

use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecurrenceFrequencyTest extends TestCase
{
    #[DataProvider('nextDateProvider')]
    public function test_next_date(RecurrenceFrequency $frequency, string $from, string $expected): void
    {
        $result = $frequency->nextDate(Carbon::parse($from));

        $this->assertSame($expected, $result->toDateString());
    }

    public static function nextDateProvider(): array
    {
        return [
            'weekly' => [RecurrenceFrequency::Weekly, '2026-01-01', '2026-01-08'],
            'monthly' => [RecurrenceFrequency::Monthly, '2026-01-15', '2026-02-15'],
            'monthly end of month' => [RecurrenceFrequency::Monthly, '2026-01-31', '2026-03-03'],
            'quarterly' => [RecurrenceFrequency::Quarterly, '2026-01-01', '2026-04-01'],
            'yearly' => [RecurrenceFrequency::Yearly, '2026-03-15', '2027-03-15'],
        ];
    }

    public function test_all_cases_exist(): void
    {
        $this->assertCount(4, RecurrenceFrequency::cases());
    }

    public function test_values(): void
    {
        $this->assertSame('weekly', RecurrenceFrequency::Weekly->value);
        $this->assertSame('monthly', RecurrenceFrequency::Monthly->value);
        $this->assertSame('quarterly', RecurrenceFrequency::Quarterly->value);
        $this->assertSame('yearly', RecurrenceFrequency::Yearly->value);
    }
}
