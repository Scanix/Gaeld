<?php

namespace Tests\Feature\Api;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Auth;
use Tests\Security\SecurityTestCase;

class ExternalReferenceApiTest extends SecurityTestCase
{
    private const REFERENCE = ['external_source' => 'shopify', 'external_id' => 'customer-0'];

    protected function setUp(): void
    {
        parent::setUp();
        app(CurrentOrganization::class)->set($this->orgA);
        $this->withToken($this->createApiToken($this->ownerA, $this->orgA));
    }

    public function test_contact_aliases_share_one_reference_and_other_tenants_can_reuse_it(): void
    {
        $payload = self::REFERENCE + ['name' => 'Original'];
        $id = $this->postJson('/api/v1/customers/upsert', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/contacts/upsert', [...$payload, 'name' => 'Revised'])->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.name', 'Revised');
        $this->postJson('/api/v1/customers', [...$payload, 'name' => 'Conflicting'])->assertConflict()
            ->assertJsonPath('code', 'external_reference_conflict');
        $this->getJson('/api/v1/contacts?'.http_build_query(self::REFERENCE))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.external_source', 'shopify');
        $this->getJson('/api/v1/customers?external_id=other')->assertOk()->assertJsonCount(0, 'data');

        Auth::forgetGuards();
        $this->withToken($this->createApiToken($this->ownerB, $this->orgB));
        $foreign = $this->postJson('/api/v1/contacts/upsert', $payload)->assertCreated()->json('data.id');
        $this->assertNotSame($id, $foreign);
        $this->getJson('/api/v1/customers?external_source=shopify')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $foreign);
    }

    public function test_deletion_reserves_the_reference_and_plain_updates_preserve_it(): void
    {
        $payload = self::REFERENCE + ['name' => 'Integration customer'];
        $id = $this->postJson('/api/v1/contacts', $payload)->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/contacts/{$id}", ['city' => 'Zürich'])->assertOk()->assertJsonPath('data.external_id', 'customer-0');
        $this->deleteJson("/api/v1/contacts/{$id}")->assertNoContent();
        $this->postJson('/api/v1/customers/upsert', $payload)->assertConflict()->assertJsonPath('code', 'external_reference_conflict');
        $this->postJson('/api/v1/customers', $payload)->assertConflict()->assertJsonPath('code', 'external_reference_conflict');
        $this->getJson('/api/v1/contacts?external_source=shopify')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_reference_is_optional_on_normal_creates_and_must_be_a_complete_pair(): void
    {
        $this->postJson('/api/v1/contacts', ['name' => 'Legacy'])->assertCreated()->assertJsonPath('data.external_id', null);
        $this->postJson('/api/v1/contacts', ['name' => 'Incomplete', 'external_id' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('external_source');
        $this->postJson('/api/v1/customers/upsert', ['name' => 'Incomplete'])
            ->assertUnprocessable()->assertJsonValidationErrors(['external_source', 'external_id']);
        $this->getJson('/api/v1/invoices?external_id[]=bad')->assertUnprocessable();
    }

    public function test_explicit_idempotency_keys_check_current_write_access_before_returning_a_response(): void
    {
        $payload = self::REFERENCE + ['name' => 'Keyed'];
        $this->withHeader('Idempotency-Key', 'key-1');
        $id = $this->postJson('/api/v1/contacts/upsert', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/contacts/upsert', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/contacts/upsert', [...$payload, 'name' => 'Changed'])->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $limited = $this->ownerA->createToken('view', ['contacts.view']);
        $limited->accessToken->update(['organization_id' => $this->orgA->id, 'type' => TokenType::Personal]);
        Auth::forgetGuards();
        $this->withToken($limited->plainTextToken)->postJson('/api/v1/contacts/upsert', $payload)->assertForbidden();
    }

    public function test_create_only_credentials_can_replay_their_own_creation_but_cannot_edit_or_replay_another_credential(): void
    {
        $payload = self::REFERENCE + ['name' => 'Create only'];
        $first = $this->ownerA->createToken('create-only', ['contacts.create']);
        $first->accessToken->update(['organization_id' => $this->orgA->id, 'type' => TokenType::Personal]);
        $this->withToken($first->plainTextToken)->withHeader('Idempotency-Key', 'create-only');
        $original = $this->postJson('/api/v1/contacts/upsert', $payload)->assertCreated()->json();
        $this->postJson('/api/v1/contacts/upsert', array_reverse($payload, true))->assertCreated()->assertExactJson($original);

        $this->withHeader('Idempotency-Key', 'new-edit')->postJson('/api/v1/contacts/upsert', [...$payload, 'name' => 'Edit'])->assertForbidden();
        $second = $this->ownerA->createToken('different-create-only', ['contacts.create']);
        $second->accessToken->update(['organization_id' => $this->orgA->id, 'type' => TokenType::Personal]);
        Auth::forgetGuards();
        $this->withToken($second->plainTextToken)->withHeader('Idempotency-Key', 'create-only')
            ->postJson('/api/v1/contacts/upsert', $payload)->assertConflict()->assertJsonMissingPath('data');
    }

    public function test_a_deleted_archived_invoice_returns_a_reserved_reference_only_to_writers(): void
    {
        $customer = Contact::factory()->for($this->orgA)->create();
        Invoice::factory()->for($this->orgA)->create(self::REFERENCE + ['status' => InvoiceStatus::Sent, 'archived_at' => now(), 'deleted_at' => now()]);
        $payload = self::REFERENCE + ['customer_id' => $customer->uuid, 'issue_date' => '2026-11-01',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 25]]];
        $this->postJson('/api/v1/invoices/upsert', $payload)->assertConflict()->assertJsonPath('code', 'external_reference_conflict');
        $reader = $this->ownerA->createToken('read-only', ['invoicing.view']);
        $reader->accessToken->update(['organization_id' => $this->orgA->id, 'type' => TokenType::Personal]);
        Auth::forgetGuards();
        $this->withToken($reader->plainTextToken)->postJson('/api/v1/invoices/upsert', $payload)->assertForbidden();
        $this->postJson('/api/v1/invoices/upsert', [...$payload, 'external_id' => 'absent'])->assertForbidden();
    }

    public function test_invoice_creates_filters_and_updates_only_drafts_and_keeps_deleted_references(): void
    {
        $customer = Contact::factory()->for($this->orgA)->create();
        $payload = self::REFERENCE + [
            'customer_id' => $customer->uuid, 'issue_date' => '2026-11-01',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 25]],
        ];
        $created = $this->postJson('/api/v1/invoices/upsert', $payload + ['customer_reference' => 'Client 55', 'purchase_order_reference' => 'PO-61'])->assertCreated();
        $id = $created->json('data.id');
        $this->postJson('/api/v1/invoices/upsert', [...$payload, 'notes' => 'Synced', 'delivery_reference' => 'Delivery 3'])->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.notes', 'Synced')
            ->assertJsonPath('data.lines.0.id', $created->json('data.lines.0.id'))
            ->assertJsonPath('data.customer_reference', 'Client 55')->assertJsonPath('data.purchase_order_reference', 'PO-61')
            ->assertJsonPath('data.delivery_reference', 'Delivery 3');
        $this->getJson('/api/v1/invoices?'.http_build_query(self::REFERENCE))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.external_id', 'customer-0');
        $this->getJson('/api/v1/invoices?external_id=other')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/invoices', $payload)->assertConflict()->assertJsonPath('code', 'external_reference_conflict');
        $this->patchJson("/api/v1/invoices/{$id}", ['notes' => 'Manual'])->assertOk()->assertJsonPath('data.external_source', 'shopify');
        Invoice::findOrFail($id)->update(['status' => InvoiceStatus::Sent]);
        $this->postJson('/api/v1/invoices/upsert', $payload)->assertConflict()->assertJsonPath('code', 'invalid_invoice_state');
        $this->assertSame('Manual', Invoice::findOrFail($id)->notes);
        Invoice::findOrFail($id)->update(['status' => InvoiceStatus::Draft]);
        $this->deleteJson("/api/v1/invoices/{$id}")->assertNoContent();
        $this->postJson('/api/v1/invoices/upsert', $payload)->assertConflict()->assertJsonPath('code', 'external_reference_conflict');
    }
}
