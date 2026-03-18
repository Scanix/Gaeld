<?php

namespace Tests\Unit;

use App\Support\QueryBuilder;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);

        $client = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Alpha Client',
        ]);

        $client2 = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Beta Client',
        ]);

        Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $client->id,
            'number' => 'INV-001',
            'status' => 'draft',
            'issue_date' => '2026-01-10',
            'due_date' => '2026-02-10',
            'currency' => 'CHF',
            'subtotal' => '500.00',
            'vat_amount' => '0.00',
            'total' => '500.00',
        ]);

        Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $client2->id,
            'number' => 'INV-002',
            'status' => 'sent',
            'issue_date' => '2026-02-15',
            'due_date' => '2026-03-15',
            'currency' => 'CHF',
            'subtotal' => '1000.00',
            'vat_amount' => '0.00',
            'total' => '1000.00',
        ]);

        Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $client->id,
            'number' => 'INV-003',
            'status' => 'paid',
            'issue_date' => '2026-03-01',
            'due_date' => '2026-04-01',
            'currency' => 'CHF',
            'subtotal' => '750.00',
            'vat_amount' => '0.00',
            'total' => '750.00',
        ]);
    }

    public function test_sorts_by_allowed_column(): void
    {
        $request = Request::create('/', 'GET', ['sort' => 'total', 'direction' => 'asc']);

        $results = QueryBuilder::for(Invoice::query(), $request)
            ->allowedSorts(['issue_date', 'total'], 'issue_date', 'desc')
            ->apply()
            ->get();

        $this->assertEquals('500.00', $results->first()->total);
        $this->assertEquals('1000.00', $results->last()->total);
    }

    public function test_ignores_disallowed_sort_column(): void
    {
        $request = Request::create('/', 'GET', ['sort' => 'currency', 'direction' => 'asc']);

        $results = QueryBuilder::for(Invoice::query(), $request)
            ->allowedSorts(['issue_date', 'total'], 'issue_date', 'desc')
            ->apply()
            ->get();

        // Should fall back to default sort (issue_date desc)
        $this->assertEquals('2026-03-01', $results->first()->issue_date->format('Y-m-d'));
    }

    public function test_filters_by_exact_value(): void
    {
        $request = Request::create('/', 'GET', ['filter' => ['status' => 'sent']]);

        $results = QueryBuilder::for(Invoice::query(), $request)
            ->allowedFilters(['status'])
            ->allowedSorts(['issue_date'], 'issue_date', 'desc')
            ->apply()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-002', $results->first()->number);
    }

    public function test_searches_across_columns(): void
    {
        $request = Request::create('/', 'GET', ['search' => 'INV-003']);

        $results = QueryBuilder::for(Invoice::query(), $request)
            ->searchable(['number'])
            ->allowedSorts(['issue_date'], 'issue_date', 'desc')
            ->apply()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-003', $results->first()->number);
    }

    public function test_searches_in_relations(): void
    {
        $request = Request::create('/', 'GET', ['search' => 'Beta']);

        $results = QueryBuilder::for(Invoice::with('client'), $request)
            ->searchable(['number', 'client.name'])
            ->allowedSorts(['issue_date'], 'issue_date', 'desc')
            ->apply()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-002', $results->first()->number);
    }

    public function test_defaults_to_desc_created_at(): void
    {
        $request = Request::create('/', 'GET');

        $results = QueryBuilder::for(Invoice::query(), $request)
            ->allowedSorts(['issue_date'], 'issue_date', 'desc')
            ->apply()
            ->get();

        // Most recent first
        $this->assertEquals('INV-003', $results->first()->number);
    }

    public function test_combined_filter_search_sort(): void
    {
        $request = Request::create('/', 'GET', [
            'filter' => ['status' => 'draft'],
            'search' => 'Alpha',
            'sort' => 'total',
            'direction' => 'desc',
        ]);

        $results = QueryBuilder::for(Invoice::with('client'), $request)
            ->allowedSorts(['issue_date', 'total'], 'issue_date', 'desc')
            ->allowedFilters(['status'])
            ->searchable(['client.name'])
            ->apply()
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-001', $results->first()->number);
    }
}
