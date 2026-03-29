<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;

/**
 * Provides a guided getting-started checklist for new organizations,
 * checking completion of key onboarding steps (accounts, invoices, etc.).
 */
class ChecklistService
{
    /**
     * Returns a list of checklist items for the current organization.
     *
     * Each item has:
     *   - key: string
     *   - done: bool
     *   - href: string|null
     *
     * @return array<int, array{key: string, done: bool, href: string|null}>
     */
    public function checklist(string $organizationId): array
    {
        $chartConfigured = Account::where('organization_id', $organizationId)->exists();

        $invoicesCreated = Invoice::where('organization_id', $organizationId)->exists();

        $expensesPosted = Expense::where('organization_id', $organizationId)
            ->where('status', 'posted')
            ->exists();

        $bankImported = BankImport::where('organization_id', $organizationId)->exists();

        $reconciliationDone = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'bank_match')
            ->exists();

        $vatDeclared = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'vat_settlement')
            ->exists();

        $depreciationPosted = DepreciationEntry::whereHas('fixedAsset', fn ($q) => $q->where('organization_id', $organizationId))
            ->exists();

        $socialChargesPosted = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'social_charges')
            ->exists();

        $yearEndClosed = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'year_end_closing')
            ->exists();

        $fiduciaryExported = false; // Would require an export log table

        return [
            ['key' => 'checklist_chart_configured',    'done' => $chartConfigured,    'href' => '/accounting/chart-of-accounts'],
            ['key' => 'checklist_invoices_created',    'done' => $invoicesCreated,    'href' => '/invoices'],
            ['key' => 'checklist_expenses_posted',     'done' => $expensesPosted,     'href' => '/expenses'],
            ['key' => 'checklist_bank_imported',       'done' => $bankImported,       'href' => '/banking'],
            ['key' => 'checklist_reconciliation_done', 'done' => $reconciliationDone, 'href' => '/reconciliation'],
            ['key' => 'checklist_vat_declared',        'done' => $vatDeclared,        'href' => '/reports/vat'],
            ['key' => 'checklist_depreciation_posted', 'done' => $depreciationPosted, 'href' => '/assets'],
            ['key' => 'checklist_social_charges',      'done' => $socialChargesPosted, 'href' => '/accounting/social-charges'],
            ['key' => 'checklist_year_end_closed',     'done' => $yearEndClosed,      'href' => '/accounting/year-end-closing'],
            ['key' => 'checklist_fiduciary_exported',  'done' => $fiduciaryExported,  'href' => '/accounting/export'],
        ];
    }
}
