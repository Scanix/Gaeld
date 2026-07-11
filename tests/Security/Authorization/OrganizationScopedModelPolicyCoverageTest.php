<?php

namespace Tests\Security\Authorization;

use App\Domains\Accounting\Models\ConsolidationElimination;
use App\Domains\Accounting\Models\JournalEvent;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Models\PersonalTransactionPattern;
use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Models\RecurringExpense;
use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Organizations\Models\OrganizationInvitation;
use App\Domains\Payroll\Models\DeductionRate;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guards against the exact bug class fixed on 2026-07-09 (see
 * .github/skills/architectural-cleanup/SKILL.md issue 2): a model gets the
 * `BelongsToOrganization` trait (and therefore tenant-scoped data), but no
 * authorization policy is ever wired up for it, so controllers either forget
 * to authorize entirely or re-implement ad-hoc `abort_unless(...)` checks
 * that silently drift out of sync with the real permission rules.
 *
 * Every `BelongsToOrganization` model must resolve a policy via
 * `Gate::getPolicyFor()` (Laravel auto-discovers `Models\Foo` →
 * `Policies\FooPolicy`, so most models need zero extra registration) UNLESS
 * it is explicitly allow-listed below with a documented reason.
 *
 * When this test fails for a *new* model, the fix is almost always to add a
 * Policy class next to the model (see any existing Policy in the same
 * domain for the pattern) — not to add it to the allow-list.
 */
class OrganizationScopedModelPolicyCoverageTest extends TestCase
{
    /**
     * Pre-existing gaps as of 2026-07-11, kept here so the test is
     * actionable (fails on *new* regressions) without blocking on a large
     * retroactive policy-writing effort. Each entry documents why the model
     * currently has no dedicated policy. Closing these is tracked separately
     * — do not add to this list to silence a newly introduced gap.
     *
     * @var array<class-string, string>
     */
    private const KNOWN_GAPS = [
        ConsolidationElimination::class => 'Managed exclusively through ConsolidationController, authorized via the parent ConsolidationGroup.',
        JournalEvent::class => 'Internal audit-trail record, never exposed via a direct CRUD route.',
        BankImport::class => 'Managed exclusively through ReconciliationController; no standalone route.',
        PersonalTransactionPattern::class => 'Managed exclusively through ReconciliationController; no standalone route.',
        ExpenseCategory::class => 'ExpenseCategoryController authorizes against the parent Organization ("update" ability), not the category itself.',
        ReceiptScan::class => 'Managed exclusively through Expense controllers, authorized via the parent Expense.',
        RecurringExpense::class => 'RecurringExpenseController has no dedicated policy yet — tracked as follow-up.',
        InvoiceCatalogItem::class => 'InvoiceCatalogItemController authorizes against the parent Organization ("update" ability), not the item itself.',
        InvoicePayment::class => 'Managed exclusively through InvoiceLifecycleController, authorized via the parent Invoice.',
        OrganizationInvitation::class => 'InvitationController authorizes against the parent Organization ("manageUsers" ability), not the invitation itself.',
        DeductionRate::class => 'No dedicated policy yet — tracked as follow-up.',
    ];

    public function test_every_organization_scoped_model_has_a_resolvable_policy(): void
    {
        $missing = [];

        foreach ($this->organizationScopedModelClasses() as $modelClass) {
            if (array_key_exists($modelClass, self::KNOWN_GAPS)) {
                continue;
            }

            if (Gate::getPolicyFor($modelClass) === null) {
                $missing[] = $modelClass;
            }
        }

        $this->assertEmpty($missing, sprintf(
            "The following BelongsToOrganization models have no resolvable authorization policy:\n- %s\n\n".
            'Add a Policy class next to the model (Laravel auto-discovers Models\Foo → Policies\FooPolicy), '.
            'or, if the model is legitimately authorized via a parent resource, add it to '.
            'OrganizationScopedModelPolicyCoverageTest::KNOWN_GAPS with a one-line justification.',
            implode("\n- ", $missing),
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function organizationScopedModelClasses(): array
    {
        $classes = [];

        $finder = (new Finder)->files()->in(app_path('Domains'))->path('/Models\//')->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace(
                [app_path().DIRECTORY_SEPARATOR, '.php', '/'],
                ['', '', '\\'],
                $file->getPathname(),
            );
            $class = 'App\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            if (! in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
