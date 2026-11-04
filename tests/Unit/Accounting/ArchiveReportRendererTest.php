<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Services\ArchiveReportRenderer;
use PHPUnit\Framework\TestCase;

class ArchiveReportRendererTest extends TestCase
{
    public function test_three_periods_with_extended_first_year_and_reclosing_preserve_exact_balances(): void
    {
        $snapshot = [
            'organization' => [['name' => 'Frozen legal name', 'currency' => 'CHF']],
            'accounts' => [
                ['id' => 'bank', 'code' => '1020', 'name' => 'Historical bank label', 'type' => 'asset'],
                ['id' => 'equity', 'code' => '2800', 'name' => 'Equity', 'type' => 'equity'],
                ['id' => 'income', 'code' => '3000', 'name' => 'Income', 'type' => 'revenue'],
                ['id' => 'expense', 'code' => '6000', 'name' => 'Expense', 'type' => 'expense'],
            ],
            'journal_entries' => [], 'transaction_lines' => [],
        ];
        $append = function (string $date, string $reference, array $amounts, ?string $type = null) use (&$snapshot): void {
            $snapshot['journal_entries'][] = ['id' => $reference, 'date' => $date, 'reference' => $reference, 'description' => $reference, 'type' => $type];
            foreach ($amounts as $account => [$debit, $credit]) {
                $snapshot['transaction_lines'][] = ['journal_entry_id' => $reference, 'account_id' => $account, 'debit' => $debit, 'credit' => $credit];
            }
        };
        $append('2026-07-08', 'CAPITAL', ['bank' => ['10000', '0'], 'equity' => ['0', '10000']]);
        $append('2027-01-15', 'SALE-2027', ['bank' => ['1000', '0'], 'income' => ['0', '1000']]);
        $append('2027-06-01', 'EXPENSE-2027', ['expense' => ['100', '0'], 'bank' => ['0', '100']]);
        $append('2027-12-31', 'CLOSE-2027', ['income' => ['1000', '0'], 'expense' => ['0', '100'], 'equity' => ['0', '900']], 'year_end_closing');
        $append('2028-01-01', 'OPENING-2028', ['bank' => ['10900', '0'], 'equity' => ['0', '10900']]);
        $append('2027-12-31', 'CORRECTION-2027', ['expense' => ['50', '0'], 'bank' => ['0', '50']]);
        $append('2027-12-31', 'CLOSE-2027-v2', ['expense' => ['0', '50'], 'equity' => ['50', '0']], 'year_end_closing');
        $append('2028-01-01', 'OPENING-2028-v2', ['bank' => ['0', '50'], 'equity' => ['50', '0']]);
        $append('2028-04-01', 'SALE-2028', ['bank' => ['200', '0'], 'income' => ['0', '200']]);
        $append('2028-12-31', 'CLOSE-2028', ['income' => ['200', '0'], 'equity' => ['0', '200']], 'year_end_closing');
        $append('2029-01-01', 'OPENING-2029', ['bank' => ['11050', '0'], 'equity' => ['0', '11050']]);
        $append('2029-04-01', 'SALE-2029', ['bank' => ['300', '0'], 'income' => ['0', '300']]);

        foreach ([
            ['2026-07-08', '2027-12-31', '10850.00', '850.00', null],
            ['2028-01-01', '2028-12-31', '11050.00', '200.00', '2028-01-01'],
            ['2029-01-01', '2029-12-31', '11350.00', '300.00', '2029-01-01'],
        ] as [$from, $to, $assets, $profit, $anchor]) {
            $periodSnapshot = $snapshot;
            $periodSnapshot['fiscal_year'] = [['start_date' => $from, 'end_date' => $to]];
            $periodSnapshot['journal_entries'] = array_values(array_filter($snapshot['journal_entries'], fn ($entry) => $entry['date'] <= $to));
            $ids = array_column($periodSnapshot['journal_entries'], 'id');
            $periodSnapshot['transaction_lines'] = array_values(array_filter($snapshot['transaction_lines'], fn ($line) => in_array($line['journal_entry_id'], $ids, true)));
            $report = (new ArchiveReportRenderer)->statements($periodSnapshot);
            $balances = array_column($report['balance_sheet'], 3, 1);
            $pnl = array_column($report['profit_and_loss'], 3, 1);
            $this->assertSame($assets, $balances['Historical bank label']);
            $this->assertSame($assets, $balances['Total assets']);
            $this->assertSame('0.00', $balances['Balance check (assets less liabilities and equity)']);
            $this->assertSame($profit, $pnl['Net result (excluding closing entries)']);
            $this->assertSame($anchor, $report['balance_from']);
        }
        $this->assertSame('Historical bank label', $snapshot['accounts'][0]['name']);
        $this->assertCount(12, $snapshot['journal_entries']);
    }
}
