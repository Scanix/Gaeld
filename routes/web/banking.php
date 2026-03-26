<?php

use App\Domains\Banking\Controllers\BankingController;
use App\Domains\Banking\Controllers\ReconciliationController;
use Illuminate\Support\Facades\Route;

// Core banking features (CE)
Route::get('/banking', [BankingController::class, 'index'])->name('banking.index');
Route::get('/banking/{bankAccount}', [BankingController::class, 'show'])->name('banking.show');
Route::post('/banking', [BankingController::class, 'store'])->name('banking.store');
Route::post('/banking/{bankAccount}/transactions', [BankingController::class, 'recordTransaction'])->name('banking.transactions.store');
Route::post('/banking/{bankAccount}/import', [BankingController::class, 'import'])->name('banking.import');

// Manual reconciliation + CAMT import (CE)
Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
Route::get('/reconciliation/{bankAccount}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
Route::post('/reconciliation/{bankAccount}/import', [ReconciliationController::class, 'import'])->name('reconciliation.import');
Route::post('/reconciliation/transactions/{transaction}/invoice', [ReconciliationController::class, 'reconcileInvoice'])->name('reconciliation.invoice');
Route::post('/reconciliation/transactions/{transaction}/expense', [ReconciliationController::class, 'reconcileExpense'])->name('reconciliation.expense');
Route::post('/reconciliation/transactions/{transaction}/manual', [ReconciliationController::class, 'reconcileManual'])->name('reconciliation.manual');
Route::post('/reconciliation/matches/{match}/confirm', [ReconciliationController::class, 'confirmMatch'])->name('reconciliation.confirm');

// Auto-reconciliation (EE only)
Route::middleware('feature:auto_reconciliation')->group(function () {
    Route::post('/reconciliation/{bankAccount}/auto', [ReconciliationController::class, 'autoReconcile'])->name('reconciliation.auto');
});

// Bank sync (EE only — future routes)
Route::middleware('feature:bank_sync')->group(function () {
    // Future: bank API sync routes
});
