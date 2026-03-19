<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data with real ledger transactions.
 *
 * After running this seeder the database contains:
 * - 2 organizations (Demo GmbH, Alpine Consulting Sàrl)
 * - 4 users with varying roles across orgs
 * - Customers, invoices, expenses, bank accounts with journal entries
 * - Chart of accounts and VAT rates for each org
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $finalizeInvoice = app(FinalizeInvoiceAction::class);
        $postExpense = app(PostExpenseAction::class);
        $bankingService = app(BankingService::class);

        // ── Users ────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@gaeld.local'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'locale' => 'en',
            ]
        );

        $accountant = User::firstOrCreate(
            ['email' => 'accountant@gaeld.local'],
            [
                'name' => 'Marie Dupont',
                'password' => Hash::make('password'),
                'locale' => 'fr',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@gaeld.local'],
            [
                'name' => 'Hans Meier',
                'password' => Hash::make('password'),
                'locale' => 'de',
            ]
        );

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@gaeld.local'],
            [
                'name' => 'Luca Rossi',
                'password' => Hash::make('password'),
                'locale' => 'it',
            ]
        );

        // ── Organization 1: Demo GmbH ───────────────────────────
        $org1 = Organization::first() ?? Organization::create([
            'name' => 'Demo GmbH',
            'legal_name' => 'Demo GmbH',
            'address' => 'Bahnhofstrasse 1',
            'city' => 'Zürich',
            'postal_code' => '8001',
            'canton' => 'ZH',
            'country' => 'CH',
            'vat_number' => 'CHE-123.456.789 MWST',
            'currency' => 'CHF',
            'locale' => 'en',
        ]);

        $org1->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner'],
            $accountant->id => ['role' => 'accountant'],
            $member->id => ['role' => 'member'],
        ]);

        // Seed chart of accounts and VAT rates for org1
        $chartsSeeder = new SwissChartOfAccountsSeeder();
        $vatSeeder = new SwissVatRatesSeeder();

        if ($org1->accounts()->count() === 0) {
            $chartsSeeder->run($org1);
        }
        if (VatRate::where('organization_id', $org1->id)->count() === 0) {
            $vatSeeder->run($org1);
        }

        // ── Organization 2: Alpine Consulting Sàrl ───────────────
        $org2 = Organization::where('name', 'Alpine Consulting Sàrl')->first()
            ?? Organization::create([
                'name' => 'Alpine Consulting Sàrl',
                'legal_name' => 'Alpine Consulting Sàrl',
                'address' => 'Rue du Mont-Blanc 12',
                'city' => 'Genève',
                'postal_code' => '1201',
                'canton' => 'GE',
                'country' => 'CH',
                'vat_number' => 'CHE-987.654.321 MWST',
                'currency' => 'CHF',
                'locale' => 'fr',
            ]);

        $org2->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner'],
            $viewer->id => ['role' => 'member'],
        ]);

        if ($org2->accounts()->count() === 0) {
            $chartsSeeder->run($org2);
        }
        if (VatRate::where('organization_id', $org2->id)->count() === 0) {
            $vatSeeder->run($org2);
        }

        // ── Seed demo data for Organization 1 ────────────────────
        $this->seedOrganizationData($org1);

        // ── Seed demo data for Organization 2 ────────────────────
        $this->seedOrganization2Data($org2);
    }

    private function seedOrganizationData(Organization $org): void
    {
        $vatNormal = VatRate::where('organization_id', $org->id)
            ->where('code', 'NORMAL')
            ->first();

        $finalizeInvoice = app(FinalizeInvoiceAction::class);
        $postExpense = app(PostExpenseAction::class);
        $bankingService = app(BankingService::class);

        // ── Bank Account ─────────────────────────────────────────
        $bankLedgerAccount = Account::where('organization_id', $org->id)
            ->where('code', '1020')
            ->firstOrFail();

        $bankAccount = BankAccount::firstOrCreate(
            ['organization_id' => $org->id, 'iban' => 'CH93 0076 2011 6238 5295 7'],
            [
                'account_id' => $bankLedgerAccount->id,
                'name' => 'Main Business Account',
                'bank_name' => 'UBS Switzerland AG',
                'currency' => 'CHF',
                'balance' => 50000.00,
            ]
        );

        // ── Customers ────────────────────────────────────────────
        $client1 = Customer::firstOrCreate(
            ['organization_id' => $org->id, 'email' => 'hans@acme.ch'],
            [
                'name' => 'Acme AG',
                'address' => 'Hauptstrasse 10',
                'city' => 'Bern',
                'postal_code' => '3000',
                'country' => 'CH',
            ]
        );

        $client2 = Customer::firstOrCreate(
            ['organization_id' => $org->id, 'email' => 'marie@swisstech.ch'],
            [
                'name' => 'Swiss Tech Sàrl',
                'address' => 'Rue du Lac 5',
                'city' => 'Genève',
                'postal_code' => '1200',
                'country' => 'CH',
            ]
        );

        // Skip if invoices already exist for this org
        if (Invoice::where('organization_id', $org->id)->exists()) {
            return;
        }

        // ── Invoice 1: Posted + Paid ─────────────────────────────
        $inv1 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client1->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->subDays(30),
            'due_date' => now(),
            'subtotal' => 5000.00,
            'vat_amount' => 405.00,
            'total' => 5405.00,
            'currency' => 'CHF',
            'notes' => 'Thank you for your business.',
            'payment_terms' => 'Net 30',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv1->id,
            'description' => 'Web Development Services — February 2026',
            'quantity' => 40,
            'unit_price' => 125.00,
            'amount' => 5000.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 405.00,
            'sort_order' => 1,
        ]);

        $finalizeInvoice->execute($inv1);

        $inv1->refresh();
        app(\App\Domains\Invoicing\Services\InvoiceService::class)
            ->recordPayment($inv1, new RecordPaymentData(
                amount: '5405.00',
                paymentDate: now()->subDays(5)->toDateString(),
                paymentMethod: 'bank',
                reference: null,
            ));

        // ── Invoice 2: Posted (sent, unpaid) ─────────────────────
        $inv2 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client2->id,
            'number' => 'INV-2026-002',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'subtotal' => 3200.00,
            'vat_amount' => 259.20,
            'total' => 3459.20,
            'currency' => 'CHF',
            'payment_terms' => 'Net 30',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv2->id,
            'description' => 'API Integration — March 2026',
            'quantity' => 16,
            'unit_price' => 200.00,
            'amount' => 3200.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 259.20,
            'sort_order' => 1,
        ]);

        $finalizeInvoice->execute($inv2);

        // ── Invoice 3: Draft (not yet posted) ────────────────────
        $inv3 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client1->id,
            'number' => 'INV-2026-003',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1500.00,
            'vat_amount' => 121.50,
            'total' => 1621.50,
            'currency' => 'CHF',
            'payment_terms' => 'Net 30',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv3->id,
            'description' => 'Consulting — March 2026',
            'quantity' => 10,
            'unit_price' => 150.00,
            'amount' => 1500.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 121.50,
            'sort_order' => 1,
        ]);

        // ── Expense 1: Posted (Software) ─────────────────────────
        $exp1 = Expense::create([
            'organization_id' => $org->id,
            'vat_rate_id' => $vatNormal?->id,
            'category' => 'Software and Subscriptions',
            'description' => 'Adobe Creative Cloud — Annual License',
            'amount' => 700.00,
            'vat_amount' => 56.70,
            'date' => now()->subDays(15),
            'vendor' => 'Adobe Inc.',
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        $postExpense->execute($exp1, '6530');

        // ── Expense 2: Posted (Office Supplies) ──────────────────
        $exp2 = Expense::create([
            'organization_id' => $org->id,
            'category' => 'Office Supplies',
            'description' => 'Printer paper and toner',
            'amount' => 150.00,
            'vat_amount' => 12.15,
            'date' => now()->subDays(5),
            'vendor' => 'Office World',
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        $postExpense->execute($exp2, '6500');

        // ── Expense 3: Pending (awaiting approval) ───────────────
        Expense::create([
            'organization_id' => $org->id,
            'category' => 'Travel Expenses',
            'description' => 'Train ticket Zürich–Bern (client meeting)',
            'amount' => 88.00,
            'vat_amount' => 7.13,
            'date' => now()->subDays(2),
            'vendor' => 'SBB CFF FFS',
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        // ── Expense 4: Approved (waiting to be posted) ───────────
        $exp4 = Expense::create([
            'organization_id' => $org->id,
            'vat_rate_id' => $vatNormal?->id,
            'category' => 'Professional Services',
            'description' => 'Legal consultation — contract review',
            'amount' => 450.00,
            'vat_amount' => 36.45,
            'date' => now()->subDays(3),
            'vendor' => 'Fischer & Partner Rechtsanwälte',
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        (new ApproveExpenseAction())->execute($exp4);

        // ── Invoice 4: Partially paid ────────────────────────────
        $inv4 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client2->id,
            'number' => 'INV-2026-004',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->subDays(20),
            'due_date' => now()->addDays(10),
            'subtotal' => 4000.00,
            'vat_amount' => 324.00,
            'total' => 4324.00,
            'currency' => 'CHF',
            'payment_terms' => 'Net 30',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv4->id,
            'description' => 'Mobile App Development — Phase 1',
            'quantity' => 20,
            'unit_price' => 200.00,
            'amount' => 4000.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 324.00,
            'sort_order' => 1,
        ]);

        $finalizeInvoice->execute($inv4);

        $inv4->refresh();
        app(\App\Domains\Invoicing\Services\InvoiceService::class)
            ->recordPayment($inv4, new RecordPaymentData(
                amount: '2000.00',
                paymentDate: now()->subDays(3)->toDateString(),
                paymentMethod: 'bank',
                reference: 'Partial payment — Phase 1 deposit',
            ));

        // ── Invoice 5: Cancelled ─────────────────────────────────
        $inv5 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client1->id,
            'number' => 'INV-2026-005',
            'status' => InvoiceStatus::Cancelled,
            'issue_date' => now()->subDays(25),
            'due_date' => now()->subDays(5),
            'subtotal' => 800.00,
            'vat_amount' => 64.80,
            'total' => 864.80,
            'currency' => 'CHF',
            'notes' => 'Cancelled — project scope changed',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv5->id,
            'description' => 'Initial design mockups (cancelled)',
            'quantity' => 8,
            'unit_price' => 100.00,
            'amount' => 800.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 64.80,
            'sort_order' => 1,
        ]);

        // ── Bank Transactions ────────────────────────────────────
        $bnkTx1 = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => now()->subDays(20),
            'description' => 'Freelance income — Logo design',
            'amount' => 2500.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'BNK-2026-001',
        ]);

        $bankingService->postBankTransaction($bnkTx1, '3000');

        $bnkTx2 = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => now()->subDays(3),
            'description' => 'Office rent — March 2026',
            'amount' => 1800.00,
            'type' => BankTransactionType::Debit,
            'reference' => 'BNK-2026-002',
        ]);

        $bankingService->postBankTransaction($bnkTx2, '6000');
    }

    private function seedOrganization2Data(Organization $org): void
    {
        $vatNormal = VatRate::where('organization_id', $org->id)
            ->where('code', 'NORMAL')
            ->first();

        // ── Bank Account ─────────────────────────────────────────
        $bankLedgerAccount = Account::where('organization_id', $org->id)
            ->where('code', '1020')
            ->firstOrFail();

        $bankAccount = BankAccount::firstOrCreate(
            ['organization_id' => $org->id, 'iban' => 'CH56 0483 5012 3456 7800 9'],
            [
                'account_id' => $bankLedgerAccount->id,
                'name' => 'Compte principal',
                'bank_name' => 'Credit Suisse',
                'currency' => 'CHF',
                'balance' => 25000.00,
            ]
        );

        // ── Customers ────────────────────────────────────────────
        $client1 = Customer::firstOrCreate(
            ['organization_id' => $org->id, 'email' => 'info@watchmaker.ch'],
            [
                'name' => 'Watchmaker SA',
                'address' => 'Avenue de la Gare 3',
                'city' => 'Lausanne',
                'postal_code' => '1003',
                'country' => 'CH',
            ]
        );

        // Skip if invoices already exist for this org
        if (Invoice::where('organization_id', $org->id)->exists()) {
            return;
        }

        // ── Invoice: Posted (sent, unpaid) ───────────────────────
        $inv1 = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $client1->id,
            'number' => 'ALP-2026-001',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(25),
            'subtotal' => 8500.00,
            'vat_amount' => 688.50,
            'total' => 9188.50,
            'currency' => 'CHF',
            'notes' => 'Merci pour votre confiance.',
            'payment_terms' => 'Net 30',
        ]);

        InvoiceLine::create([
            'invoice_id' => $inv1->id,
            'description' => 'Conseil stratégique — Mars 2026',
            'quantity' => 50,
            'unit_price' => 170.00,
            'amount' => 8500.00,
            'vat_rate_id' => $vatNormal?->id,
            'vat_amount' => 688.50,
            'sort_order' => 1,
        ]);

        $finalizeInvoice = app(FinalizeInvoiceAction::class);
        $postExpense = app(PostExpenseAction::class);
        $bankingService = app(BankingService::class);

        $finalizeInvoice->execute($inv1);

        // ── Expense: Posted ──────────────────────────────────────
        $exp1 = Expense::create([
            'organization_id' => $org->id,
            'vat_rate_id' => $vatNormal?->id,
            'category' => 'Travel Expenses',
            'description' => 'Déplacement client — Lausanne',
            'amount' => 320.00,
            'vat_amount' => 25.92,
            'date' => now()->subDays(7),
            'vendor' => 'SBB CFF FFS',
            'status' => ExpenseStatus::Pending->value,
            'currency' => 'CHF',
        ]);

        $postExpense->execute($exp1, '6700');

        // ── Bank Transaction ─────────────────────────────────────
        $bnkTx1 = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => now()->subDays(10),
            'description' => 'Consulting payment — February',
            'amount' => 4200.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'ALP-BNK-001',
        ]);

        $bankingService->postBankTransaction($bnkTx1, '3000');
    }
}
