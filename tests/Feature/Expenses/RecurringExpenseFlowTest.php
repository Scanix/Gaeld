<?php

namespace Tests\Feature\Expenses;

use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Jobs\GenerateRecurringExpensesJob;
use App\Domains\Expenses\Models\RecurringExpense;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class RecurringExpenseFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-15 08:00:00');
        $this->setUpOrganization();
    }

    public function test_can_update_recurring_expense(): void
    {
        $recurring = RecurringExpense::create([
            'organization_id' => $this->org->id,
            'category' => 'Software',
            'description' => 'Monthly subscription',
            'amount' => '100.00',
            'vat_amount' => '0.00',
            'frequency' => RecurrenceFrequency::Monthly,
            'next_due_date' => '2026-04-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->put(route('expenses.recurring.update', $recurring), [
                'category' => 'Software',
                'description' => 'Quarterly subscription',
                'amount' => '300.00',
                'vat_amount' => '0.00',
                'frequency' => 'quarterly',
                'next_due_date' => '2026-04-01',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('expenses.recurring.index'));
        $this->assertDatabaseHas('recurring_expenses', [
            'id' => $recurring->id,
            'amount' => '300.00',
            'frequency' => 'quarterly',
        ]);
    }

    public function test_retry_after_success_does_not_create_a_duplicate_expense(): void
    {
        RecurringExpense::create([
            'organization_id' => $this->org->id,
            'category' => 'Software',
            'description' => 'Monthly subscription',
            'amount' => '100.00',
            'vat_amount' => '0.00',
            'frequency' => RecurrenceFrequency::Monthly,
            'next_due_date' => '2026-03-15',
            'is_active' => true,
        ]);

        $job = app(GenerateRecurringExpensesJob::class);
        $job->handle(app(CreateExpenseAction::class));
        $job->handle(app(CreateExpenseAction::class));

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_cannot_update_recurring_expense_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignRecurring = RecurringExpense::withoutGlobalScopes()->create([
            'organization_id' => $otherOrganization->id,
            'category' => 'Software',
            'amount' => '100.00',
            'vat_amount' => '0.00',
            'frequency' => RecurrenceFrequency::Monthly,
            'next_due_date' => '2026-04-01',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->org->id])
            ->put(route('expenses.recurring.update', $foreignRecurring), [
                'category' => 'Hacked',
                'amount' => '1.00',
                'frequency' => 'monthly',
                'next_due_date' => '2026-04-01',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('recurring_expenses', [
            'id' => $foreignRecurring->id,
            'organization_id' => $otherOrganization->id,
            'category' => 'Software',
        ]);
    }
}
