<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Generates a full data export ZIP for an organization (GDPR Art. 20 / portability).
 *
 * Produces both JSON (machine-readable) and CSV (human/spreadsheet) files
 * for every major data entity belonging to the organization.
 */
class OrganizationExportService
{
    /**
     * Generate a ZIP export of all organization data.
     *
     * @return string Absolute path to the generated ZIP file.
     */
    public function generate(string $organizationId): string
    {
        $org = Organization::findOrFail($organizationId);

        $exportDir = Storage::disk('local')->path('exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $zipPath = $exportDir.'/org-export-'.$org->id.'-'.now()->format('Y-m-d-His').'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Organization metadata
        $zip->addFromString('organization.json', $this->toJson($this->exportOrganization($org)));

        // Accounts (chart of accounts)
        $accounts = Account::where('organization_id', $org->id)->orderBy('code')->get();
        $zip->addFromString('accounts.json', $this->toJson($accounts->toArray()));
        $zip->addFromString('accounts.csv', $this->toCsv(
            ['Code', 'Name', 'Type', 'Parent ID', 'Is Active', 'Created At'],
            $accounts->map(fn (Account $a): array => [
                $a->code, $a->name, $a->type->value, $a->parent_id, $a->is_active ? 'Yes' : 'No', (string) $a->created_at,
            ])->toArray(),
        ));

        // Customers
        $customers = Contact::withTrashed()->where('organization_id', $org->id)->get();
        $zip->addFromString('customers.json', $this->toJson($customers->toArray()));
        $zip->addFromString('customers.csv', $this->toCsv(
            ['ID', 'Name', 'Email', 'Phone', 'Address', 'City', 'Postal Code', 'Country', 'VAT Number', 'Currency', 'Payment Terms', 'Created At', 'Deleted At'],
            $customers->map(fn (Contact $c): array => [
                $c->id, $c->name, $c->email, $c->phone, $c->address, $c->city, $c->postal_code, $c->country, $c->vat_number, $c->currency, $c->payment_terms, (string) $c->created_at, (string) $c->deleted_at,
            ])->toArray(),
        ));

        // Suppliers
        $suppliers = Contact::withTrashed()->where('organization_id', $org->id)->get();
        $zip->addFromString('suppliers.json', $this->toJson($suppliers->toArray()));
        $zip->addFromString('suppliers.csv', $this->toCsv(
            ['ID', 'Name', 'Email', 'Phone', 'Address', 'City', 'Postal Code', 'Country', 'VAT Number', 'IBAN', 'Created At', 'Deleted At'],
            $suppliers->map(fn (Contact $s): array => [
                $s->id, $s->name, $s->email, $s->phone, $s->address, $s->city, $s->postal_code, $s->country, $s->vat_number, $s->iban, (string) $s->created_at, (string) $s->deleted_at,
            ])->toArray(),
        ));

        // Invoices
        $invoices = Invoice::withTrashed()->where('organization_id', $org->id)->get();
        $zip->addFromString('invoices.json', $this->toJson($invoices->toArray()));
        $zip->addFromString('invoices.csv', $this->toCsv(
            ['ID', 'Number', 'Customer ID', 'Status', 'Type', 'Issue Date', 'Due Date', 'Subtotal', 'VAT', 'Total', 'Currency', 'Created At', 'Deleted At'],
            $invoices->map(fn (Invoice $i): array => [
                $i->id, $i->number, $i->customer_id, $i->status->value, $i->type->value, (string) $i->issue_date, (string) $i->due_date, $i->subtotal, $i->vat_amount, $i->total, $i->currency, (string) $i->created_at, (string) $i->deleted_at,
            ])->toArray(),
        ));

        // Invoice lines
        $invoiceIds = $invoices->pluck('id');
        $lines = InvoiceLine::whereIn('invoice_id', $invoiceIds)->get();
        $zip->addFromString('invoice_lines.json', $this->toJson($lines->toArray()));
        $zip->addFromString('invoice_lines.csv', $this->toCsv(
            ['ID', 'Invoice ID', 'Description', 'Quantity', 'Unit Price', 'VAT Rate ID', 'Amount', 'Sort Order'],
            $lines->map(fn (InvoiceLine $l): array => [
                $l->id, $l->invoice_id, $l->description, $l->quantity, $l->unit_price, $l->vat_rate_id, $l->amount, $l->sort_order,
            ])->toArray(),
        ));

        // Invoice payments
        $payments = InvoicePayment::where('organization_id', $org->id)->get();
        $zip->addFromString('invoice_payments.json', $this->toJson($payments->toArray()));
        $zip->addFromString('invoice_payments.csv', $this->toCsv(
            ['ID', 'Invoice ID', 'Amount', 'Payment Date', 'Payment Method', 'Reference', 'Created At'],
            $payments->map(fn (InvoicePayment $p): array => [
                $p->id, $p->invoice_id, $p->amount, (string) $p->payment_date, $p->payment_method->value, $p->reference, (string) $p->created_at,
            ])->toArray(),
        ));

        // Expenses
        $expenses = Expense::withTrashed()->where('organization_id', $org->id)->get();
        $zip->addFromString('expenses.json', $this->toJson($expenses->toArray()));
        $zip->addFromString('expenses.csv', $this->toCsv(
            ['ID', 'Category', 'Description', 'Amount', 'VAT Amount', 'Date', 'Vendor', 'Status', 'Currency', 'Supplier ID', 'Created At', 'Deleted At'],
            $expenses->map(fn (Expense $e): array => [
                $e->id, $e->category, $e->description, $e->amount, $e->vat_amount, (string) $e->date, $e->vendor, $e->status->value, $e->currency, $e->supplier_id, (string) $e->created_at, (string) $e->deleted_at,
            ])->toArray(),
        ));

        // Bank accounts
        $bankAccounts = BankAccount::withTrashed()->where('organization_id', $org->id)->get();
        $zip->addFromString('bank_accounts.json', $this->toJson($bankAccounts->toArray()));
        $zip->addFromString('bank_accounts.csv', $this->toCsv(
            ['ID', 'Name', 'IBAN', 'Currency', 'Balance', 'Ledger Account ID', 'Created At', 'Deleted At'],
            $bankAccounts->map(fn (BankAccount $b): array => [
                $b->id, $b->name, $b->iban, $b->currency, $b->balance, $b->account_id, (string) $b->created_at, (string) $b->deleted_at,
            ])->toArray(),
        ));

        // Bank transactions (scoped via bank accounts)
        $bankAccountIds = $bankAccounts->pluck('id');
        $transactions = BankTransaction::whereIn('bank_account_id', $bankAccountIds)->get();
        $zip->addFromString('bank_transactions.json', $this->toJson($transactions->toArray()));
        $zip->addFromString('bank_transactions.csv', $this->toCsv(
            ['ID', 'Bank Account ID', 'Date', 'Description', 'Amount', 'Type', 'Reference', 'Debtor', 'Creditor', 'Is Reconciled', 'Created At'],
            $transactions->map(fn (BankTransaction $t): array => [
                $t->id, $t->bank_account_id, (string) $t->date, $t->description, $t->amount, $t->type->value, $t->reference, $t->debtor_name, $t->creditor_name, $t->is_reconciled ? 'Yes' : 'No', (string) $t->created_at,
            ])->toArray(),
        ));

        // Bank imports
        $bankImports = BankImport::where('organization_id', $org->id)->get();
        $zip->addFromString('bank_imports.json', $this->toJson($bankImports->toArray()));

        // Journal entries
        $journalEntries = JournalEntry::where('organization_id', $org->id)->get();
        $zip->addFromString('journal_entries.json', $this->toJson($journalEntries->toArray()));
        $zip->addFromString('journal_entries.csv', $this->toCsv(
            ['ID', 'Date', 'Reference', 'Description', 'Is Posted', 'Created At'],
            $journalEntries->map(fn (JournalEntry $j) => [
                $j->id, $j->date, $j->reference, $j->description, $j->is_posted ? 'Yes' : 'No', $j->created_at,
            ])->toArray(),
        ));

        // Transaction lines (journal entry details)
        $entryIds = $journalEntries->pluck('id');
        $txLines = TransactionLine::whereIn('journal_entry_id', $entryIds)->get();
        $zip->addFromString('journal_lines.json', $this->toJson($txLines->toArray()));
        $zip->addFromString('journal_lines.csv', $this->toCsv(
            ['ID', 'Journal Entry ID', 'Account ID', 'Debit', 'Credit', 'Description'],
            $txLines->map(fn (TransactionLine $l) => [
                $l->id, $l->journal_entry_id, $l->account_id, $l->debit, $l->credit, $l->description,
            ])->toArray(),
        ));

        // VAT rates
        $vatRates = VatRate::where('organization_id', $org->id)->get();
        $zip->addFromString('vat_rates.json', $this->toJson($vatRates->toArray()));

        // Budgets
        $budgets = Budget::where('organization_id', $org->id)->get();
        $zip->addFromString('budgets.json', $this->toJson($budgets->toArray()));

        // Recurring invoices
        $recurring = RecurringInvoice::where('organization_id', $org->id)->get();
        $zip->addFromString('recurring_invoices.json', $this->toJson($recurring->toArray()));

        $zip->close();

        return $zipPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportOrganization(Organization $org): array
    {
        return [
            'id' => $org->id,
            'name' => $org->name,
            'legal_name' => $org->legal_name,
            'address' => $org->address,
            'city' => $org->city,
            'postal_code' => $org->postal_code,
            'canton' => $org->canton,
            'country' => $org->country,
            'vat_number' => $org->vat_number,
            'qr_iban' => $org->qr_iban,
            'currency' => $org->currency,
            'fiscal_year_start' => $org->fiscal_year_start,
            'closed_fiscal_years' => $org->closed_fiscal_years,
            'locale' => $org->locale,
            'business_type' => $org->business_type?->value,
            'default_payment_terms_days' => $org->default_payment_terms_days,
            'created_at' => $org->created_at?->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
        ];
    }

    private function toJson(mixed $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function toCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }
}
