<?php

use App\Domains\Invoicing\Controllers\InvoiceController;
use App\Domains\Invoicing\Controllers\RecurringInvoiceController;
use Illuminate\Support\Facades\Route;

// Recurring must be registered BEFORE the invoices resource
// so GET /invoices/recurring is not caught by GET /invoices/{invoice}
Route::resource('invoices/recurring', RecurringInvoiceController::class)->names('invoices.recurring');

Route::resource('invoices', InvoiceController::class);
Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
Route::post('/invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->name('invoices.creditNote');
Route::get('/invoices/{invoice}/qr-pdf', [InvoiceController::class, 'downloadQrPdf'])->middleware('throttle:30,1')->name('invoices.qr-pdf');
Route::delete('/invoices/{invoice}/justificatif', [InvoiceController::class, 'removeJustificatif'])->name('invoices.justificatif.remove');
Route::get('/invoices/{invoice}/justificatif', [InvoiceController::class, 'downloadJustificatif'])->middleware('throttle:30,1')->name('invoices.justificatif.download');
Route::post('/invoices/{invoice}/reminder', [InvoiceController::class, 'sendReminder'])->name('invoices.reminder');
