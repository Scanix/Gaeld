<?php

use App\Domains\Accounting\Controllers\AccountController;
use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Accounting\Controllers\BudgetController;
use App\Domains\Accounting\Controllers\ConsolidationController;
use App\Domains\Accounting\Controllers\CostCenterController;
use App\Domains\Accounting\Controllers\ExchangeRateController;
use App\Domains\Accounting\Controllers\FiscalYearsController;
use App\Domains\Accounting\Controllers\LegalArchiveController;
use App\Domains\Accounting\Controllers\LettrageController;
use App\Domains\Accounting\Controllers\OpeningBalancesController;
use App\Domains\Accounting\Controllers\SocialChargesController;
use App\Domains\Accounting\Controllers\TaxDeclarationController;
use App\Domains\Accounting\Controllers\VatRateController;
use App\Domains\Accounting\Controllers\YearEndClosingController;
use App\Domains\Reporting\Controllers\AccountingExportController;
use Illuminate\Support\Facades\Route;

Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
Route::post('/accounting/accounts', [AccountController::class, 'store'])->name('accounting.accounts.store');
Route::put('/accounting/accounts/{account}', [AccountController::class, 'update'])->name('accounting.accounts.update');
Route::delete('/accounting/accounts/{account}', [AccountController::class, 'destroy'])->name('accounting.accounts.destroy');
Route::post('/accounting/accounts/import', [AccountController::class, 'import'])->name('accounting.accounts.import');
Route::get('/accounting/accounts/export', [AccountController::class, 'export'])->name('accounting.accounts.export');
Route::get('/accounting/accounts/export/download', [AccountController::class, 'downloadExport'])->name('accounting.accounts.export.download')->middleware('signed');
Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
Route::get('/accounting/journal-entries/create', [AccountingController::class, 'createJournalEntry'])->name('accounting.journal-entries.create');
Route::post('/accounting/journal-entries', [AccountingController::class, 'storeJournalEntry'])->name('accounting.journal-entries.store');
Route::delete('/accounting/journal-entries/{journalEntry}', [AccountingController::class, 'destroyJournalEntry'])->name('accounting.journal-entries.destroy');
Route::get('/accounting/opening-balances', [OpeningBalancesController::class, 'index'])->name('accounting.opening-balances.index');
Route::post('/accounting/opening-balances', [OpeningBalancesController::class, 'store'])->name('accounting.opening-balances.store');
Route::post('/accounting/opening-balances/historical', [OpeningBalancesController::class, 'storeHistorical'])->name('accounting.opening-balances.historical');
Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trial-balance');
Route::get('/accounting/trial-balance/export/{format}', [AccountingController::class, 'exportTrialBalance'])->name('accounting.trial-balance.export');
Route::get('/accounting/journal-entries/export/{format}', [AccountingController::class, 'exportJournalEntries'])->name('accounting.journal-entries.export');
Route::get('/accounting/year-end-closing', [YearEndClosingController::class, 'index'])->name('accounting.closing');
Route::post('/accounting/year-end-closing', [YearEndClosingController::class, 'store'])->name('accounting.closing.store');
Route::post('/accounting/year-end-closing/reopen', [YearEndClosingController::class, 'reopen'])->name('accounting.closing.reopen');
Route::get('/accounting/fiscal-years', [FiscalYearsController::class, 'index'])->name('accounting.fiscal-years.index');
Route::post('/accounting/fiscal-years', [FiscalYearsController::class, 'store'])->name('accounting.fiscal-years.store');
Route::put('/accounting/fiscal-years/{fiscalYear}', [FiscalYearsController::class, 'update'])->name('accounting.fiscal-years.update');
Route::delete('/accounting/fiscal-years/{fiscalYear}', [FiscalYearsController::class, 'destroy'])->name('accounting.fiscal-years.destroy');
Route::get('/accounting/social-charges', [SocialChargesController::class, 'index'])->name('accounting.social-charges');
Route::post('/accounting/social-charges/calculate', [SocialChargesController::class, 'calculate'])->name('accounting.social-charges.calculate');
Route::post('/accounting/social-charges/post', [SocialChargesController::class, 'post'])->name('accounting.social-charges.post');
Route::get('/accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
Route::post('/accounting/budgets', [BudgetController::class, 'store'])->name('accounting.budgets.store');
Route::patch('/accounting/budgets/{budget}', [BudgetController::class, 'update'])->name('accounting.budgets.update');
Route::delete('/accounting/budgets/{budget}', [BudgetController::class, 'destroy'])->name('accounting.budgets.destroy');
Route::get('/accounting/export', [AccountingExportController::class, 'index'])->name('accounting.export');
Route::post('/accounting/export', [AccountingExportController::class, 'generate'])->name('accounting.export.generate');
Route::get('/accounting/export/download', [AccountingExportController::class, 'download'])->name('accounting.export.download')->middleware('signed');

// Tax declarations (feature-gated)
Route::middleware('feature:tax_declaration')->group(function () {
    Route::get('/accounting/tax-declarations', [TaxDeclarationController::class, 'index'])->name('accounting.tax-declarations.index');
    Route::post('/accounting/tax-declarations', [TaxDeclarationController::class, 'store'])->name('accounting.tax-declarations.store');
    Route::get('/accounting/tax-declarations/{taxDeclaration}', [TaxDeclarationController::class, 'show'])->name('accounting.tax-declarations.show');
    Route::post('/accounting/tax-declarations/{taxDeclaration}/finalize', [TaxDeclarationController::class, 'finalize'])->name('accounting.tax-declarations.finalize');
});

// Analytical accounting (feature-gated)
Route::middleware('feature:analytical')->group(function () {
    Route::get('/accounting/cost-centers', [CostCenterController::class, 'index'])->name('accounting.cost-centers.index');
    Route::post('/accounting/cost-centers', [CostCenterController::class, 'store'])->name('accounting.cost-centers.store');
    Route::put('/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'update'])->name('accounting.cost-centers.update');
    Route::delete('/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'destroy'])->name('accounting.cost-centers.destroy');

    Route::get('/accounting/analytical-report', [CostCenterController::class, 'analyticalReport'])->name('accounting.analytical-report.index');
});

// Multi-currency exchange rates (feature-gated)
Route::middleware('feature:multi_currency')->group(function () {
    Route::get('/accounting/exchange-rates', [ExchangeRateController::class, 'index'])->name('accounting.exchange-rates.index');
    Route::post('/accounting/exchange-rates', [ExchangeRateController::class, 'store'])->name('accounting.exchange-rates.store');
    Route::delete('/accounting/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy'])->name('accounting.exchange-rates.destroy');
    Route::post('/accounting/exchange-rates/fetch-ecb', [ExchangeRateController::class, 'fetchEcb'])->name('accounting.exchange-rates.fetch-ecb');
});

// Consolidation (feature-gated)
Route::middleware('feature:consolidation')->group(function () {
    Route::get('/accounting/consolidation', [ConsolidationController::class, 'index'])->name('accounting.consolidation.index');
    Route::post('/accounting/consolidation/groups', [ConsolidationController::class, 'storeGroup'])->name('accounting.consolidation.groups.store');
    Route::get('/accounting/consolidation/{group}/report', [ConsolidationController::class, 'report'])->name('accounting.consolidation.report');
    Route::post('/accounting/consolidation/{group}/eliminations', [ConsolidationController::class, 'storeElimination'])->name('accounting.consolidation.eliminations.store');
    Route::delete('/accounting/consolidation/eliminations/{consolidationElimination}', [ConsolidationController::class, 'destroyElimination'])->name('accounting.consolidation.eliminations.destroy');
});

// Account matching (formerly lettrage) — CE feature
Route::get('/accounting/account-matching', [LettrageController::class, 'index'])->name('accounting.account-matching.index');
Route::post('/accounting/account-matching', [LettrageController::class, 'store'])->name('accounting.account-matching.store');
Route::delete('/accounting/account-matching/{lettrageLot}', [LettrageController::class, 'destroy'])->name('accounting.account-matching.destroy');

// Legal Archiving (10-year retention, Swiss CO Art. 958f) — CE feature
Route::get('/accounting/archives', [LegalArchiveController::class, 'index'])->name('accounting.archives.index');
Route::post('/accounting/archives/{archive}/verify', [LegalArchiveController::class, 'verify'])->name('accounting.archives.verify');
Route::get('/accounting/archives/{archive}/download', [LegalArchiveController::class, 'download'])->name('accounting.archives.download');

// VAT Rates management
Route::get('/accounting/vat-rates', [VatRateController::class, 'index'])->name('accounting.vat-rates');
Route::post('/accounting/vat-rates', [VatRateController::class, 'store'])->name('accounting.vat-rates.store');
Route::put('/accounting/vat-rates/{vatRate}', [VatRateController::class, 'update'])->name('accounting.vat-rates.update');
Route::delete('/accounting/vat-rates/{vatRate}', [VatRateController::class, 'destroy'])->name('accounting.vat-rates.destroy');
