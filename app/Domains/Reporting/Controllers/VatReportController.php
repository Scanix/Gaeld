<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Actions\PostVatSettlementAction;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Requests\VatReportRequest;
use App\Domains\Reporting\Services\ExportReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * VAT report generation, export, and settlement posting.
 */
class VatReportController extends Controller
{
    public function vatReport(Request $request, VatReportService $service, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from_date', $request->input('from', now()->startOfQuarter()->toDateString()));
        $to = $request->input('to_date', $request->input('to', now()->endOfQuarter()->toDateString()));

        $report = $service->generate($currentOrg->id(), $from, $to);

        return Inertia::render('Reports/VatReport', [
            'report' => $report,
        ]);
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
        $action->execute($currentOrg->id(), $validated['from_date'], $validated['to_date']);

        return redirect()->route('reports.vat', [
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
        ])->with('success', __('app.vat_settlement_posted'));
    }
}
