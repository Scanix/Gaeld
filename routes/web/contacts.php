<?php

use App\Domains\Contacts\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// Trash view + restore must be registered before the resource route so
// GET /contacts/trashed isn't captured by GET /contacts/{contact}.
Route::get('/contacts/trashed', [ContactController::class, 'trashed'])->name('contacts.trashed');
Route::post('/contacts/{contact}/restore', [ContactController::class, 'restore'])->name('contacts.restore');

Route::resource('contacts', ContactController::class);
