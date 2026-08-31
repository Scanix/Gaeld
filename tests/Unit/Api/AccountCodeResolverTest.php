<?php

namespace Tests\Unit\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Services\AccountCodeResolver;
use App\Domains\Organizations\Models\Organization;
use App\Support\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCodeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_an_active_code_inside_the_requested_organization(): void
    {
        $organization = Organization::factory()->create();
        $account = Account::create([
            'organization_id' => $organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $resolved = app(AccountCodeResolver::class)->resolve($organization->id, '1020');

        $this->assertSame($account->id, $resolved->id);
    }

    public function test_it_rejects_an_unknown_or_inactive_code(): void
    {
        $organization = Organization::factory()->create();
        Account::create([
            'organization_id' => $organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
            'is_active' => false,
        ]);

        $this->expectException(DomainException::class);
        app(AccountCodeResolver::class)->resolve($organization->id, '1020');
    }
}
