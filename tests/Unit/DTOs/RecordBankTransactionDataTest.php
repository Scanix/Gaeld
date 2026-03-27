<?php

namespace Tests\Unit\DTOs;

use App\Domains\Banking\DTOs\RecordBankTransactionData;
use App\Domains\Banking\Enums\BankTransactionType;
use PHPUnit\Framework\TestCase;

class RecordBankTransactionDataTest extends TestCase
{
    public function test_from_array_casts_type_to_enum(): void
    {
        $dto = RecordBankTransactionData::fromArray([
            'date' => '2026-03-19',
            'amount' => 125.50,
            'type' => 'credit',
            'description' => 'Client payment',
            'reference' => 'BNK-123',
            'contra_account_code' => '1100',
        ]);

        $this->assertSame('2026-03-19', $dto->date);
        $this->assertSame('125.5', $dto->amount);
        $this->assertSame(BankTransactionType::Credit, $dto->type);
        $this->assertSame('1100', $dto->contraAccountCode);
    }

    public function test_to_array_returns_scalar_payload(): void
    {
        $dto = new RecordBankTransactionData(
            date: '2026-03-19',
            amount: '125.50',
            type: BankTransactionType::Debit,
            description: 'Vendor payment',
            reference: 'BNK-456',
            contraAccountCode: '2000',
        );

        $this->assertSame([
            'date' => '2026-03-19',
            'amount' => '125.50',
            'type' => 'debit',
            'description' => 'Vendor payment',
            'reference' => 'BNK-456',
            'contra_account_code' => '2000',
        ], $dto->toArray());
    }
}
