<?php

use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Accounting\Controllers\YearEndClosingController;
use App\Domains\Banking\Controllers\BankingController;
use App\Domains\Banking\Controllers\ReconciliationController;
use App\Domains\Contacts\Controllers\CustomerController;
use App\Domains\Contacts\Controllers\SupplierController;
use App\Domains\Expenses\Controllers\ExpenseController;
use App\Domains\Invoicing\Controllers\InvoiceController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Organizations\Controllers\OnboardingController;
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
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

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

// Logout (available to any authenticated user)
Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Authenticated routes
Route::middleware(['auth', 'verified', 'org', 'org-2fa'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Accounting
    Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
    Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
    Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trialBalance');
    Route::get('/accounting/year-end-closing', [YearEndClosingController::class, 'index'])->name('accounting.closing');
    Route::post('/accounting/year-end-closing', [YearEndClosingController::class, 'store'])->name('accounting.closing.store');

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::get('/invoices/{invoice}/qr-pdf', [InvoiceController::class, 'downloadQrPdf'])->name('invoices.qr-pdf');
    Route::delete('/invoices/{invoice}/justificatif', [InvoiceController::class, 'removeJustificatif'])->name('invoices.justificatif.remove');
    Route::get('/invoices/{invoice}/justificatif', [InvoiceController::class, 'downloadJustificatif'])->name('invoices.justificatif.download');

    // Expenses
    Route::resource('expenses', ExpenseController::class);
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/{expense}/post', [ExpenseController::class, 'postToLedger'])->name('expenses.post');
    Route::delete('/expenses/{expense}/receipt', [ExpenseController::class, 'removeReceipt'])->name('expenses.receipt.remove');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])->name('expenses.receipt.download');

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

    // User profile
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [UserController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/toggle-help', [UserController::class, 'toggleHelp'])->name('profile.toggle-help');
    Route::get('/profile/export', [UserController::class, 'exportData'])->name('profile.export');
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
});
