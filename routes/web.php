<?php

use App\Domains\Api\Controllers\TokenSettingsController;
use App\Domains\Api\Controllers\WebhookSettingsController;
use App\Domains\Contacts\Controllers\ContactPersonController;
use App\Domains\Organizations\Controllers\InvitationController;
use App\Domains\Organizations\Controllers\OnboardingController;
use App\Domains\Organizations\Controllers\SetupWizardController;
use App\Domains\Reporting\Controllers\DashboardController;
use App\Domains\Users\Controllers\AuthenticatedSessionController;
use App\Domains\Users\Controllers\EmailVerificationController;
use App\Domains\Users\Controllers\PasswordResetController;
use App\Domains\Users\Controllers\RegisteredUserController;
use App\Domains\Users\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\OnboardingDismissController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Setup wizard (only accessible if no organization exists)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

    // Two-factor challenge
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.create');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1')->name('two-factor.store');
    Route::post('/two-factor-challenge/passkey/options', [TwoFactorChallengeController::class, 'passkeyOptions'])->middleware('throttle:10,1')->name('two-factor.passkey.options');
    Route::post('/two-factor-challenge/passkey/verify', [TwoFactorChallengeController::class, 'passkeyVerify'])->middleware('throttle:5,1')->name('two-factor.passkey.verify');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');

    Route::get('/setup', [SetupWizardController::class, 'index'])->name('setup.index');
    Route::post('/setup', [SetupWizardController::class, 'store'])->name('setup.store');
});

// Email verification (authenticated but not yet verified)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Onboarding (verified but no organization yet)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

// Invitation accept (authenticated but no org middleware needed)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
});

// Logout (available to any authenticated user)
Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
// GET fallback: render a tiny auto-submitting POST form so direct links / browser typed URLs still log out.
Route::middleware('auth')->get('/logout', fn () => response(
    '<!doctype html><html><head><meta charset="utf-8"><title>Signing out…</title></head>'
    .'<body><form id="f" method="POST" action="'.route('logout').'">'
    .'<input type="hidden" name="_token" value="'.csrf_token().'">'
    .'<noscript><button type="submit">Sign out</button></noscript>'
    .'</form><script>document.getElementById("f").submit();</script></body></html>'
));

// Root redirect — send all visitors to the dashboard (auth middleware handles unauthenticated users)
Route::redirect('/', '/dashboard')->name('home');

// Authenticated routes
Route::middleware(['auth', 'verified', 'org', 'org-2fa', 'subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/onboarding/dismiss', OnboardingDismissController::class)->name('onboarding.dismiss');

    // Global search
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:60,1')->name('search');

    // API tokens & webhooks settings (EE only)
    Route::middleware('feature:api_access')->group(function () {
        Route::get('/settings/api-tokens', [TokenSettingsController::class, 'index'])->name('settings.api-tokens');
        Route::post('/settings/api-tokens/personal', [TokenSettingsController::class, 'storePersonal'])->name('settings.api-tokens.personal.store');
        Route::post('/settings/api-tokens/organization', [TokenSettingsController::class, 'storeOrganization'])->name('settings.api-tokens.organization.store');
        Route::delete('/settings/api-tokens/personal/{token}', [TokenSettingsController::class, 'destroyPersonal'])->name('settings.api-tokens.personal.destroy');
        Route::delete('/settings/api-tokens/organization/{token}', [TokenSettingsController::class, 'destroyOrganization'])->name('settings.api-tokens.organization.destroy');

        Route::get('/settings/webhooks', [WebhookSettingsController::class, 'index'])->name('settings.webhooks');
        Route::post('/settings/webhooks', [WebhookSettingsController::class, 'store'])->name('settings.webhooks.store');
        Route::put('/settings/webhooks/{webhook}', [WebhookSettingsController::class, 'update'])->name('settings.webhooks.update');
        Route::delete('/settings/webhooks/{webhook}', [WebhookSettingsController::class, 'destroy'])->name('settings.webhooks.destroy');
        Route::post('/settings/webhooks/{webhook}/regenerate-secret', [WebhookSettingsController::class, 'regenerateSecret'])->name('settings.webhooks.regenerate-secret');
    });

    // Contact persons (nested under contacts)
    Route::post('/{contactableType}/{contactableId}/contact-persons', [ContactPersonController::class, 'store'])
        ->where('contactableType', 'contacts')
        ->name('contact-persons.store');
    Route::put('/{contactableType}/{contactableId}/contact-persons/{contactPerson}', [ContactPersonController::class, 'update'])
        ->where('contactableType', 'contacts')
        ->name('contact-persons.update');
    Route::delete('/{contactableType}/{contactableId}/contact-persons/{contactPerson}', [ContactPersonController::class, 'destroy'])
        ->where('contactableType', 'contacts')
        ->name('contact-persons.destroy');

    // Domain-specific route files
    require __DIR__.'/web/accounting.php';
    require __DIR__.'/web/reporting.php';
    require __DIR__.'/web/invoicing.php';
    require __DIR__.'/web/expenses.php';
    require __DIR__.'/web/banking.php';
    require __DIR__.'/web/contacts.php';
    require __DIR__.'/web/organizations.php';
    require __DIR__.'/web/users.php';
    require __DIR__.'/web/assets.php';
    require __DIR__.'/web/payroll.php';
    require __DIR__.'/web/migration.php';
});
