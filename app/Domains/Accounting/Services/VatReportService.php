<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

/**
 * Generates Swiss VAT declaration data (AFC/ESTV form chiffres 200–510)
 * from posted journal entries for a given reporting period.
 */
class VatReportService
{
    /**
     * Generate a VAT report for the given period, matching Swiss AFC/ESTV form structure.
     *
     * Returns data for chiffres 200–510 of the Swiss VAT declaration.
     *
     * @param  string  $orgId  Organization UUID
     * @param  string  $fromDate  Period start (Y-m-d)
     * @param  string  $toDate  Period end (Y-m-d)
     * @return array{
     *   period: array{from: string, to: string},
     *   revenue_by_rate: array,
     *   total_revenue: string,
     *   output_vat_by_rate: array,
     *   total_output_vat: string,
     *   input_vat: string,
     *   net_vat: string,
     *   vat_payable: string,
     * }
     */
    /**
     * @return array<string, mixed>
     */
    public function generate(string $orgId, string $fromDate, string $toDate): array
    {
        $cacheKey = "vat_report:{$orgId}:{$fromDate}:{$toDate}";
        $orgTag = "org:{$orgId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($orgId, $fromDate, $toDate) {
            // Load all VatEntries for this org + period via the JournalEntry relationship
            $entries = VatEntry::with('vatRate')
                ->whereHas('journalEntry', function ($q) use ($orgId, $fromDate, $toDate) {
                    $q->where('organization_id', $orgId)
                        ->where('is_posted', true)
                        ->where('date', '>=', $fromDate)
                        ->where('date', '<=', $toDate);
                })
                ->get();

            // Separate Output (sales) and Input (purchases) entries
            $outputEntries = $entries->where('type', VatEntryType::Output);
            $inputEntries = $entries->where('type', VatEntryType::Input);

            // Aggregate Output by rate → chiffres 200 & 300
            $revenueByRate = [];
            $outputVatByRate = [];

            foreach ($outputEntries->groupBy('vat_rate_id') as $rateId => $rateEntries) {
                /** @var VatEntry $firstEntry */
                $firstEntry = $rateEntries->first();
                /** @var VatRate|null $vatRate */
                $vatRate = $firstEntry->vatRate;
                $rateName = $vatRate ? (string) $vatRate->name : 'Unknown';
                $rateValue = $vatRate ? number_format((float) $vatRate->rate, 2, '.', '') : '0.00';

                $baseAmount = '0.00';
                $vatAmount = '0.00';
                foreach ($rateEntries as $entry) {
                    $baseAmount = Money::add($baseAmount, (string) $entry->base_amount);
                    $vatAmount = Money::add($vatAmount, (string) $entry->vat_amount);
                }

                $revenueByRate[] = [
                    'rate_id' => $rateId,
                    'rate_name' => $rateName,
                    'rate' => $rateValue,
                    'base_amount' => $baseAmount,
                    'vat_amount' => $vatAmount,
                ];

                $outputVatByRate[] = [
                    'rate_id' => $rateId,
                    'rate_name' => $rateName,
                    'rate' => $rateValue,
                    'amount' => $vatAmount,
                ];
            }

            // Aggregate Input by rate → chiffre 400
            $totalInputVat = '0.00';
            foreach ($inputEntries as $entry) {
                $totalInputVat = Money::add($totalInputVat, (string) $entry->vat_amount);
            }

            // Totals
            $totalRevenue = array_reduce($revenueByRate, fn ($carry, $row) => Money::add($carry, $row['base_amount']), '0.00');
            $totalOutputVat = array_reduce($outputVatByRate, fn ($carry, $row) => Money::add($carry, $row['amount']), '0.00');
            $netVat = Money::subtract($totalOutputVat, $totalInputVat);

            return [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue_by_rate' => $revenueByRate,      // chiffre 200
                'total_revenue' => $totalRevenue,        // chiffre 299
                'output_vat_by_rate' => $outputVatByRate,  // chiffre 300
                'total_output_vat' => $totalOutputVat,      // chiffre 399
                'input_vat' => $totalInputVat,       // chiffre 400
                'net_vat' => $netVat,              // chiffre 500
                'vat_payable' => $netVat,              // chiffre 510 (same as net for standard method)
            ];
        });
    }
}
