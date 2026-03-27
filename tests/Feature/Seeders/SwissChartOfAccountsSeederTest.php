<?php

namespace Tests\Feature\Seeders;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Database\Seeders\SwissChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwissChartOfAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_accounts_for_a_specific_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Seeder Test Org',
            'legal_name' => 'Seeder Test Org AG',
            'currency' => 'CHF',
            'locale' => 'en',
            'country' => 'CH',
        ]);

        (new SwissChartOfAccountsSeeder)->run($organization);

        $this->assertGreaterThan(40, Account::where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('accounts', [
            'organization_id' => $organization->id,
            'code' => '1020',
            'name' => 'Bank Account CHF',
        ]);
        $this->assertDatabaseHas('accounts', [
            'organization_id' => $organization->id,
            'code' => '3000',
            'name' => 'Revenue from Services',
        ]);
    }

    public function test_it_does_nothing_when_no_organization_exists(): void
    {
        (new SwissChartOfAccountsSeeder)->run();

        $this->assertSame(0, Account::count());
    }
}
