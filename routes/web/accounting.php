<?php

use App\Domains\Accounting\Controllers\AccountController;
use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Accounting\Controllers\BudgetController;
use App\Domains\Accounting\Controllers\LegalArchiveController;
use App\Domains\Accounting\Controllers\LettrageController;
use App\Domains\Accounting\Controllers\SocialChargesController;
use App\Domains\Accounting\Controllers\YearEndClosingController;
use App\Domains\Reporting\Controllers\AccountingExportController;
use Illuminate\Support\Facades\Route;

Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
Route::post('/accounting/accounts', [AccountController::class, 'store'])->name('accounting.accounts.store');
Route::put('/accounting/accounts/{account}', [AccountController::class, 'update'])->name('accounting.accounts.update');
Route::delete('/accounting/accounts/{account}', [AccountController::class, 'destroy'])->name('accounting.accounts.destroy');
Route::post('/accounting/accounts/import', [AccountController::class, 'import'])->name('accounting.accounts.import');
Route::get('/accounting/accounts/export', [AccountController::class, 'export'])->name('accounting.accounts.export');
Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trialBalance');
Route::get('/accounting/trial-balance/export/{format}', [AccountingController::class, 'exportTrialBalance'])->name('accounting.trialBalance.export');
Route::get('/accounting/journal-entries/export/{format}', [AccountingController::class, 'exportJournalEntries'])->name('accounting.journalEntries.export');
Route::get('/accounting/year-end-closing', [YearEndClosingController::class, 'index'])->name('accounting.closing');
Route::post('/accounting/year-end-closing', [YearEndClosingController::class, 'store'])->name('accounting.closing.store');
Route::get('/accounting/social-charges', [SocialChargesController::class, 'index'])->name('accounting.socialCharges');
Route::post('/accounting/social-charges/calculate', [SocialChargesController::class, 'calculate'])->name('accounting.socialCharges.calculate');
Route::post('/accounting/social-charges/post', [SocialChargesController::class, 'post'])->name('accounting.socialCharges.post');
Route::get('/accounting/budgets', [BudgetController::class, 'index'])->name('accounting.budgets');
Route::post('/accounting/budgets', [BudgetController::class, 'store'])->name('accounting.budgets.store');
Route::delete('/accounting/budgets/{budget}', [BudgetController::class, 'destroy'])->name('accounting.budgets.destroy');
Route::get('/accounting/export', [AccountingExportController::class, 'index'])->name('accounting.export');
Route::post('/accounting/export', [AccountingExportController::class, 'generate'])->name('accounting.export.generate');
Route::get('/accounting/export/download', [AccountingExportController::class, 'download'])->name('accounting.export.download')->middleware('signed');

// Lettrage (account line matching) — CE feature
Route::get('/accounting/lettrage', [LettrageController::class, 'index'])->name('accounting.lettrage.index');
Route::post('/accounting/lettrage', [LettrageController::class, 'store'])->name('accounting.lettrage.store');
Route::delete('/accounting/lettrage/{lettrageLot}', [LettrageController::class, 'destroy'])->name('accounting.lettrage.destroy');

// Legal Archiving (10-year retention, Swiss CO Art. 958f) — CE feature
Route::get('/accounting/archives', [LegalArchiveController::class, 'index'])->name('accounting.archives.index');
Route::post('/accounting/archives/{archive}/verify', [LegalArchiveController::class, 'verify'])->name('accounting.archives.verify');
Route::get('/accounting/archives/{archive}/download', [LegalArchiveController::class, 'download'])->name('accounting.archives.download');
