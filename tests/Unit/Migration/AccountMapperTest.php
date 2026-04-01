<?php

namespace Tests\Unit\Migration;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Migration\Mappers\FuzzyNameAccountMapper;
use App\Domains\Migration\Mappers\NumberPatternAccountMapper;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMapperTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Mapper Test Org',
            'currency' => 'CHF',
        ]);

        // Create a standard Swiss chart of accounts subset
        $accounts = [
            ['1020', 'Bank', AccountType::Asset],
            ['1100', 'Debitoren', AccountType::Asset],
            ['2000', 'Kreditoren', AccountType::Liability],
            ['2100', 'Bankverbindlichkeiten', AccountType::Liability],
            ['3000', 'Umsatz aus Lieferungen und Leistungen', AccountType::Revenue],
            ['3200', 'Übrige Erlöse', AccountType::Revenue],
            ['4000', 'Materialaufwand', AccountType::Expense],
            ['5000', 'Personalaufwand Löhne', AccountType::Expense],
            ['6000', 'Raumaufwand', AccountType::Expense],
            ['6500', 'Büroaufwand', AccountType::Expense],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            Account::create([
                'organization_id' => $this->organization->id,
                'code' => $code,
                'name' => $name,
                'type' => $type->value,
            ]);
        }
    }

    // ────────────────────────────────────────────────
    // NumberPatternAccountMapper
    // ────────────────────────────────────────────────

    public function test_number_pattern_exact_match_returns_full_confidence(): void
    {
        $mapper = new NumberPatternAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        $result = $mapper->suggest('1020', 'Bank', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertSame('1020', $result['account']->code);
        $this->assertSame(1.0, $result['confidence']);
    }

    public function test_number_pattern_prefix_match_returns_half_confidence(): void
    {
        $mapper = new NumberPatternAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        // 1050 doesn't exist but 10xx does (1020)
        $result = $mapper->suggest('1050', 'Kasse', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertSame('1020', $result['account']->code);
        $this->assertSame(0.5, $result['confidence']);
    }

    public function test_number_pattern_first_digit_match_returns_low_confidence(): void
    {
        $mapper = new NumberPatternAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        // 1800 doesn't exist, no 18xx, but 1xxx does
        $result = $mapper->suggest('1800', 'Something', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertTrue(str_starts_with($result['account']->code, '1'));
        $this->assertSame(0.3, $result['confidence']);
    }

    public function test_number_pattern_no_match_returns_zero_confidence(): void
    {
        $mapper = new NumberPatternAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        // 9xxx doesn't exist in our chart
        $result = $mapper->suggest('9999', 'NonExistent', $accounts);

        $this->assertNull($result['account']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_number_pattern_picks_closest_code_numerically(): void
    {
        $mapper = new NumberPatternAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        // 6200 → should pick 6000 (closer) over 6500
        $result = $mapper->suggest('6200', 'Misc expense', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertSame('6000', $result['account']->code);
    }

    // ────────────────────────────────────────────────
    // FuzzyNameAccountMapper
    // ────────────────────────────────────────────────

    public function test_fuzzy_name_exact_match_returns_high_confidence(): void
    {
        $mapper = new FuzzyNameAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        $result = $mapper->suggest('1020', 'Bank', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertSame('Bank', $result['account']->name);
        // Exact name match + exact code match → high confidence
        $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
    }

    public function test_fuzzy_name_partial_match(): void
    {
        $mapper = new FuzzyNameAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        $result = $mapper->suggest('5000', 'Personalaufwand', $accounts);

        $this->assertNotNull($result['account']);
        // Should match "Personalaufwand Löhne"
        $this->assertStringContainsString('Personalaufwand', $result['account']->name);
        $this->assertGreaterThan(0.3, $result['confidence']);
    }

    public function test_fuzzy_name_code_boost_with_matching_code(): void
    {
        $mapper = new FuzzyNameAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        // Same name ("Bank") but with matching code gets a code boost
        $withCode = $mapper->suggest('1020', 'Bank', $accounts);
        $withoutCode = $mapper->suggest('9999', 'Bank', $accounts);

        $this->assertGreaterThanOrEqual($withoutCode['confidence'], $withCode['confidence']);
    }

    public function test_fuzzy_name_case_insensitive(): void
    {
        $mapper = new FuzzyNameAccountMapper;
        $accounts = Account::where('organization_id', $this->organization->id)->get();

        $result = $mapper->suggest('1020', 'bank', $accounts);

        $this->assertNotNull($result['account']);
        $this->assertSame('Bank', $result['account']->name);
    }

    public function test_fuzzy_name_empty_accounts_returns_zero(): void
    {
        $mapper = new FuzzyNameAccountMapper;

        $result = $mapper->suggest('1020', 'Bank', collect());

        $this->assertNull($result['account']);
        $this->assertSame(0.0, $result['confidence']);
    }
}
