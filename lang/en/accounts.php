<?php

/**
 * Translations for the seeded Swiss SME chart of accounts.
 *
 * Keys are the account `code` from SwissChartOfAccountsSeeder. The Account
 * model exposes a translated `display_name` accessor that falls back to the
 * stored `name` when no translation exists for the code (e.g. for accounts
 * created by the user).
 */
return [
    // Class 1: Assets
    '1000' => 'Cash',
    '1010' => 'Post Office Account',
    '1020' => 'Bank Account CHF',
    '1021' => 'Bank Account EUR',
    '1100' => 'Accounts Receivable',
    '1109' => 'Allowance for Doubtful Accounts',
    '1170' => 'VAT Input Tax',
    '1200' => 'Inventory',
    '1300' => 'Prepaid Expenses',
    '1500' => 'Machinery and Equipment',
    '1510' => 'Office Equipment',
    '1520' => 'IT Equipment',
    '1530' => 'Vehicles',
    '1540' => 'Tools',

    // Class 2: Liabilities
    '2000' => 'Accounts Payable',
    '2100' => 'Bank Loan Short-term',
    '2200' => 'VAT Output Tax',
    '2201' => 'VAT Payable',
    '2270' => 'Social Security Payable',
    '2271' => 'Unemployment Insurance (AC) Payable',
    '2272' => 'Pension Fund (LPP) Payable',
    '2300' => 'Accrued Liabilities',
    '2400' => 'Bank Loan Long-term',

    // Class 2.8: Equity
    '2800' => 'Share Capital',
    '2900' => 'Retained Earnings',
    '2950' => 'Current Year Profit/Loss',
    '2970' => 'Owner Drawings',

    // Class 3: Revenue
    '3000' => 'Revenue from Services',
    '3200' => 'Revenue from Products',
    '3400' => 'Other Revenue',
    '3800' => 'Discounts Given',
    '3900' => 'Revenue Corrections',

    // Class 4: Cost of Goods/Services
    '4000' => 'Cost of Materials',
    '4200' => 'Cost of Services',
    '4400' => 'Subcontractor Costs',

    // Class 5: Personnel Expenses
    '5000' => 'Salaries',
    '5700' => 'Social Security Contributions',
    '5800' => 'Other Personnel Expenses',
    '5900' => 'Temporary Staff',

    // Class 6: Other Operating Expenses
    '6000' => 'Rent',
    '6100' => 'Maintenance and Repairs',
    '6200' => 'Vehicle Expenses',
    '6300' => 'Insurance',
    '6400' => 'Energy and Utilities',
    '6500' => 'Office Supplies',
    '6510' => 'Telephone and Internet',
    '6520' => 'Postage and Shipping',
    '6530' => 'Software and Subscriptions',
    '6570' => 'Accounting and Legal Fees',
    '6600' => 'Advertising and Marketing',
    '6700' => 'Travel Expenses',
    '6800' => 'Depreciation',
    '6900' => 'Financial Expenses',
    '6950' => 'Bank Fees',

    // Class 7-8: Non-operating
    '7000' => 'Non-operating Revenue',
    '7500' => 'Non-operating Expenses',
    '8000' => 'Extraordinary Revenue',
    '8500' => 'Extraordinary Expenses',

    // Class 9: Closing
    '9000' => 'Opening Balance',
    '9100' => 'Profit and Loss Summary',
];
