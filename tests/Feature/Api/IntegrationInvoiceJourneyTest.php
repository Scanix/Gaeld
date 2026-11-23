<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Models\WebhookCall;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Security\SecurityTestCase;

class IntegrationInvoiceJourneyTest extends SecurityTestCase
{
    public function test_an_integration_upserts_a_draft_renders_it_and_verifies_its_signed_lifecycle(): void
    {
        $this->withToken($this->createApiToken($this->ownerA, $this->orgA));
        $this->orgA->update(['legal_name' => 'Example GmbH', 'address' => 'Bahnhofstrasse 1', 'postal_code' => '8001', 'city' => 'Zürich', 'country' => 'CH']);
        BankAccount::create(['organization_id' => $this->orgA->id, 'name' => 'Integration account', 'iban' => 'CH9300762011623852957', 'currency' => 'CHF', 'is_default_for_invoicing' => true, 'is_active' => true]);
        foreach (['1020' => AccountType::Asset, '1100' => AccountType::Asset, '2200' => AccountType::Liability, '3000' => AccountType::Revenue] as $code => $type) {
            Account::create(['organization_id' => $this->orgA->id, 'code' => (string) $code, 'name' => (string) $code, 'type' => $type]);
        }
        $rate = VatRate::factory()->create(['organization_id' => $this->orgA->id, 'rate' => '8.10']);
        InvoiceCatalogItem::create(['name' => 'Consultation', 'default_unit_price' => '100.00', 'default_vat_rate_id' => $rate->id]);
        $events = ['invoice.created', 'invoice.finalized', 'invoice.payment_recorded', 'invoice.cancelled'];
        $subscriber = Webhook::create(['organization_id' => $this->orgA->id, 'url' => 'https://93.184.216.34/hook', 'secret' => 'synthetic-journey-secret', 'is_active' => true, 'events' => $events]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/hook' => Http::response('', 204)]);
        Queue::fake([DispatchWebhookJob::class]);

        $customer = $this->postJson('/api/v1/contacts/upsert', [
            'external_source' => 'erp', 'external_id' => 'client-17', 'name' => 'Client AG',
            'address' => 'Lagerstrasse 5', 'postal_code' => '8004', 'city' => 'Zürich', 'country' => 'CH',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/customers/'.$customer)->assertOk()->assertJsonPath('data.external_id', 'client-17');
        $vat = $this->getJson('/api/v1/vat-rates')->assertOk()->json('data.0');
        $item = $this->getJson('/api/v1/invoice-catalog-items')->assertOk()->json('data.0');
        $this->assertSame($vat['id'], $item['default_vat_rate_id']);
        $key = ['external_source' => 'erp', 'external_id' => 'draft-507'];
        $payload = [
            ...$key, 'customer_id' => $customer, 'issue_date' => '2026-11-23', 'due_date' => '2026-12-23',
            'number' => 'INV-507', 'customer_reference' => 'UNIT-3', 'purchase_order_reference' => 'PO-17',
            'lines' => [['description' => $item['name'], 'quantity' => 1, 'unit_price' => $item['default_unit_price'], 'vat_rate_id' => $vat['id']]],
        ];
        $draft = $this->postJson('/api/v1/invoices/upsert', $payload)->assertCreated()
            ->assertJsonPath('data.lines.0.vat_rate_id', $vat['id'])->json('data.id');
        $this->postJson('/api/v1/invoices/upsert', [...$payload, 'delivery_reference' => 'DEL-7'])
            ->assertOk()->assertJsonPath('data.id', $draft)->assertJsonPath('data.customer_reference', 'UNIT-3')
            ->assertJsonPath('data.delivery_reference', 'DEL-7');
        $path = '/api/v1/invoices/'.$draft;
        $pdf = $this->get($path.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->postJson($path.'/finalize')->assertSuccessful();
        $this->postJson($path.'/record-payment', ['amount' => '20.00', 'payment_method' => 'bank', 'payment_date' => '2026-11-24', 'bank_account_code' => '1020'])->assertSuccessful();
        $this->postJson($path.'/cancel')->assertSuccessful();

        Queue::assertPushed(DispatchWebhookJob::class, 4);
        $calls = WebhookCall::where('webhook_id', $subscriber->id)->get();
        $this->assertEqualsCanonicalizing($events, $calls->pluck('event')->all());
        foreach ($calls as $call) {
            (new DispatchWebhookJob($call))->withFakeQueueInteractions()->handle();
        }
        Http::assertSentCount(4);
        foreach (Http::recorded() as [$request]) {
            $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($draft, $payload['invoice_id']);
            $this->assertSame($this->orgA->id, $payload['organization_id']);
            $this->assertTrue($request->hasHeader('X-Webhook-Event-Id', $payload['event_id']));
            $this->assertTrue($request->hasHeader('X-Webhook-Signature', hash_hmac('sha256', $request->body(), $subscriber->secret)));
        }
    }
}
