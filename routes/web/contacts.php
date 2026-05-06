<?php

use App\Domains\Contacts\Controllers\ContactController;
use App\Domains\Contacts\Controllers\CustomerController;
use App\Domains\Contacts\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// Unified contacts (new)
Route::resource('contacts', ContactController::class);

// Legacy scoped routes kept for backward-compatibility
Route::resource('customers', CustomerController::class);
Route::resource('suppliers', SupplierController::class);
