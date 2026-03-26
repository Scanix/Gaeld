<?php

use App\Domains\Users\Controllers\PasskeyController;
use App\Domains\Users\Controllers\TwoFactorController;
use App\Domains\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// User profile
Route::get('/profile', [UserController::class, 'profile'])->name('profile');
Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
Route::put('/profile/password', [UserController::class, 'updatePassword'])->name('profile.password');
Route::post('/profile/toggle-help', [UserController::class, 'toggleHelp'])->name('profile.toggle-help');
Route::post('/profile/export', [UserController::class, 'exportData'])->name('profile.export');
Route::get('/profile/export/download/{filename}', [UserController::class, 'downloadExport'])->name('profile.export.download')->middleware('signed');
Route::delete('/profile', [UserController::class, 'destroyAccount'])->name('profile.destroy');

// Two-factor authentication management
Route::post('/profile/two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('two-factor.recovery-codes');
Route::post('/profile/two-factor/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes.regenerate');

// Passkey management
Route::post('/profile/passkeys/register/options', [PasskeyController::class, 'registerOptions'])->name('passkeys.register.options');
Route::post('/profile/passkeys/register', [PasskeyController::class, 'register'])->name('passkeys.register');
Route::get('/profile/passkeys', [PasskeyController::class, 'index'])->name('passkeys.index');
Route::delete('/profile/passkeys/{credential}', [PasskeyController::class, 'destroy'])->name('passkeys.destroy');
