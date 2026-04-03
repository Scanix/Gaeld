<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Queries\InvoiceReportingQuery;
use App\Support\DTOs\SummaryResult;
use Illuminate\Support\Collection;

/**
 * Façade for backward compatibility: delegates accounting writes to
 * InvoiceAccountingService and reporting reads to InvoiceReportingQuery.
 *
 * Existing callers can keep injecting InvoiceService without changes.
 *
 * @deprecated Inject InvoiceAccountingService or InvoiceReportingQuery directly for new code.
 */
class InvoiceService
{
    public function __construct(
        private InvoiceAccountingService $accountingService,
        private InvoiceReportingQuery $reportingQuery,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Accounting writes (delegated)
    // ──────────────────────────────────────────────────────────────

    public function postToLedger(Invoice $invoice): Invoice
    {
        return $this->accountingService->postToLedger($invoice);
    }

    public function recordPayment(Invoice $invoice, RecordPaymentData $data): InvoicePayment
    {
        return $this->accountingService->recordPayment($invoice, $data);
    }

    // ──────────────────────────────────────────────────────────────
    //  Reporting reads (delegated)
    // ──────────────────────────────────────────────────────────────

    public function yearlyRevenue(string $orgId, int $year): string
    {
        return $this->reportingQuery->yearlyRevenue($orgId, $year);
    }

    public function unpaidSummary(string $orgId): SummaryResult
    {
        return $this->reportingQuery->unpaidSummary($orgId);
    }

    public function paidInYear(string $orgId, int $year): Collection
    {
        return $this->reportingQuery->paidInYear($orgId, $year);
    }

    public function sentOrOverdueDueInYear(string $orgId, int $year): Collection
    {
        return $this->reportingQuery->sentOrOverdueDueInYear($orgId, $year);
    }

    public function hasMatchingQrReference(string $organizationId, string $reference): bool
    {
        return $this->reportingQuery->hasMatchingQrReference($organizationId, $reference);
    }
}
