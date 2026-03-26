<?php

use App\Domains\Contacts\Controllers\CustomerController;
use App\Domains\Contacts\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::resource('customers', CustomerController::class);
Route::resource('suppliers', SupplierController::class);
