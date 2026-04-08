<?php

use App\Domains\Invoicing\Controllers\InvoiceCommunicationController;
use App\Domains\Invoicing\Controllers\InvoiceController;
use App\Domains\Invoicing\Controllers\InvoiceDocumentController;
use App\Domains\Invoicing\Controllers\InvoiceLifecycleController;
use App\Domains\Invoicing\Controllers\RecurringInvoiceController;
use Illuminate\Support\Facades\Route;

// Recurring must be registered BEFORE the invoices resource
// so GET /invoices/recurring is not caught by GET /invoices/{invoice}
Route::resource('invoices/recurring', RecurringInvoiceController::class)->names('invoices.recurring');
Route::post('/invoices/recurring/{recurring}/pause', [RecurringInvoiceController::class, 'pause'])->name('invoices.recurring.pause');
Route::post('/invoices/recurring/{recurring}/resume', [RecurringInvoiceController::class, 'resume'])->name('invoices.recurring.resume');

Route::resource('invoices', InvoiceController::class);
Route::post('/invoices/{invoice}/finalize', [InvoiceLifecycleController::class, 'finalize'])->name('invoices.finalize');
Route::post('/invoices/{invoice}/cancel', [InvoiceLifecycleController::class, 'cancel'])->name('invoices.cancel');
Route::post('/invoices/{invoice}/payment', [InvoiceLifecycleController::class, 'recordPayment'])->name('invoices.payment');
Route::post('/invoices/{invoice}/duplicate', [InvoiceLifecycleController::class, 'duplicate'])->name('invoices.duplicate');
Route::post('/invoices/{invoice}/credit-note', [InvoiceLifecycleController::class, 'creditNote'])->name('invoices.creditNote');
Route::post('/invoices/{invoice}/send', [InvoiceCommunicationController::class, 'sendInvoice'])->name('invoices.send');
Route::get('/invoices/{invoice}/qr-pdf', [InvoiceDocumentController::class, 'downloadQrPdf'])->middleware('throttle:30,1')->name('invoices.qr-pdf');
Route::delete('/invoices/{invoice}/justificatif', [InvoiceDocumentController::class, 'removeJustificatif'])->name('invoices.justificatif.remove');
Route::get('/invoices/{invoice}/justificatif', [InvoiceDocumentController::class, 'downloadJustificatif'])->middleware('throttle:30,1')->name('invoices.justificatif.download');
Route::post('/invoices/{invoice}/reminder', [InvoiceCommunicationController::class, 'sendReminder'])->name('invoices.reminder');
Route::delete('/invoices/{invoice}/purge', [InvoiceLifecycleController::class, 'purge'])->withTrashed()->name('invoices.purge');
