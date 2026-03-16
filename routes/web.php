<?php

use App\Domains\Accounting\Controllers\AccountingController;
use App\Domains\Banking\Controllers\BankingController;
use App\Domains\Expenses\Controllers\ExpenseController;
use App\Domains\Invoicing\Controllers\InvoiceController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Reporting\Controllers\ReportController;
use App\Domains\Users\Controllers\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SetupWizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Setup wizard (only accessible if no organization exists)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

    Route::get('/setup', [SetupWizardController::class, 'index'])->name('setup.index');
    Route::post('/setup', [SetupWizardController::class, 'store'])->name('setup.store');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Accounting
    Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('accounting.chart');
    Route::get('/accounting/journal-entries', [AccountingController::class, 'journalEntries'])->name('accounting.journal');
    Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trial-balance');

    // Invoices
    Route::resource('invoices', InvoiceController::class)->except(['edit', 'update', 'destroy']);
    Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.payment');

    // Expenses
    Route::resource('expenses', ExpenseController::class)->except(['edit', 'update', 'destroy']);
    Route::post('/expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');

    // Reports
    Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('reports.pnl');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');

    // Banking (feature-flagged)
    Route::middleware('feature:bank_sync')->group(function () {
        Route::get('/banking', [BankingController::class, 'index'])->name('banking.index');
        Route::get('/banking/{bankAccount}', [BankingController::class, 'show'])->name('banking.show');
        Route::post('/banking', [BankingController::class, 'store'])->name('banking.store');
        Route::post('/banking/{bankAccount}/transactions', [BankingController::class, 'recordTransaction'])->name('banking.transactions.store');
    });

    // Organizations
    Route::resource('organizations', OrganizationController::class)->only(['index', 'show', 'store', 'update']);

    // User profile
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [UserController::class, 'updatePassword'])->name('profile.password');
});
