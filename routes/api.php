<?php

use App\Domains\Api\Controllers\AccountApiController;
use App\Domains\Api\Controllers\ApiTokenController;
use App\Domains\Api\Controllers\BankAccountApiController;
use App\Domains\Api\Controllers\CustomerApiController;
use App\Domains\Api\Controllers\ExpenseApiController;
use App\Domains\Api\Controllers\InvoiceApiController;
use App\Domains\Api\Controllers\OrgTokenController;
use App\Domains\Api\Controllers\WebhookApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 and protected by Sanctum auth
| + organization resolution via the API token's organization_id.
| Access requires the FEATURE_API_ACCESS flag to be enabled.
|
| Two token types:
|  - Personal tokens: created by users, scoped to one org, requires membership
|  - Organization tokens: managed by admins, survive user removal
|
*/

Route::middleware(['auth:sanctum', 'api-org', 'feature:api_access', 'throttle:api'])->prefix('v1')->group(function () {

    // Personal token management (user's own tokens)
    Route::get('/tokens', [ApiTokenController::class, 'index'])->name('api.tokens.index');
    Route::post('/tokens', [ApiTokenController::class, 'store'])->name('api.tokens.store');
    Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');

    // Organization token management (admin-managed, org-level tokens)
    Route::get('/org-tokens', [OrgTokenController::class, 'index'])->name('api.org-tokens.index');
    Route::post('/org-tokens', [OrgTokenController::class, 'store'])->name('api.org-tokens.store');
    Route::delete('/org-tokens/{token}', [OrgTokenController::class, 'destroy'])->name('api.org-tokens.destroy');

    // Reference data
    Route::get('/meta/abilities', [ApiTokenController::class, 'abilities'])->name('api.meta.abilities');
    Route::get('/meta/webhook-events', [ApiTokenController::class, 'webhookEvents'])->name('api.meta.webhook-events');

    // Customers
    Route::apiResource('customers', CustomerApiController::class)->names([
        'index' => 'api.customers.index',
        'show' => 'api.customers.show',
        'store' => 'api.customers.store',
        'update' => 'api.customers.update',
        'destroy' => 'api.customers.destroy',
    ]);

    // Invoices
    Route::apiResource('invoices', InvoiceApiController::class)->names([
        'index' => 'api.invoices.index',
        'show' => 'api.invoices.show',
        'store' => 'api.invoices.store',
        'update' => 'api.invoices.update',
        'destroy' => 'api.invoices.destroy',
    ]);

    // Expenses
    Route::apiResource('expenses', ExpenseApiController::class)->names([
        'index' => 'api.expenses.index',
        'show' => 'api.expenses.show',
        'store' => 'api.expenses.store',
        'update' => 'api.expenses.update',
        'destroy' => 'api.expenses.destroy',
    ]);

    // Accounts (read-only)
    Route::get('/accounts', [AccountApiController::class, 'index'])->name('api.accounts.index');
    Route::get('/accounts/{account}', [AccountApiController::class, 'show'])->name('api.accounts.show');

    // Bank Accounts (read-only)
    Route::get('/bank-accounts', [BankAccountApiController::class, 'index'])->name('api.bank-accounts.index');
    Route::get('/bank-accounts/{bankAccount}', [BankAccountApiController::class, 'show'])->name('api.bank-accounts.show');

    // Webhooks
    Route::apiResource('webhooks', WebhookApiController::class)->names([
        'index' => 'api.webhooks.index',
        'show' => 'api.webhooks.show',
        'store' => 'api.webhooks.store',
        'update' => 'api.webhooks.update',
        'destroy' => 'api.webhooks.destroy',
    ]);
    Route::post('/webhooks/{webhook}/regenerate-secret', [WebhookApiController::class, 'regenerateSecret'])
        ->name('api.webhooks.regenerate-secret');
});
