<?php

use App\Domains\Users\Controllers\NotificationController;
use App\Domains\Users\Controllers\PasskeyController;
use App\Domains\Users\Controllers\TwoFactorController;
use App\Domains\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// User profile
Route::get('/profile', [UserController::class, 'profile'])->name('profile');
Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
Route::put('/profile/password', [UserController::class, 'updatePassword'])->name('profile.password');
Route::post('/profile/toggle-help', [UserController::class, 'toggleHelp'])->name('profile.toggle-help');
Route::post('/profile/onboarding/dismiss', [UserController::class, 'dismissOnboarding'])->name('profile.onboarding.dismiss');
Route::post('/profile/onboarding/reset', [UserController::class, 'resetOnboarding'])->name('profile.onboarding.reset');
Route::post('/profile/export', [UserController::class, 'exportData'])->name('profile.export');
Route::get('/profile/export/download/{filename}', [UserController::class, 'downloadExport'])->name('profile.export.download')->middleware('signed');
Route::delete('/profile', [UserController::class, 'destroyAccount'])->name('profile.destroy');

// Email change
Route::put('/profile/email', [UserController::class, 'requestEmailChange'])->name('profile.email');
Route::get('/profile/email/verify/{token}', [UserController::class, 'confirmEmailChange'])->name('profile.email.verify')->middleware('signed');
Route::delete('/profile/email', [UserController::class, 'cancelEmailChange'])->name('profile.email.cancel');

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

// Notification preferences
Route::put('/profile/notification-preferences', [UserController::class, 'updateNotificationPreferences'])->name('profile.notification-preferences');

// Notifications
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
