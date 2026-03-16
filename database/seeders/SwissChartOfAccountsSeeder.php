<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Swiss SME Chart of Accounts (Kontenrahmen KMU)
 * Based on the standard Swiss chart of accounts for small and medium enterprises.
 */
class SwissChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();

        if (! $organization) {
            return;
        }

        $accounts = [
            // Class 1: Assets (Aktiven)
            ['code' => '1000', 'name' => 'Cash', 'type' => Account::TYPE_ASSET],
            ['code' => '1010', 'name' => 'Post Office Account', 'type' => Account::TYPE_ASSET],
            ['code' => '1020', 'name' => 'Bank Account CHF', 'type' => Account::TYPE_ASSET],
            ['code' => '1021', 'name' => 'Bank Account EUR', 'type' => Account::TYPE_ASSET],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => Account::TYPE_ASSET],
            ['code' => '1109', 'name' => 'Allowance for Doubtful Accounts', 'type' => Account::TYPE_ASSET],
            ['code' => '1170', 'name' => 'VAT Input Tax (Vorsteuer)', 'type' => Account::TYPE_ASSET],
            ['code' => '1200', 'name' => 'Inventory', 'type' => Account::TYPE_ASSET],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => Account::TYPE_ASSET],
            ['code' => '1500', 'name' => 'Machinery and Equipment', 'type' => Account::TYPE_ASSET],
            ['code' => '1510', 'name' => 'Office Equipment', 'type' => Account::TYPE_ASSET],
            ['code' => '1520', 'name' => 'IT Equipment', 'type' => Account::TYPE_ASSET],
            ['code' => '1530', 'name' => 'Vehicles', 'type' => Account::TYPE_ASSET],
            ['code' => '1540', 'name' => 'Tools', 'type' => Account::TYPE_ASSET],

            // Class 2: Liabilities (Passiven)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2100', 'name' => 'Bank Loan Short-term', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2200', 'name' => 'VAT Output Tax (Umsatzsteuer)', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2201', 'name' => 'VAT Payable', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2270', 'name' => 'Social Security Payable', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2300', 'name' => 'Accrued Liabilities', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2400', 'name' => 'Bank Loan Long-term', 'type' => Account::TYPE_LIABILITY],

            // Class 2.8: Equity (Eigenkapital)
            ['code' => '2800', 'name' => 'Share Capital', 'type' => Account::TYPE_EQUITY],
            ['code' => '2900', 'name' => 'Retained Earnings', 'type' => Account::TYPE_EQUITY],
            ['code' => '2950', 'name' => 'Current Year Profit/Loss', 'type' => Account::TYPE_EQUITY],
            ['code' => '2970', 'name' => 'Owner Drawings', 'type' => Account::TYPE_EQUITY],

            // Class 3: Revenue (Ertrag)
            ['code' => '3000', 'name' => 'Revenue from Services', 'type' => Account::TYPE_REVENUE],
            ['code' => '3200', 'name' => 'Revenue from Products', 'type' => Account::TYPE_REVENUE],
            ['code' => '3400', 'name' => 'Other Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '3800', 'name' => 'Discounts Given', 'type' => Account::TYPE_REVENUE],
            ['code' => '3900', 'name' => 'Revenue Corrections', 'type' => Account::TYPE_REVENUE],

            // Class 4: Cost of Goods/Services (Aufwand für Material und Dienstleistungen)
            ['code' => '4000', 'name' => 'Cost of Materials', 'type' => Account::TYPE_EXPENSE],
            ['code' => '4200', 'name' => 'Cost of Services', 'type' => Account::TYPE_EXPENSE],
            ['code' => '4400', 'name' => 'Subcontractor Costs', 'type' => Account::TYPE_EXPENSE],

            // Class 5: Personnel Expenses (Personalaufwand)
            ['code' => '5000', 'name' => 'Salaries', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5700', 'name' => 'Social Security Contributions', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5800', 'name' => 'Other Personnel Expenses', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5900', 'name' => 'Temporary Staff', 'type' => Account::TYPE_EXPENSE],

            // Class 6: Other Operating Expenses (Übriger betrieblicher Aufwand)
            ['code' => '6000', 'name' => 'Rent', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6100', 'name' => 'Maintenance and Repairs', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6200', 'name' => 'Vehicle Expenses', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6300', 'name' => 'Insurance', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6400', 'name' => 'Energy and Utilities', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6500', 'name' => 'Office Supplies', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6510', 'name' => 'Telephone and Internet', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6520', 'name' => 'Postage and Shipping', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6530', 'name' => 'Software and Subscriptions', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6570', 'name' => 'Accounting and Legal Fees', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6600', 'name' => 'Advertising and Marketing', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6700', 'name' => 'Travel Expenses', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6800', 'name' => 'Depreciation', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6900', 'name' => 'Financial Expenses', 'type' => Account::TYPE_EXPENSE],
            ['code' => '6950', 'name' => 'Bank Fees', 'type' => Account::TYPE_EXPENSE],

            // Class 7-8: Non-operating (simplified)
            ['code' => '7000', 'name' => 'Non-operating Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '7500', 'name' => 'Non-operating Expenses', 'type' => Account::TYPE_EXPENSE],
            ['code' => '8000', 'name' => 'Extraordinary Revenue', 'type' => Account::TYPE_REVENUE],
            ['code' => '8500', 'name' => 'Extraordinary Expenses', 'type' => Account::TYPE_EXPENSE],

            // Class 9: Closing
            ['code' => '9000', 'name' => 'Opening Balance', 'type' => Account::TYPE_EQUITY],
            ['code' => '9100', 'name' => 'Profit and Loss Summary', 'type' => Account::TYPE_EQUITY],
        ];

        foreach ($accounts as $account) {
            Account::create(array_merge($account, [
                'organization_id' => $organization->id,
            ]));
        }
    }
}
