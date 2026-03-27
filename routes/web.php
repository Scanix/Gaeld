<?php

use App\Domains\Accounting\Controllers\AccountController;
use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Accounting\Controllers\YearEndClosingController;
use App\Domains\Banking\Controllers\BankingController;
use App\Domains\Banking\Controllers\ReconciliationController;
use App\Domains\Contacts\Controllers\ContactPersonController;
use App\Domains\Contacts\Controllers\CustomerController;
use App\Domains\Contacts\Controllers\SupplierController;
use App\Domains\Expenses\Controllers\ExpenseController;
use App\Domains\Invoicing\Controllers\InvoiceController;
use App\Domains\Organizations\Controllers\InvitationController;
use App\Domains\Organizations\Controllers\MemberController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Organizations\Controllers\OrganizationSettingsController;
use App\Domains\Organizations\Controllers\ActivityLogController;
use App\Domains\Organizations\Controllers\OnboardingController;
use App\Domains\Api\Controllers\TokenSettingsController;
use App\Domains\Api\Controllers\WebhookSettingsController;
use App\Domains\Reporting\Controllers\ReportController;
use App\Domains\Users\Controllers\AuthenticatedSessionController;
use App\Domains\Users\Controllers\EmailVerificationController;
use App\Domains\Users\Controllers\PasskeyController;
use App\Domains\Users\Controllers\PasswordResetController;
use App\Domains\Users\Controllers\RegisteredUserController;
use App\Domains\Users\Controllers\TwoFactorChallengeController;
use App\Domains\Users\Controllers\TwoFactorController;
use App\Domains\Users\Controllers\UserController;
use App\Domains\Reporting\Controllers\DashboardController;
use App\Domains\Organizations\Controllers\SetupWizardController;
use App\Http\Controllers\GlobalSearchController;
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

    // Passkey login (unauthenticated)
    Route::post('/passkey/login/options', [PasskeyController::class, 'loginOptions'])->middleware('throttle:10,1');
    Route::post('/passkey/login', [PasskeyController::class, 'login'])->middleware('throttle:5,1');

    // Two-factor challenge
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.create');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1')->name('two-factor.store');

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

// Authenticated routes
Route::middleware(['auth', 'verified', 'org', 'org-2fa'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Global search
    Route::get('/search', GlobalSearchController::class)->name('search');

    // Accounting
    Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
    Route::post('/accounting/accounts', [AccountController::class, 'store'])->name('accounting.accounts.store');
    Route::put('/accounting/accounts/{account}', [AccountController::class, 'update'])->name('accounting.accounts.update');
    Route::delete('/accounting/accounts/{account}', [AccountController::class, 'destroy'])->name('accounting.accounts.destroy');
    Route::post('/accounting/accounts/import', [AccountController::class, 'import'])->name('accounting.accounts.import');
    Route::get('/accounting/accounts/export', [AccountController::class, 'export'])->name('accounting.accounts.export');
    Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
    Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trialBalance');
    Route::get('/accounting/year-end-closing', [YearEndClosingController::class, 'index'])->name('accounting.closing');
    Route::post('/accounting/year-end-closing', [YearEndClosingController::class, 'store'])->name('accounting.closing.store');

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::get('/invoices/{invoice}/qr-pdf', [InvoiceController::class, 'downloadQrPdf'])->middleware('throttle:30,1')->name('invoices.qr-pdf');
    Route::delete('/invoices/{invoice}/justificatif', [InvoiceController::class, 'removeJustificatif'])->name('invoices.justificatif.remove');
    Route::get('/invoices/{invoice}/justificatif', [InvoiceController::class, 'downloadJustificatif'])->middleware('throttle:30,1')->name('invoices.justificatif.download');

    // Expenses
    Route::post('/expenses/scan-receipt', [ExpenseController::class, 'scanReceipt'])->name('expenses.scan-receipt');
    Route::get('/expenses/scan-receipt/{scanId}', [ExpenseController::class, 'scanReceiptStatus'])->name('expenses.scan-receipt.status');
    Route::resource('expenses', ExpenseController::class);
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/{expense}/post', [ExpenseController::class, 'postToLedger'])->name('expenses.post');
    Route::delete('/expenses/{expense}/receipt', [ExpenseController::class, 'removeReceipt'])->name('expenses.receipt.remove');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])->middleware('throttle:30,1')->name('expenses.receipt.download');

    // Reports
    Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('reports.pnl');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balanceSheet');

    // Banking (CE — core banking features)
    Route::get('/banking', [BankingController::class, 'index'])->name('banking.index');
    Route::get('/banking/{bankAccount}', [BankingController::class, 'show'])->name('banking.show');
    Route::post('/banking', [BankingController::class, 'store'])->name('banking.store');
    Route::post('/banking/{bankAccount}/transactions', [BankingController::class, 'recordTransaction'])->name('banking.transactions.store');

    // Reconciliation (CE — manual reconciliation + CAMT import)
    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::get('/reconciliation/{bankAccount}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
    Route::post('/reconciliation/{bankAccount}/import', [ReconciliationController::class, 'import'])->name('reconciliation.import');
    Route::post('/reconciliation/transactions/{transaction}/invoice', [ReconciliationController::class, 'reconcileInvoice'])->name('reconciliation.invoice');
    Route::post('/reconciliation/transactions/{transaction}/expense', [ReconciliationController::class, 'reconcileExpense'])->name('reconciliation.expense');
    Route::post('/reconciliation/transactions/{transaction}/manual', [ReconciliationController::class, 'reconcileManual'])->name('reconciliation.manual');
    Route::post('/reconciliation/matches/{match}/confirm', [ReconciliationController::class, 'confirmMatch'])->name('reconciliation.confirm');

    // Auto-reconciliation (EE only)
    Route::middleware('feature:auto_reconciliation')->group(function () {
        Route::post('/reconciliation/{bankAccount}/auto', [ReconciliationController::class, 'autoReconcile'])->name('reconciliation.auto');
    });

    // Bank sync (EE only)
    Route::middleware('feature:bank_sync')->group(function () {
        // Future: bank API sync routes
    });

    // Organizations
    Route::resource('organizations', OrganizationController::class)->only(['index', 'show', 'store', 'update']);
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switchOrganization'])->name('organizations.switch');

    // Organization settings
    Route::get('/settings', [OrganizationSettingsController::class, 'show'])->name('settings');
    Route::put('/settings/general', [OrganizationSettingsController::class, 'updateGeneral'])->name('settings.general');
    Route::put('/settings/invoice', [OrganizationSettingsController::class, 'updateInvoice'])->name('settings.invoice');
    Route::post('/settings/invoice/logo', [OrganizationSettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
    Route::delete('/settings/invoice/logo', [OrganizationSettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    Route::get('/settings/logo', [OrganizationSettingsController::class, 'serveLogo'])->name('settings.logo');
    Route::put('/settings/communications', [OrganizationSettingsController::class, 'updateCommunications'])->name('settings.communications');

    // Activity log
    Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('settings.activity-log');

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

    // Organization members
    Route::post('/organizations/{organization}/members/{user}/role', [MemberController::class, 'updateRole'])->name('organizations.members.updateRole');
    Route::delete('/organizations/{organization}/members/{user}', [MemberController::class, 'remove'])->name('organizations.members.remove');
    Route::post('/organizations/{organization}/leave', [MemberController::class, 'leave'])->name('organizations.leave');

    // Organization invitations
    Route::post('/organizations/{organization}/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,60')->name('organizations.invitations.store');
    Route::delete('/organizations/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('organizations.invitations.destroy');
    Route::post('/organizations/{organization}/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('organizations.invitations.resend');

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

    // Contacts — Customers (CE)
    Route::resource('customers', CustomerController::class);

    // Contacts — Suppliers (CE)
    Route::resource('suppliers', SupplierController::class);

    // Contact persons (nested under customers/suppliers)
    Route::post('/{contactableType}/{contactableId}/contact-persons', [ContactPersonController::class, 'store'])
        ->where('contactableType', 'customers|suppliers')
        ->name('contact-persons.store');
    Route::put('/{contactableType}/{contactableId}/contact-persons/{contactPerson}', [ContactPersonController::class, 'update'])
        ->where('contactableType', 'customers|suppliers')
        ->name('contact-persons.update');
    Route::delete('/{contactableType}/{contactableId}/contact-persons/{contactPerson}', [ContactPersonController::class, 'destroy'])
        ->where('contactableType', 'customers|suppliers')
        ->name('contact-persons.destroy');
});
