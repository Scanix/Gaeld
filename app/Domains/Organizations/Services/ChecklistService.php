<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Organizations\Models\Organization;
use App\Support\FeatureFlag;

/**
 * Provides a two-tier guided checklist for organizations:
 *   - getting_started: essential first steps any new user should complete
 *   - accounting: advanced accounting lifecycle items
 */
class ChecklistService
{
    /**
     * Returns both getting-started and accounting checklists.
     *
     * @return array{getting_started: array<int, array{key: string, done: bool, href: string|null}>, accounting: array<int, array{key: string, done: bool, href: string|null}>}
     */
    public function checklist(string $organizationId): array
    {
        return [
            'getting_started' => $this->gettingStarted($organizationId),
            'accounting' => $this->accounting($organizationId),
        ];
    }

    /**
     * Essential first steps for a new user.
     *
     * @return array<int, array{key: string, done: bool, href: string|null}>
     */
    private function gettingStarted(string $organizationId): array
    {
        $org = Organization::find($organizationId);

        $profileComplete = $org
            && $org->legal_name
            && $org->address
            && $org->city
            && $org->postal_code;

        $chartConfigured = Account::where('organization_id', $organizationId)->exists();

        $customerCreated = Contact::where('organization_id', $organizationId)->exists();

        $bankAccountCreated = BankAccount::where('organization_id', $organizationId)->exists();

        $invoicesCreated = Invoice::where('organization_id', $organizationId)->exists();

        return [
            ['key' => 'checklist_profile_complete',    'done' => $profileComplete,    'href' => '/settings'],
            ['key' => 'checklist_chart_configured',    'done' => $chartConfigured,    'href' => '/accounting/chart-of-accounts'],
            ['key' => 'checklist_customer_created',    'done' => $customerCreated,    'href' => '/customers/create'],
            ['key' => 'checklist_bank_account_created', 'done' => $bankAccountCreated, 'href' => '/banking'],
            ['key' => 'checklist_invoices_created',    'done' => $invoicesCreated,    'href' => '/invoices/create'],
        ];
    }

    /**
     * Advanced accounting lifecycle items, filtered by the org's enabled modules.
     *
     * @return array<int, array{key: string, done: bool, href: string|null}>
     */
    private function accounting(string $organizationId): array
    {
        $org = Organization::find($organizationId);

        $dataImported = MigrationSession::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->exists();

        $expensesPosted = Expense::where('organization_id', $organizationId)
            ->whereIn('status', ['approved', 'posted'])
            ->exists();

        $bankImported = BankImport::where('organization_id', $organizationId)->exists();

        $reconciliationDone = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'bank_match')
            ->exists();

        $vatDeclared = JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'vat_settlement')
            ->exists();

        $items = [];

        // Only show data import item for orgs migrating from another system
        if ($org?->setup_mode === 'migrating') {
            $items[] = ['key' => 'checklist_data_imported', 'done' => $dataImported, 'href' => '/migration'];
        }

        $items[] = ['key' => 'checklist_expenses_posted',     'done' => $expensesPosted,     'href' => '/expenses'];
        $items[] = ['key' => 'checklist_bank_imported',       'done' => $bankImported,       'href' => '/banking'];
        $items[] = ['key' => 'checklist_reconciliation_done', 'done' => $reconciliationDone, 'href' => '/reconciliation'];
        $items[] = ['key' => 'checklist_vat_declared',        'done' => $vatDeclared,        'href' => '/reports/vat'];

        if ($org && FeatureFlag::enabledForOrg('assets', $org)) {
            $depreciationPosted = DepreciationEntry::whereHas('fixedAsset', fn ($q) => $q->where('organization_id', $organizationId))
                ->exists();
            $items[] = ['key' => 'checklist_depreciation_posted', 'done' => $depreciationPosted, 'href' => '/assets'];
        }

        if ($org && FeatureFlag::enabledForOrg('social_charges', $org)) {
            $socialChargesPosted = JournalEntry::where('organization_id', $organizationId)
                ->where('type', 'social_charges')
                ->exists();
            $items[] = ['key' => 'checklist_social_charges', 'done' => $socialChargesPosted, 'href' => '/accounting/social-charges'];
        }

        $items[] = ['key' => 'checklist_year_end_closed', 'done' => JournalEntry::where('organization_id', $organizationId)
            ->where('type', 'year_end_closing')
            ->exists(), 'href' => '/accounting/year-end-closing'];

        if ($org && FeatureFlag::enabledForOrg('fiduciary_export', $org)) {
            $items[] = ['key' => 'checklist_fiduciary_exported', 'done' => false, 'href' => '/accounting/export'];
        }

        return $items;
    }
}
