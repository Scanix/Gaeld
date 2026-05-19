<?php

use App\Domains\Expenses\Controllers\ExpenseController;
use App\Domains\Expenses\Controllers\ExpenseReceiptController;
use App\Domains\Expenses\Controllers\ExpenseWorkflowController;
use App\Domains\Expenses\Controllers\ReceiptScanIndexController;
use App\Domains\Expenses\Controllers\RecurringExpenseController;
use Illuminate\Support\Facades\Route;

Route::post('/expenses/scan-receipt', [ExpenseReceiptController::class, 'scanReceipt'])->middleware('throttle:20,1')->name('expenses.scan-receipt');
Route::get('/expenses/scan-receipt/{scanId}', [ExpenseReceiptController::class, 'scanReceiptStatus'])->name('expenses.scan-receipt.status');
Route::get('/expenses/receipt-scans', [ReceiptScanIndexController::class, 'index'])->name('expenses.receipt-scans.index');
Route::delete('/expenses/receipt-scans/{scanId}', [ReceiptScanIndexController::class, 'discard'])->name('expenses.receipt-scans.discard');
Route::post('/expenses/recurring/{recurring}/pause', [RecurringExpenseController::class, 'pause'])->name('expenses.recurring.pause');
Route::post('/expenses/recurring/{recurring}/resume', [RecurringExpenseController::class, 'resume'])->name('expenses.recurring.resume');
Route::resource('expenses/recurring', RecurringExpenseController::class, ['as' => 'expenses'])->parameter('recurring', 'recurring');
Route::resource('expenses', ExpenseController::class);
Route::post('/expenses/{expense}/approve', [ExpenseWorkflowController::class, 'approve'])->name('expenses.approve');
Route::post('/expenses/{expense}/post', [ExpenseWorkflowController::class, 'postToLedger'])->name('expenses.post');
Route::delete('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'removeReceipt'])->name('expenses.receipt.remove');
Route::get('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'downloadReceipt'])->middleware('throttle:30,1')->name('expenses.receipt.download');
