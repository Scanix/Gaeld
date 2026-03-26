<?php

use App\Domains\Invoicing\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::resource('invoices', InvoiceController::class);
Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
Route::get('/invoices/{invoice}/qr-pdf', [InvoiceController::class, 'downloadQrPdf'])->middleware('throttle:30,1')->name('invoices.qr-pdf');
Route::delete('/invoices/{invoice}/justificatif', [InvoiceController::class, 'removeJustificatif'])->name('invoices.justificatif.remove');
Route::get('/invoices/{invoice}/justificatif', [InvoiceController::class, 'downloadJustificatif'])->middleware('throttle:30,1')->name('invoices.justificatif.download');
