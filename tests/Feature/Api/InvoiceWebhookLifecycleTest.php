<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Models\WebhookCall;
use App\Domains\Api\Services\WebhookService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Security\SecurityTestCase;

class InvoiceWebhookLifecycleTest extends SecurityTestCase
{
    private const EVENTS = ['invoice.created', 'invoice.finalized', 'invoice.payment_recorded', 'invoice.cancelled'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.api_access' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Queue::fake([DispatchWebhookJob::class]);
    }

    public static function entryPoints(): array
    {
        return [['web'], ['api']];
    }

    #[DataProvider('entryPoints')]
    public function test_committed_transitions_reach_only_subscribed_endpoints(string $entryPoint): void
    {
        $this->subscribe($this->orgA->id);
        $this->subscribe($this->orgB->id);
        $this->subscribe($this->orgA->id, false);
        $this->subscribe($this->orgA->id, true, ['customer.created']);
        foreach (['1020' => AccountType::Asset, '1100' => AccountType::Asset, '3000' => AccountType::Revenue] as $code => $type) {
            Account::create(['organization_id' => $this->orgA->id, 'code' => (string) $code, 'name' => (string) $code, 'type' => $type]);
        }
        $customer = Contact::create(['organization_id' => $this->orgA->id, 'name' => 'Example customer']);
        $api = $entryPoint === 'api';
        $base = $api ? '/api/v1/invoices' : '/invoices';
        if ($api) {
            $this->withToken($this->createApiToken($this->ownerA, $this->orgA));
        } else {
            $this->ownerA->forceFill(['onboarding_completed_at' => now()])->save();
            $this->actingAs($this->ownerA)->withSession(['current_organization_id' => $this->orgA->id]);
        }
        $post = function (string $path, array $data = []) use ($api): void {
            if ($api) {
                $this->postJson($path, $data)->assertSuccessful();
            } else {
                $this->post($path, $data)->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
            }
        };
        $post($base, [
            'customer_id' => $api ? $customer->uuid : $customer->id,
            'issue_date' => '2026-03-10', 'due_date' => '2026-03-31', 'currency' => 'CHF',
            'lines' => [['description' => 'Example service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $invoice = Invoice::sole();
        $post("{$base}/{$invoice->id}/finalize");

        $paymentRoute = $api ? 'record-payment' : 'payment';
        $post("{$base}/{$invoice->id}/{$paymentRoute}", [
            'amount' => '10.00', 'payment_method' => 'bank', 'payment_date' => '2026-03-16', 'bank_account_code' => '1020',
        ]);
        $post("{$base}/{$invoice->id}/cancel");
        if ($api) {
            $this->postJson("{$base}/{$invoice->id}/cancel")->assertSuccessful();
        } else {
            $this->post("{$base}/{$invoice->id}/cancel")->assertForbidden();
        }

        $calls = WebhookCall::all()->keyBy('event');
        $this->assertEqualsCanonicalizing(self::EVENTS, $calls->keys()->all());
        $this->assertDatabaseCount('webhook_calls', 4);
        Queue::assertPushed(DispatchWebhookJob::class, 4);
        foreach ($calls as $event => $call) {
            $this->assertSame($event, $call->payload['event']);
            $this->assertSame($invoice->id, $call->payload['invoice_id']);
            $this->assertSame($this->orgA->id, $call->payload['organization_id']);
            $this->assertTrue(Str::isUuid($call->payload['event_id']));
        }
        $this->assertSame(InvoicePayment::sole()->id, $calls['invoice.payment_recorded']->payload['payment_id']);
    }

    public function test_outer_rollback_discards_calls_and_pending_jobs(): void
    {
        $this->subscribe($this->orgA->id);
        $invoice = Invoice::factory()->create(['organization_id' => $this->orgA->id]);
        DB::beginTransaction();
        try {
            app(WebhookService::class)->dispatchInvoice($invoice, WebhookEvent::InvoiceCreated);
            $this->assertDatabaseCount('webhook_calls', 1);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseCount('webhook_calls', 0);
        Queue::assertNothingPushed();
    }

    public function test_subscribers_share_an_event_id_and_retry_uses_the_original_signed_body(): void
    {
        $first = $this->subscribe($this->orgA->id);
        $second = $this->subscribe($this->orgA->id);
        $invoice = Invoice::factory()->create(['organization_id' => $this->orgA->id]);
        app(WebhookService::class)->dispatchInvoice($invoice, WebhookEvent::InvoiceCreated);
        $call = $first->calls()->sole();
        $this->assertSame($second->calls()->sole()->payload['event_id'], $call->payload['event_id']);
        $this->assertNotSame($second->calls()->sole()->id, $call->id);

        Http::fake(['*' => Http::sequence()->pushStatus(503)->pushStatus(200)]);
        $firstAttempt = (new DispatchWebhookJob($call))->withFakeQueueInteractions();
        $firstAttempt->handle();
        $firstAttempt->assertReleased(30);
        $this->assertNotNull($call->fresh()->next_retry_at);
        (new DispatchWebhookJob($call->fresh()))->withFakeQueueInteractions()->handle();
        (new DispatchWebhookJob($call->fresh()))->withFakeQueueInteractions()->handle();

        $this->assertSame('success', $call->fresh()->status);
        $this->assertNull($call->fresh()->next_retry_at);
        Http::assertSentCount(2);
        $sent = Http::recorded();
        $this->assertSame($sent[0][0]->body(), $sent[1][0]->body());
        foreach ($sent as [$request]) {
            $this->assertTrue($request->hasHeader('X-Webhook-Id', $call->id));
            $this->assertTrue($request->hasHeader('X-Webhook-Event-Id', $call->payload['event_id']));
            $this->assertTrue($request->hasHeader('X-Webhook-Signature', hash_hmac('sha256', $request->body(), $first->secret)));
        }
    }

    /** @param list<string> $events */
    private function subscribe(string $orgId, bool $active = true, array $events = self::EVENTS): Webhook
    {
        return Webhook::create([
            'organization_id' => $orgId, 'url' => 'https://93.184.216.34/hook',
            'secret' => 'synthetic-test-signing-key', 'events' => $events, 'is_active' => $active,
        ]);
    }
}
