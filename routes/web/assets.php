<?php

use App\Domains\Assets\Controllers\FixedAssetController;
use Illuminate\Support\Facades\Route;

Route::resource('assets', FixedAssetController::class);
Route::post('/assets/{asset}/depreciate', [FixedAssetController::class, 'depreciate'])->name('assets.depreciate');
Route::post('/assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('assets.dispose');
