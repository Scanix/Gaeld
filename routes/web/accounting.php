<?php

use App\Domains\Accounting\Controllers\AccountController;
use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Accounting\Controllers\YearEndClosingController;
use Illuminate\Support\Facades\Route;

Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
Route::post('/accounting/accounts', [AccountController::class, 'store'])->name('accounting.accounts.store');
Route::put('/accounting/accounts/{account}', [AccountController::class, 'update'])->name('accounting.accounts.update');
Route::delete('/accounting/accounts/{account}', [AccountController::class, 'destroy'])->name('accounting.accounts.destroy');
Route::post('/accounting/accounts/import', [AccountController::class, 'import'])->name('accounting.accounts.import');
Route::get('/accounting/accounts/export', [AccountController::class, 'export'])->name('accounting.accounts.export');
Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trialBalance');
Route::get('/accounting/year-end-closing', [YearEndClosingController::class, 'index'])->name('accounting.closing');
Route::post('/accounting/year-end-closing', [YearEndClosingController::class, 'store'])->name('accounting.closing.store');
