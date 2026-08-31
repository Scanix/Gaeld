<?php

namespace Tests\Security\Api;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

class JournalEntryApiSecurityTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        $this->createAccountSet($this->orgA);
        $this->createAccountSet($this->orgB);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_a_token_without_accounting_create_cannot_create_an_entry(): void
    {
        $token = $this->ownerA->createToken('read-only', ['accounting.view']);
        $token->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/journal-entries', $this->payload('SEC-NO-WRITE'));

        $response->assertForbidden();
    }

    public function test_a_token_cannot_read_an_entry_from_another_organization(): void
    {
        app(CurrentOrganization::class)->set($this->orgB);
        $entry = app(LedgerService::class)->postEntry($this->orgB->id, new JournalEntryData(
            date: '2026-08-21',
            reference: 'SEC-ORG-B',
            description: 'Security test',
            lines: [
                new JournalLineData(accountId: (string) Account::withoutGlobalScopes()->where('organization_id', $this->orgB->id)->where('code', '1020')->value('id'), debit: '10.00', credit: '0.00'),
                new JournalLineData(accountId: (string) Account::withoutGlobalScopes()->where('organization_id', $this->orgB->id)->where('code', '3000')->value('id'), debit: '0.00', credit: '10.00'),
            ],
        ));
        app(CurrentOrganization::class)->set($this->orgA);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/v1/journal-entries/{$entry->id}");

        $this->assertDenied($response);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/journal-entries')->assertUnauthorized();
    }

    public function test_an_organization_token_cannot_read_an_entry_from_another_organization(): void
    {
        app(CurrentOrganization::class)->set($this->orgB);
        $entry = app(LedgerService::class)->postEntry($this->orgB->id, new JournalEntryData(
            date: '2026-08-21',
            reference: 'SEC-ORG-TOKEN-B',
            description: 'Organization token security test',
            lines: [
                new JournalLineData(accountId: (string) Account::where('code', '1020')->value('id'), debit: '10.00', credit: '0.00'),
                new JournalLineData(accountId: (string) Account::where('code', '3000')->value('id'), debit: '0.00', credit: '10.00'),
            ],
        ));
        app(CurrentOrganization::class)->set($this->orgA);

        $token = $this->ownerA->createToken('organization-token', ['*']);
        $token->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Organization,
        ]);

        $response = $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/journal-entries/{$entry->id}");

        $this->assertDenied($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $reference): array
    {
        return [
            'date' => '2026-08-21',
            'reference' => $reference,
            'description' => 'Security test',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1020', 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ];
    }

    private function createAccountSet(Organization $organization): void
    {
        Account::create([
            'organization_id' => $organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
    }
}
