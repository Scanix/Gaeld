<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Client;
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
 * - 1 admin user + 1 organization
 * - 2 clients, 3 invoices (1 paid, 1 sent, 1 draft)
 * - 2 posted expenses
 * - 1 bank account with 2 recorded transactions
 * - All posted items have matching journal entries & transaction lines
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $ledger = app(LedgerService::class);

        // ── Admin + Organization ──────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@gaeld.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'locale' => 'en',
            ]
        );

        $org = Organization::first() ?? Organization::create([
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

        $org->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        $vatNormal = VatRate::where('organization_id', $org->id)
            ->where('code', 'NORMAL')
            ->first();

        // ── Bank Account ─────────────────────────────────────────
        $bankLedgerAccount = Account::where('organization_id', $org->id)
            ->where('code', '1020')
            ->firstOrFail();

        $bankAccount = BankAccount::create([
            'organization_id' => $org->id,
            'account_id' => $bankLedgerAccount->id,
            'name' => 'Main Business Account',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bank_name' => 'UBS Switzerland AG',
            'currency' => 'CHF',
            'balance' => 50000.00,
        ]);

        // ── Clients ──────────────────────────────────────────────
        $client1 = Client::create([
            'organization_id' => $org->id,
            'name' => 'Acme AG',
            'contact_name' => 'Hans Müller',
            'email' => 'hans@acme.ch',
            'address' => 'Hauptstrasse 10',
            'city' => 'Bern',
            'postal_code' => '3000',
            'country' => 'CH',
        ]);

        $client2 = Client::create([
            'organization_id' => $org->id,
            'name' => 'Swiss Tech Sàrl',
            'contact_name' => 'Marie Dupont',
            'email' => 'marie@swisstech.ch',
            'address' => 'Rue du Lac 5',
            'city' => 'Genève',
            'postal_code' => '1200',
            'country' => 'CH',
        ]);

        // ── Invoice 1: Posted + Paid ─────────────────────────────
        $inv1 = Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $client1->id,
            'number' => 'INV-2026-001',
            'status' => Invoice::STATUS_DRAFT,
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

        // Post invoice → Debit AR 1100 / Credit Revenue 3000
        $ledger->postInvoice($inv1);

        // Record payment → Debit Bank 1020 / Credit AR 1100
        $inv1->refresh();
        app(\App\Domains\Invoicing\Services\InvoiceService::class)
            ->recordPayment($inv1, 5405.00);

        // ── Invoice 2: Posted (sent, unpaid) ─────────────────────
        $inv2 = Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $client2->id,
            'number' => 'INV-2026-002',
            'status' => Invoice::STATUS_DRAFT,
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

        $ledger->postInvoice($inv2);

        // ── Invoice 3: Draft (not yet posted) ────────────────────
        $inv3 = Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $client1->id,
            'number' => 'INV-2026-003',
            'status' => Invoice::STATUS_DRAFT,
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
            'status' => Expense::STATUS_PENDING,
            'currency' => 'CHF',
        ]);

        // Post expense → Debit 6530 Software / Credit 1020 Bank
        $ledger->postExpense($exp1, '6530');

        // ── Expense 2: Posted (Office Supplies) ──────────────────
        $exp2 = Expense::create([
            'organization_id' => $org->id,
            'category' => 'Office Supplies',
            'description' => 'Printer paper and toner',
            'amount' => 150.00,
            'vat_amount' => 12.15,
            'date' => now()->subDays(5),
            'vendor' => 'Office World',
            'status' => Expense::STATUS_PENDING,
            'currency' => 'CHF',
        ]);

        // Post expense → Debit 6500 Office / Credit 1020 Bank
        $ledger->postExpense($exp2, '6500');

        // ── Bank Transactions ────────────────────────────────────

        // Deposit: Freelance income from external client
        $bnkTx1 = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => now()->subDays(20),
            'description' => 'Freelance income — Logo design',
            'amount' => 2500.00,
            'type' => BankTransaction::TYPE_CREDIT,
            'reference' => 'BNK-2026-001',
        ]);

        $ledger->postBankTransaction($bnkTx1, '3000');

        // Withdrawal: Rent payment
        $bnkTx2 = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => now()->subDays(3),
            'description' => 'Office rent — March 2026',
            'amount' => 1800.00,
            'type' => BankTransaction::TYPE_DEBIT,
            'reference' => 'BNK-2026-002',
        ]);

        $ledger->postBankTransaction($bnkTx2, '6000');
    }
}
