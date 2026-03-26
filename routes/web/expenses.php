<?php

use App\Domains\Expenses\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::post('/expenses/scan-receipt', [ExpenseController::class, 'scanReceipt'])->name('expenses.scan-receipt');
Route::get('/expenses/scan-receipt/{scanId}', [ExpenseController::class, 'scanReceiptStatus'])->name('expenses.scan-receipt.status');
Route::resource('expenses', ExpenseController::class);
Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
Route::post('/expenses/{expense}/post', [ExpenseController::class, 'postToLedger'])->name('expenses.post');
Route::delete('/expenses/{expense}/receipt', [ExpenseController::class, 'removeReceipt'])->name('expenses.receipt.remove');
Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])->middleware('throttle:30,1')->name('expenses.receipt.download');
