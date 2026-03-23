<?php

namespace Database\Seeders;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Import 2025 real accounting data for Nectoria.
 *
 * Run with:
 *   php artisan db:seed --class=Import2025AccountingSeeder
 *
 * Or target a specific org:
 *   php artisan db:seed --class=Import2025AccountingSeeder
 *   (uses the first organization found, or the one named Nectoria)
 */
class Import2025AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('name', 'Nectoria')->first()
            ?? Organization::first();

        if (! $org) {
            $this->command->error('No organization found. Run the setup wizard first.');
            return;
        }

        $this->command->info("Importing 2025 accounting data for organization: {$org->name}");

        $billing = $this->importBillingInvoices($org->id);
        $loyer   = $this->importLoyerBureau($org->id);

        $this->command->info("Imported {$billing} billing expenses and {$loyer} loyer expenses.");
    }

    // ──────────────────────────────────────────────────────────────
    //  Google Cloud billing invoices (received by Nectoria)
    // ──────────────────────────────────────────────────────────────

    private function importBillingInvoices(string $orgId): int
    {
        // Billing_invoice_list.csv — Google Cloud monthly invoices
        $rows = [
            ['id' => 'G127436769', 'date' => '2025-12-05', 'amount' => 12.71],
            ['id' => 'G122040592', 'date' => '2025-11-05', 'amount' => 12.71],
            ['id' => 'G116604016', 'date' => '2025-10-05', 'amount' => 12.71],
            ['id' => 'G110874491', 'date' => '2025-09-05', 'amount' => 12.71],
            ['id' => 'G105173655', 'date' => '2025-08-05', 'amount' => 12.71],
            ['id' => 'G100036086', 'date' => '2025-07-05', 'amount' => 12.71],
            ['id' => 'G095035366', 'date' => '2025-06-05', 'amount' => 12.71],
            ['id' => 'G094975583', 'date' => '2025-06-05', 'amount' => 12.11],
            ['id' => 'G089819133', 'date' => '2025-05-05', 'amount' => 12.11],
            ['id' => 'G085065099', 'date' => '2025-04-05', 'amount' => 12.11],
            ['id' => 'G080982283', 'date' => '2025-03-05', 'amount' => 12.11],
            ['id' => 'G076710473', 'date' => '2025-02-05', 'amount' => 12.11],
            ['id' => 'G072491557', 'date' => '2025-01-05', 'amount' => 12.11],
        ];

        $count = 0;
        foreach ($rows as $row) {
            // Skip if already imported (idempotent via description check)
            $exists = Expense::where('organization_id', $orgId)
                ->where('description', 'Google Cloud — ' . $row['id'])
                ->exists();

            if ($exists) {
                continue;
            }

            Expense::create([
                'id'              => Str::uuid(),
                'organization_id' => $orgId,
                'category'        => 'Software',
                'description'     => 'Google Cloud — ' . $row['id'],
                'amount'          => $row['amount'],
                'vat_amount'      => 0,
                'date'            => $row['date'],
                'vendor'          => 'Google Cloud (Nectoria)',
                'currency'        => 'CHF',
                'status'          => ExpenseStatus::Pending,
            ]);

            $count++;
        }

        return $count;
    }

    // ──────────────────────────────────────────────────────────────
    //  Office rent (loyer bureau) — standing order to Mael Baechtold
    // ──────────────────────────────────────────────────────────────

    private function importLoyerBureau(string $orgId): int
    {
        // loyer_bureau.csv — monthly CHF 192 standing orders + initial CHF 385 TWINT
        $rows = [
            ['date' => '2025-12-31', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent déc. 2025'],
            ['date' => '2025-11-28', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent nov. 2025'],
            ['date' => '2025-10-31', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent oct. 2025'],
            ['date' => '2025-09-30', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent sep. 2025'],
            ['date' => '2025-08-29', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent août 2025'],
            ['date' => '2025-07-31', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent juil. 2025'],
            ['date' => '2025-06-30', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent juin 2025'],
            ['date' => '2025-05-30', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent mai 2025'],
            ['date' => '2025-04-30', 'amount' => 192.00, 'description' => 'Loyer bureau — ordre permanent avr. 2025'],
            ['date' => '2025-03-21', 'amount' => 175.00, 'description' => 'Loyer bureau — ordre permanent mars 2025'],
            ['date' => '2025-02-14', 'amount' => 385.00, 'description' => 'Loyer bureau — versement initial TWINT (Baechtold)'],
        ];

        $count = 0;
        foreach ($rows as $row) {
            $exists = Expense::where('organization_id', $orgId)
                ->where('description', $row['description'])
                ->exists();

            if ($exists) {
                continue;
            }

            Expense::create([
                'id'              => Str::uuid(),
                'organization_id' => $orgId,
                'category'        => 'Rent',
                'description'     => $row['description'],
                'amount'          => $row['amount'],
                'vat_amount'      => 0,
                'date'            => $row['date'],
                'vendor'          => 'Mael Baechtold',
                'currency'        => 'CHF',
                'status'          => ExpenseStatus::Pending,
            ]);

            $count++;
        }

        return $count;
    }
}
