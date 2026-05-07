<?php

namespace Tests\Unit\Banking;

use App\Domains\Banking\Services\SwissBicResolver;
use PHPUnit\Framework\TestCase;

class SwissBicResolverTest extends TestCase
{
    private SwissBicResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SwissBicResolver;
    }

    public function test_resolves_postfinance(): void
    {
        // IID 09000
        $this->assertSame(
            'POFICHBEXXX',
            $this->resolver->resolveFromIban('CH0009000123456789012'),
        );
    }

    public function test_resolves_ubs_via_range(): void
    {
        // IID 00235 (UBS)
        $this->assertSame(
            'UBSWCHZH80A',
            $this->resolver->resolveFromIban('CH0000235123456789012'),
        );
    }

    public function test_resolves_zkb(): void
    {
        // IID 00700
        $this->assertSame(
            'ZKBKCHZZ80A',
            $this->resolver->resolveFromIban('CH00 0070 0123 4567 8901 2'),
        );
    }

    public function test_resolves_raiffeisen_via_range(): void
    {
        // IID 80808 (Raiffeisen branch)
        $this->assertSame(
            'RAIFCH22XXX',
            $this->resolver->resolveFromIban('CH0080808123456789012'),
        );
    }

    public function test_returns_null_for_unknown_iid(): void
    {
        // IID 09999 — not in the curated table.
        $this->assertNull($this->resolver->resolveFromIban('CH0009999123456789012'));
    }

    public function test_returns_null_for_non_swiss_iban(): void
    {
        $this->assertNull($this->resolver->resolveFromIban('DE89370400440532013000'));
    }

    public function test_returns_null_for_malformed_iban(): void
    {
        $this->assertNull($this->resolver->resolveFromIban('not-an-iban'));
        $this->assertNull($this->resolver->resolveFromIban(''));
        $this->assertNull($this->resolver->resolveFromIban(null));
    }

    public function test_normalises_whitespace_and_case(): void
    {
        $this->assertSame(
            'POFICHBEXXX',
            $this->resolver->resolveFromIban(' ch00 0900 0123 4567 8901 2 '),
        );
    }

    public function test_resolves_liechtenstein(): void
    {
        // IID 08800 (LLB)
        $this->assertSame(
            'LILALI2XXXX',
            $this->resolver->resolveFromIban('LI00 0880 0123 4567 8901 2'),
        );
    }

    public function test_resolves_directly_from_iid(): void
    {
        $this->assertSame('POFICHBEXXX', $this->resolver->resolveFromIid('09000'));
        $this->assertSame('UBSWCHZH80A', $this->resolver->resolveFromIid('00250'));
        $this->assertNull($this->resolver->resolveFromIid('99999'));
        $this->assertNull($this->resolver->resolveFromIid('abc'));
    }
}
