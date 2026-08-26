<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Actions\PostVatSettlementAction;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Requests\VatReportRequest;
use App\Domains\Reporting\Services\ExportReportService;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * VAT report generation, export, and settlement posting.
 */
class VatReportController extends Controller
{
    use HandlesFlashErrorResponses;

    public function vatReport(Request $request, VatReportService $service, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $period = $this->resolvePeriod($request);
        $from = $period['from'];
        $to = $period['to'];

        $report = $service->generate($currentOrg->id(), $from, $to);

        $settlementReference = "VAT-SETTLEMENT-{$from}-{$to}";

        // The settlement may have been re-posted under a versioned reference
        // (e.g. "-v2") after an earlier attempt for this period was reversed —
        // see PostVatSettlementAction::resolveReference(). Look at the most
        // recent posted attempt, not just the original base reference.
        $latestSettlement = JournalEntry::where('organization_id', $currentOrg->id())
            ->where('is_posted', true)
            ->where(function ($query) use ($settlementReference) {
                $query->where('reference', $settlementReference)
                    ->orWhere('reference', 'like', "{$settlementReference}-v%");
            })
            ->orderByDesc('created_at')
            ->first();

        $isSettled = false;
        if ($latestSettlement) {
            $isReversed = JournalEntry::where('organization_id', $currentOrg->id())
                ->where('reference', "REV-{$latestSettlement->reference}")
                ->where('is_posted', true)
                ->exists();

            $isSettled = ! $isReversed;
        }

        $report['is_settled'] = $isSettled;

        return Inertia::render('Reports/VatReport', [
            'report' => $report,
        ]);
    }

    /**
     * @return array{from: string, to: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->string('from_date')->toString() ?: $request->string('from')->toString();
        $to = $request->string('to_date')->toString() ?: $request->string('to')->toString();
        $period = $request->string('period')->toString();

        if (($from === '' || $to === '') && preg_match('/^Q([1-4])\s+(\d{4})$/', trim($period), $matches) === 1) {
            $quarterStart = Carbon::create(
                (int) $matches[2],
                (((int) $matches[1] - 1) * 3) + 1,
                1,
            );

            $from = $from !== '' ? $from : $quarterStart->copy()->startOfQuarter()->toDateString();
            $to = $to !== '' ? $to : $quarterStart->copy()->endOfQuarter()->toDateString();
        }

        return [
            'from' => $from !== '' ? $from : now()->startOfQuarter()->toDateString(),
            'to' => $to !== '' ? $to : now()->endOfQuarter()->toDateString(),
        ];
    }

    public function exportVatReport(
        VatReportRequest $request,
        VatReportService $service,
        CurrentOrganization $currentOrg,
        ExportReportService $exporter,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $report = $service->generate($currentOrg->id(), $validated['from_date'], $validated['to_date']);
        $org = $currentOrg->get();
        $from = $validated['from_date'];
        $to = $validated['to_date'];

        return $exporter->export(
            $format,
            csvBuilder: function () use ($exporter, $report, $from, $to) {
                $headers = ['Chiffre', 'Description', 'Base amount', 'VAT amount'];
                $rows = [];
                foreach ($report['revenue_by_rate'] as $line) {
                    $rows[] = ['200', $line['rate'].'%', $line['base_amount'], $line['vat_amount']];
                }
                $rows[] = ['299', 'Total revenue', $report['total_revenue'], ''];
                foreach ($report['output_vat_by_rate'] as $line) {
                    $rows[] = ['300', $line['rate'].'%', $line['base_amount'], $line['vat_amount']];
                }
                $rows[] = ['399', 'Total output VAT', '', $report['total_output_vat']];
                $rows[] = ['400', 'Input VAT', '', $report['input_vat']];
                $rows[] = ['500', 'Net VAT', '', $report['net_vat']];
                $rows[] = ['510', 'VAT payable', '', $report['vat_payable']];

                return $exporter->csv()->export($headers, $rows, "vat-report-{$from}-{$to}.csv");
            },
            pdfBuilder: fn () => $exporter->pdf()->download('exports.vat-report', [
                'organization' => $org,
                'report' => $report,
            ], "vat-report-{$from}-{$to}.pdf"),
        );
    }

    public function postVatSettlement(
        VatReportRequest $request,
        PostVatSettlementAction $action,
        CurrentOrganization $currentOrg,
    ): RedirectResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();

        try {
            $action->execute($currentOrg->id(), $validated['from_date'], $validated['to_date']);
        } catch (DuplicateReferenceException) {
            return $this->backWithError(__('app.vat_settlement_already_posted'));
        }

        return redirect()->route('reports.vat', [
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
        ])->with('success', __('app.vat_settlement_posted'));
    }
}
