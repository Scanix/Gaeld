<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('plugins/example')->group(function () {
    Route::get('/', function () {
        return 'Example Plugin is active!';
    })->name('plugins.example.index');
});
