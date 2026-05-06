<?php

use App\Domains\Contacts\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::resource('contacts', ContactController::class);
