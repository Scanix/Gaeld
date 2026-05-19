<?php

namespace App\Providers;

use App\Domains\Accounting\Jobs\ExportChartOfAccountsJob;
use App\Domains\Accounting\Listeners\JournalEventSubscriber;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\TaxDeclaration;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Policies\ConsolidationGroupPolicy;
use App\Domains\Accounting\Policies\CostCenterPolicy;
use App\Domains\Accounting\Policies\ExchangeRatePolicy;
use App\Domains\Accounting\Policies\FiscalYearPolicy;
use App\Domains\Accounting\Policies\TaxDeclarationPolicy;
use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Assets\Jobs\MonthlyDepreciationJob;
use App\Domains\Banking\Contracts\PaymentInitiationProviderInterface;
use App\Domains\Banking\Services\Payments\FilePain001Provider;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Policies\ContactPolicy;
use App\Domains\Contacts\Search\ContactSearchProvider;
use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Expenses\Search\ExpenseSearchProvider;
use App\Domains\Expenses\Services\NullOcrService;
use App\Domains\Expenses\Services\TesseractOcrService;
use App\Domains\Invoicing\Jobs\GenerateRecurringInvoicesJob;
use App\Domains\Invoicing\Jobs\SendPaymentRemindersJob;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Search\InvoiceSearchProvider;
use App\Domains\Migration\Jobs\ProcessMigrationImport;
use App\Domains\Organizations\Events\MemberRemoved;
use App\Domains\Organizations\Jobs\ExportOrganizationDataJob;
use App\Domains\Organizations\Listeners\RevokeOrganizationTokens;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Jobs\GenerateReportsJob;
use App\Domains\Users\Jobs\ExportUserDataJob;
use App\Http\Services\GlobalSearchService;
use App\Listeners\SendHorizonTelegramAlert;
use App\Support\Listeners\AuthAuditSubscriber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Sanctum\Sanctum;

/**
 * Core application service provider — registers bindings, gates, policies, and global search providers.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->singleton(
            ReceiptOcrInterface::class,
            config('services.ocr.driver', 'tesseract') === 'tesseract'
                ? TesseractOcrService::class
                : NullOcrService::class,
        );

        // Outbound payment provider — overridden by EE GaeldEEServiceProvider
        // when the bank_sync feature is enabled and the BankAccount uses bLink.
        $this->app->bind(PaymentInitiationProviderInterface::class, FilePain001Provider::class);

        $this->app->singleton(GlobalSearchService::class, function ($app) {
            return new GlobalSearchService(
                $app->make(InvoiceSearchProvider::class),
                $app->make(ContactSearchProvider::class),
                $app->make(ExpenseSearchProvider::class),
            );
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // ── Queue Routing (Laravel 13) ──────────────────────────
        // Centralizes job→queue mapping so individual jobs don't
        // need $queue properties. Run separate Horizon supervisors
        // per queue for priority / concurrency control.
        Queue::route([
            DispatchWebhookJob::class => [null, 'webhooks'],
            ProcessReceiptOcrJob::class => [null, 'ocr'],
            ProcessMigrationImport::class => [null, 'processing'],
            ExportChartOfAccountsJob::class => [null, 'exports'],
            ExportUserDataJob::class => [null, 'exports'],
            ExportOrganizationDataJob::class => [null, 'exports'],
            GenerateRecurringInvoicesJob::class => [null, 'scheduled'],
            SendPaymentRemindersJob::class => [null, 'scheduled'],
            GenerateReportsJob::class => [null, 'scheduled'],
            MonthlyDepreciationJob::class => [null, 'scheduled'],
        ]);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('sanctum.rate_limit', 60))->by($request->user()?->id ?: $request->ip());
        });
        Password::defaults(fn () => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        Event::subscribe(AuthAuditSubscriber::class);
        Event::subscribe(JournalEventSubscriber::class);
        Event::listen(MemberRemoved::class, RevokeOrganizationTokens::class);
        Event::listen(LongWaitDetected::class, SendHorizonTelegramAlert::class);

        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(FiscalYear::class, FiscalYearPolicy::class);
        Gate::policy(TaxDeclaration::class, TaxDeclarationPolicy::class);
        Gate::policy(CostCenter::class, CostCenterPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(ConsolidationGroup::class, ConsolidationGroupPolicy::class);

        // Cache invalidation: flush tagged caches when models change
        $flushTags = function (string ...$tags) {
            return function (Model $model) use ($tags) {
                $orgId = $model->organization_id ?? null;
                if (! $orgId) {
                    return;
                }
                foreach ($tags as $tag) {
                    try {
                        Cache::tags(["org:{$orgId}:{$tag}"])->flush();
                    } catch (\BadMethodCallException) {
                        // File/array caches used in testing do not support tags.
                    }
                }
            };
        };

        $referenceFlush = $flushTags('reference');
        $contactsFlush = $flushTags('contacts');
        $dashboardFlush = $flushTags('dashboard');

        foreach (['created', 'updated', 'deleted'] as $event) {
            VatRate::$event($referenceFlush);
            Account::$event($referenceFlush);
            ExpenseCategory::$event($referenceFlush);
            Contact::$event($contactsFlush);
            Invoice::$event($dashboardFlush);
            Expense::$event($dashboardFlush);
        }
    }
}
