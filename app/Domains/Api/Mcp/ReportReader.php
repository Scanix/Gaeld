<?php

namespace App\Domains\Api\Mcp;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\AgingReportService;
use App\Domains\Reporting\Services\ReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

class ReportReader
{
    public function __construct(
        private CurrentOrganization $organization,
        private McpAccess $access,
        private ReportingService $reports,
        private AgingReportService $aging,
        private FiscalYearService $fiscalYears,
    ) {}

    /** @return array<string, mixed> */
    public function profitAndLoss(Request $request): array
    {
        Gate::allowIf($this->access->canReadReports(), 'Report access denied.');
        Gate::authorize('viewAny', Account::class);
        $arguments = $this->validatePeriod($request, false);
        $report = $this->reports->profitAndLoss($this->organization->id(), $arguments['from'], $arguments['to']);

        return $this->envelope('profit_and_loss', [
            'period' => $report['period'],
            'revenue' => array_map(fn (array $row): array => array_intersect_key($row, array_flip(['code', 'name', 'balance'])), $report['revenue']),
            'expenses' => array_map(fn (array $row): array => array_intersect_key($row, array_flip(['code', 'name', 'balance'])), $report['expenses']),
            'total_revenue' => $report['total_revenue'], 'total_expenses' => $report['total_expenses'],
            'net_profit' => $report['net_profit'],
        ], $this->organization->get()->currency, 'posted_ledger_base_currency');
    }

    /** @return array<string, mixed> */
    public function balanceSheet(Request $request): array
    {
        Gate::allowIf($this->access->canReadReports(), 'Report access denied.');
        Gate::authorize('viewAny', Account::class);
        $arguments = $this->validatePeriod($request, true);
        $report = $this->reports->balanceSheet($this->organization->id(), $arguments['as_of_date']);
        unset($report['comparison']);

        return $this->envelope('balance_sheet', $report, $this->organization->get()->currency, 'posted_ledger_base_currency');
    }

    /** @return array<string, mixed> */
    public function aging(Request $request): array
    {
        $this->rejectUnknown($request, ['type', 'as_of_date', 'currency', 'page', 'per_page']);
        $arguments = $request->validate([
            'type' => ['required', Rule::in(['receivables', 'payables'])],
            'as_of_date' => ['required', 'date_format:Y-m-d'],
            'currency' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{3}$/'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
        ]);
        Gate::allowIf($this->access->canReadAging($arguments['type']), 'Aging report access denied.');
        Gate::authorize('viewAny', Account::class);
        $currency = $arguments['currency'] ?? $this->organization->get()->currency;
        $report = $this->aging->generate($this->organization->id(), $arguments['type'], $arguments['as_of_date'], $currency);
        $items = [];
        $brackets = [];
        foreach ($report['brackets'] as $key => $bracket) {
            $brackets[$key] = ['total' => $bracket['total'], 'count' => count($bracket['items'])];
            array_push($items, ...$bracket['items']);
        }
        $page = (int) ($arguments['page'] ?? 1);
        $perPage = (int) ($arguments['per_page'] ?? 20);

        return $this->envelope('aging', [
            'type' => $report['type'], 'as_of_date' => $report['as_of_date'],
            'brackets' => $brackets, 'grand_total' => $report['grand_total'],
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => count($items), 'has_more' => $page * $perPage < count($items)],
            'notes' => [
                'Uses current document statuses and recorded outstanding amounts; the cutoff controls document dates and aging, not a historical state reconstruction.',
                'Payables include pending and approved expenses at gross value; their expense date is used as the aging reference, not a contractual due date.',
                'Totals cover all matching documents in the selected currency, including items on other pages.',
            ],
        ], $currency, 'current_open_documents');
    }

    /** @return array<string, string> */
    private function validatePeriod(Request $request, bool $balanceSheet): array
    {
        $keys = $balanceSheet ? ['fiscal_year_id', 'as_of_date'] : ['fiscal_year_id', 'from', 'to'];
        $this->rejectUnknown($request, $keys);
        $rules = [
            'fiscal_year_id' => ['sometimes', 'required', 'uuid', Rule::exists('fiscal_years', 'id')->where('organization_id', $this->organization->id())],
        ];
        if ($balanceSheet) {
            $rules['as_of_date'] = ['required_without:fiscal_year_id', Rule::prohibitedIf($request->has('fiscal_year_id')), 'date_format:Y-m-d'];
        } else {
            $rules['from'] = ['required_without:fiscal_year_id', Rule::prohibitedIf($request->has('fiscal_year_id')), 'date_format:Y-m-d'];
            $rules['to'] = ['required_without:fiscal_year_id', Rule::prohibitedIf($request->has('fiscal_year_id')), 'date_format:Y-m-d', 'after_or_equal:from'];
        }
        $arguments = $request->validate($rules);
        if (isset($arguments['fiscal_year_id'])) {
            $period = $this->fiscalYears->resolvePeriod($this->organization->get(), $arguments['fiscal_year_id']);

            return $balanceSheet ? ['as_of_date' => $period->toDate] : ['from' => $period->fromDate, 'to' => $period->toDate];
        }
        if (! $balanceSheet) {
            $request->validate(['to' => ['before_or_equal:'.CarbonImmutable::parse($arguments['from'])->addYears(2)->toDateString()]]);
        }

        return $arguments;
    }

    /** @param list<string> $keys */
    private function rejectUnknown(Request $request, array $keys): void
    {
        Validator::make(['arguments' => $request->all()], ['arguments' => 'array:'.implode(',', $keys)])->validate();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function envelope(string $type, array $data, string $currency, string $basis): array
    {
        return [
            'organization' => ['id' => $this->organization->id(), 'name' => $this->organization->get()->name],
            'report_type' => $type, 'currency' => $currency, 'basis' => $basis,
            'generated_at' => now()->toIso8601String(), 'cache_max_age_seconds' => 1800, 'data' => $data,
        ];
    }
}
