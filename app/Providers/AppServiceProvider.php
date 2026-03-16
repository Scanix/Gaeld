<?php

namespace App\Providers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Policies\AccountPolicy;
use App\Domains\Accounting\Policies\JournalEntryPolicy;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Policies\BankAccountPolicy;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Policies\ExpensePolicy;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Policies\InvoicePolicy;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Policies\OrganizationPolicy;
use App\Domains\Users\Models\User;
use App\Domains\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
