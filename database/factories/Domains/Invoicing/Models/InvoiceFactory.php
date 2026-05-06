<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Contact::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'type' => InvoiceType::Invoice,
            'issue_date' => fake()->date(),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'subtotal' => fake()->randomFloat(2, 100, 10000),
            'vat_amount' => '0.00',
            'total' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'CHF',
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Overdue,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid]);
    }
}
