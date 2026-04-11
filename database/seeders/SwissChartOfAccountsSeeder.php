<?php

namespace Database\Seeders;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Swiss SME Chart of Accounts (Kontenrahmen KMU)
 * Based on the standard Swiss chart of accounts for small and medium enterprises.
 */
class SwissChartOfAccountsSeeder extends Seeder
{
    public function run(?Organization $organization = null): void
    {
        $organization = $organization ?? Organization::first();

        if (! $organization) {
            return;
        }

        $accounts = [
            // Class 1: Assets (Aktiven)
            ['code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset->value],
            ['code' => '1010', 'name' => 'Post Office Account', 'type' => AccountType::Asset->value],
            ['code' => '1020', 'name' => 'Bank Account CHF', 'type' => AccountType::Asset->value],
            ['code' => '1021', 'name' => 'Bank Account EUR', 'type' => AccountType::Asset->value],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value],
            ['code' => '1109', 'name' => 'Allowance for Doubtful Accounts', 'type' => AccountType::Asset->value],
            ['code' => '1170', 'name' => 'VAT Input Tax (Vorsteuer)', 'type' => AccountType::Asset->value],
            ['code' => '1200', 'name' => 'Inventory', 'type' => AccountType::Asset->value],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => AccountType::Asset->value],
            ['code' => '1500', 'name' => 'Machinery and Equipment', 'type' => AccountType::Asset->value],
            ['code' => '1510', 'name' => 'Office Equipment', 'type' => AccountType::Asset->value],
            ['code' => '1520', 'name' => 'IT Equipment', 'type' => AccountType::Asset->value],
            ['code' => '1530', 'name' => 'Vehicles', 'type' => AccountType::Asset->value],
            ['code' => '1540', 'name' => 'Tools', 'type' => AccountType::Asset->value],

            // Class 2: Liabilities (Passiven)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability->value],
            ['code' => '2100', 'name' => 'Bank Loan Short-term', 'type' => AccountType::Liability->value],
            ['code' => '2200', 'name' => 'VAT Output Tax (Umsatzsteuer)', 'type' => AccountType::Liability->value],
            ['code' => '2201', 'name' => 'VAT Payable', 'type' => AccountType::Liability->value],
            ['code' => '2270', 'name' => 'Social Security Payable', 'type' => AccountType::Liability->value],
            ['code' => '2300', 'name' => 'Accrued Liabilities', 'type' => AccountType::Liability->value],
            ['code' => '2400', 'name' => 'Bank Loan Long-term', 'type' => AccountType::Liability->value],

            // Class 2.8: Equity (Eigenkapital)
            ['code' => '2800', 'name' => 'Share Capital', 'type' => AccountType::Equity->value],
            ['code' => '2900', 'name' => 'Retained Earnings', 'type' => AccountType::Equity->value],
            ['code' => '2950', 'name' => 'Current Year Profit/Loss', 'type' => AccountType::Equity->value],
            ['code' => '2970', 'name' => 'Owner Drawings', 'type' => AccountType::Equity->value],

            // Class 3: Revenue (Ertrag)
            ['code' => '3000', 'name' => 'Revenue from Services', 'type' => AccountType::Revenue->value],
            ['code' => '3200', 'name' => 'Revenue from Products', 'type' => AccountType::Revenue->value],
            ['code' => '3400', 'name' => 'Other Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '3800', 'name' => 'Discounts Given', 'type' => AccountType::Revenue->value],
            ['code' => '3900', 'name' => 'Revenue Corrections', 'type' => AccountType::Revenue->value],

            // Class 4: Cost of Goods/Services (Aufwand für Material und Dienstleistungen)
            ['code' => '4000', 'name' => 'Cost of Materials', 'type' => AccountType::Expense->value],
            ['code' => '4200', 'name' => 'Cost of Services', 'type' => AccountType::Expense->value],
            ['code' => '4400', 'name' => 'Subcontractor Costs', 'type' => AccountType::Expense->value],

            // Class 5: Personnel Expenses (Personalaufwand)
            ['code' => '5000', 'name' => 'Salaries', 'type' => AccountType::Expense->value],
            ['code' => '5700', 'name' => 'Social Security Contributions', 'type' => AccountType::Expense->value],
            ['code' => '5800', 'name' => 'Other Personnel Expenses', 'type' => AccountType::Expense->value],
            ['code' => '5900', 'name' => 'Temporary Staff', 'type' => AccountType::Expense->value],

            // Class 6: Other Operating Expenses (Übriger betrieblicher Aufwand)
            ['code' => '6000', 'name' => 'Rent', 'type' => AccountType::Expense->value],
            ['code' => '6100', 'name' => 'Maintenance and Repairs', 'type' => AccountType::Expense->value],
            ['code' => '6200', 'name' => 'Vehicle Expenses', 'type' => AccountType::Expense->value],
            ['code' => '6300', 'name' => 'Insurance', 'type' => AccountType::Expense->value],
            ['code' => '6400', 'name' => 'Energy and Utilities', 'type' => AccountType::Expense->value],
            ['code' => '6500', 'name' => 'Office Supplies', 'type' => AccountType::Expense->value],
            ['code' => '6510', 'name' => 'Telephone and Internet', 'type' => AccountType::Expense->value],
            ['code' => '6520', 'name' => 'Postage and Shipping', 'type' => AccountType::Expense->value],
            ['code' => '6530', 'name' => 'Software and Subscriptions', 'type' => AccountType::Expense->value],
            ['code' => '6570', 'name' => 'Accounting and Legal Fees', 'type' => AccountType::Expense->value],
            ['code' => '6600', 'name' => 'Advertising and Marketing', 'type' => AccountType::Expense->value],
            ['code' => '6700', 'name' => 'Travel Expenses', 'type' => AccountType::Expense->value],
            ['code' => '6800', 'name' => 'Depreciation', 'type' => AccountType::Expense->value],
            ['code' => '6900', 'name' => 'Financial Expenses', 'type' => AccountType::Expense->value],
            ['code' => '6950', 'name' => 'Bank Fees', 'type' => AccountType::Expense->value],

            // Class 7-8: Non-operating (simplified)
            ['code' => '7000', 'name' => 'Non-operating Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '7500', 'name' => 'Non-operating Expenses', 'type' => AccountType::Expense->value],
            ['code' => '8000', 'name' => 'Extraordinary Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '8500', 'name' => 'Extraordinary Expenses', 'type' => AccountType::Expense->value],

            // Class 9: Closing
            ['code' => '9000', 'name' => 'Opening Balance', 'type' => AccountType::Equity->value],
            ['code' => '9100', 'name' => 'Profit and Loss Summary', 'type' => AccountType::Equity->value],
        ];

        $systemCodes = array_filter(
            (new \ReflectionClass(AccountCode::class))->getConstants(),
            fn ($v) => is_string($v) && ctype_digit($v),
        );

        foreach ($accounts as $account) {
            Account::create(array_merge($account, [
                'organization_id' => $organization->id,
                'is_system' => in_array($account['code'], $systemCodes, true),
            ]));
        }
    }
}
