<?php

namespace App\Domains\Accounting\Services;

use App\Support\Money;
use TCPDF;

/** Human-readable derivatives rendered exclusively from the frozen snapshot. */
class ArchiveReportRenderer
{
    /** @param array<string, mixed> $snapshot
     * @return array<string, string> filename => PDF bytes
     */
    public function render(array $snapshot): array
    {
        $statements = $this->statements($snapshot);

        return [
            'journal.pdf' => $this->pdf('Journal', $statements['heading'], ['Date', 'Reference', 'Entry', 'Account', 'Line', 'Debit', 'Credit'], $statements['journal']),
            'balance-sheet.pdf' => $this->pdf('Balance sheet', $statements['heading'], ['Account', 'Name', 'Type', 'Balance'], $statements['balance_sheet']),
            'profit-and-loss.pdf' => $this->pdf('Income statement', $statements['heading'], ['Account', 'Name', 'Type', 'Balance'], $statements['profit_and_loss']),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{heading:string, journal:list<array<mixed>>, balance_sheet:list<array<mixed>>, profit_and_loss:list<array<mixed>>, balance_from:string|null}
     */
    public function statements(array $snapshot): array
    {
        $year = $snapshot['fiscal_year'][0];
        $from = substr($year['start_date'], 0, 10);
        $to = substr($year['end_date'], 0, 10);
        $entries = array_column($snapshot['journal_entries'], null, 'id');
        $accounts = array_column($snapshot['accounts'], null, 'id');
        $journal = [];
        $balances = [];
        $pnl = [];
        $balanceFrom = null;
        foreach ($entries as $entry) {
            $date = substr($entry['date'], 0, 10);
            if ($date <= $from && preg_match('/^OPENING-\d{4}(?:-v\d+)?$/D', $entry['reference'] ?? '') === 1) {
                $balanceFrom = $balanceFrom === null ? $date : max($balanceFrom, $date);
            }
        }
        foreach ($snapshot['transaction_lines'] as $line) {
            $entry = $entries[$line['journal_entry_id']] ?? throw new \RuntimeException('Snapshot contains an orphan journal line.');
            $account = $accounts[$line['account_id']] ?? throw new \RuntimeException('Snapshot contains an unknown account.');
            $delta = Money::subtract($line['debit'], $line['credit']);
            if ($balanceFrom === null || substr($entry['date'], 0, 10) >= $balanceFrom) {
                $balances[$account['id']] = Money::add($balances[$account['id']] ?? '0', $delta);
            }
            if (substr($entry['date'], 0, 10) < $from) {
                continue;
            }
            $journal[] = [$entry['date'], $entry['reference'], $entry['description'], $account['code'], $line['description'] ?? '', $line['debit'], $line['credit']];
            if (($entry['type'] ?? null) !== 'year_end_closing') {
                $pnl[$account['id']] = Money::add($pnl[$account['id']] ?? '0', $delta);
            }
        }
        usort($journal, fn (array $left, array $right) => [$left[0], $left[1], $left[3]] <=> [$right[0], $right[1], $right[3]]);
        uasort($accounts, fn (array $left, array $right) => $left['code'] <=> $right['code']);
        $balanceRows = [];
        $pnlRows = [];
        $result = '0';
        $unclosedResult = '0';
        $totals = ['asset' => '0', 'liability' => '0', 'equity' => '0', 'revenue' => '0', 'expense' => '0'];
        foreach ($accounts as $account) {
            $isPnl = in_array($account['type'], ['expense', 'revenue'], true);
            $normalDebit = in_array($account['type'], ['asset', 'expense'], true);
            $amount = $balances[$account['id']] ?? '0';
            if ($isPnl) {
                $unclosedResult = Money::subtract($unclosedResult, $amount);
                $periodAmount = $pnl[$account['id']] ?? '0';
                $result = Money::subtract($result, $periodAmount);
                $normalAmount = $normalDebit ? $periodAmount : Money::negate($periodAmount);
                $totals[$account['type']] = Money::add($totals[$account['type']], $normalAmount);
                $pnlRows[] = [$account['code'], $account['name'], $account['type'], $normalAmount];
            } else {
                $normalAmount = $normalDebit ? $amount : Money::negate($amount);
                $totals[$account['type']] = Money::add($totals[$account['type']], $normalAmount);
                $balanceRows[] = [$account['code'], $account['name'], $account['type'], $normalAmount];
            }
        }
        $pnlRows[] = ['', 'Total revenue', '', $totals['revenue']];
        $pnlRows[] = ['', 'Total expenses', '', $totals['expense']];
        $pnlRows[] = ['', 'Net result (excluding closing entries)', '', $result];
        if (! Money::isZero($unclosedResult)) {
            $balanceRows[] = ['', 'Unallocated result', 'equity', $unclosedResult];
            $totals['equity'] = Money::add($totals['equity'], $unclosedResult);
        }
        $balanceRows[] = ['', 'Total assets', '', $totals['asset']];
        $balanceRows[] = ['', 'Total liabilities', '', $totals['liability']];
        $balanceRows[] = ['', 'Total equity', '', $totals['equity']];
        $balanceRows[] = ['', 'Balance check (assets less liabilities and equity)', '', Money::subtract($totals['asset'], Money::add($totals['liability'], $totals['equity']))];
        $heading = $snapshot['organization'][0]['name'].' — '.$from.' / '.$to.' — '.($snapshot['organization'][0]['currency'] ?? 'CHF');

        return [
            'heading' => $heading, 'journal' => $journal, 'balance_sheet' => $balanceRows,
            'profit_and_loss' => $pnlRows, 'balance_from' => $balanceFrom,
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<mixed>>  $rows
     */
    private function pdf(string $title, string $heading, array $columns, array $rows): string
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Gäld');
        $pdf->SetTitle($title);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->AddPage();
        $pdf->writeHTML('<h1>'.e($title).'</h1><p>'.e($heading).'</p><p>Derivative report generated from the package snapshot; supporting originals are preserved separately.</p>');
        foreach (array_chunk($rows ?: [array_fill(0, count($columns), '')], 75) as $chunk) {
            $html = '<table border="1" cellpadding="3"><thead><tr>';
            foreach ($columns as $column) {
                $html .= '<th>'.e($column).'</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($chunk as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>'.e((string) $value).'</td>';
                }
                $html .= '</tr>';
            }
            $pdf->writeHTML($html.'</tbody></table>');
        }

        return $pdf->Output('', 'S');
    }
}
