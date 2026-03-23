<?php

namespace Tests\Feature\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_demo_data_with_accounting_prerequisites(): void
    {
        $this->seed(DemoDataSeeder::class);

        $demoInvoiceNumbers = [
            'INV-2026-001',
            'INV-2026-002',
            'INV-2026-003',
            'INV-2026-004',
            'INV-2026-005',
            'ALP-2026-001',
        ];

        $demoExpenseDescriptions = [
            'Adobe Creative Cloud — Annual License',
            'Printer paper and toner',
            'Train ticket Zürich–Bern (client meeting)',
            'Legal consultation — contract review',
            'Déplacement client — Lausanne',
        ];

        $this->assertDatabaseHas('organizations', ['name' => 'Demo GmbH']);
        $this->assertDatabaseHas('organizations', ['name' => 'Alpine Consulting Sàrl']);
        $this->assertSame(1, Organization::where('name', 'Demo GmbH')->count());
        $this->assertSame(1, Organization::where('name', 'Alpine Consulting Sàrl')->count());
        $this->assertSame(4, User::whereIn('email', [
            'admin@gaeld.local',
            'accountant@gaeld.local',
            'member@gaeld.local',
            'viewer@gaeld.local',
        ])->count());
        $this->assertSame(2, BankAccount::whereIn('iban', [
            'CH93 0076 2011 6238 5295 7',
            'CH56 0483 5012 3456 7800 9',
        ])->count());
        $this->assertSame(2, BankTransaction::whereIn('reference', [
            'BNK-2026-001',
            'BNK-2026-002',
        ])->count());
        $this->assertSame(6, Invoice::whereIn('number', $demoInvoiceNumbers)->count());
        $this->assertSame(5, Expense::whereIn('description', $demoExpenseDescriptions)->count());
        $this->assertGreaterThan(80, Account::count());
        $this->assertGreaterThanOrEqual(8, VatRate::count());
        $this->assertDatabaseHas('users', ['email' => 'admin@gaeld.local']);
        $this->assertDatabaseHas('invoices', ['number' => 'INV-2026-001']);
        $this->assertDatabaseHas('invoices', ['number' => 'ALP-2026-001']);
    }

    public function test_it_is_idempotent_for_demo_records(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $demoInvoiceNumbers = [
            'INV-2026-001',
            'INV-2026-002',
            'INV-2026-003',
            'INV-2026-004',
            'INV-2026-005',
            'ALP-2026-001',
        ];

        $demoExpenseDescriptions = [
            'Adobe Creative Cloud — Annual License',
            'Printer paper and toner',
            'Train ticket Zürich–Bern (client meeting)',
            'Legal consultation — contract review',
            'Déplacement client — Lausanne',
        ];

        $this->assertSame(1, Organization::where('name', 'Demo GmbH')->count());
        $this->assertSame(1, Organization::where('name', 'Alpine Consulting Sàrl')->count());
        $this->assertSame(6, Invoice::whereIn('number', $demoInvoiceNumbers)->count());
        $this->assertSame(5, Expense::whereIn('description', $demoExpenseDescriptions)->count());
        $this->assertSame(2, BankTransaction::whereIn('reference', [
            'BNK-2026-001',
            'BNK-2026-002',
        ])->count());
    }
}