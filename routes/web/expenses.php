<?php

use App\Domains\Expenses\Controllers\ExpenseController;
use App\Domains\Expenses\Controllers\ExpenseReceiptController;
use App\Domains\Expenses\Controllers\ExpenseWorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/expenses/scan-receipt', [ExpenseReceiptController::class, 'scanReceipt'])->name('expenses.scan-receipt');
Route::get('/expenses/scan-receipt/{scanId}', [ExpenseReceiptController::class, 'scanReceiptStatus'])->name('expenses.scan-receipt.status');
Route::resource('expenses', ExpenseController::class);
Route::post('/expenses/{expense}/approve', [ExpenseWorkflowController::class, 'approve'])->name('expenses.approve');
Route::post('/expenses/{expense}/post', [ExpenseWorkflowController::class, 'postToLedger'])->name('expenses.post');
Route::delete('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'removeReceipt'])->name('expenses.receipt.remove');
Route::get('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'downloadReceipt'])->middleware('throttle:30,1')->name('expenses.receipt.download');
