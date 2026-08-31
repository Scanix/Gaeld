<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

class ApiIdempotencyTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->createAccount('1020', 'Bank', AccountType::Asset->value);
        $this->createAccount('3000', 'Revenue', AccountType::Revenue->value);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_replaying_a_request_with_the_same_key_returns_the_original_entry(): void
    {
        $payload = $this->payload('IDEMP-KEY-1');

        $first = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'logbook-1')
            ->postJson('/api/v1/journal-entries', $payload);
        $second = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'logbook-1')
            ->postJson('/api/v1/journal-entries', $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_reusing_a_key_with_a_different_payload_returns_a_conflict(): void
    {
        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'logbook-2')
            ->postJson('/api/v1/journal-entries', $this->payload('IDEMP-KEY-2'))
            ->assertCreated();

        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'logbook-2')
            ->postJson('/api/v1/journal-entries', [
                ...$this->payload('IDEMP-KEY-2'),
                'description' => 'Changed payload',
            ]);

        $response->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_reference_is_used_as_the_fallback_when_no_key_is_supplied(): void
    {
        $payload = $this->payload('IDEMP-FALLBACK');

        $first = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', $payload);
        $second = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_a_mutation_without_a_key_or_reference_is_rejected(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => '2026-08-21',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1020', 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'idempotency_key_required');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_a_duplicate_reference_with_a_new_key_returns_a_reference_conflict(): void
    {
        $payload = $this->payload('DUPLICATE-REFERENCE');

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'duplicate-reference-1')
            ->postJson('/api/v1/journal-entries', $payload)
            ->assertCreated();

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'duplicate-reference-2')
            ->postJson('/api/v1/journal-entries', $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'duplicate_reference');

        $this->assertDatabaseCount('journal_entries', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $reference): array
    {
        return [
            'date' => '2026-08-21',
            'reference' => $reference,
            'description' => 'Idempotency test',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1020', 'debit' => '10.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '10.00'],
            ],
        ];
    }

    private function createAccount(string $code, string $name, string $type): void
    {
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
        ]);
    }
}
