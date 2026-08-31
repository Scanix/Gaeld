<?php

namespace Tests\Unit\Api;

use App\Domains\Api\Exceptions\ApiIdempotencyConflictException;
use App\Domains\Api\Services\ApiIdempotencyService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiIdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replays_a_completed_reservation(): void
    {
        $organization = Organization::factory()->create();
        $request = Request::create('/api/v1/journal-entries', 'POST', ['reference' => 'UNIT-1']);
        $service = app(ApiIdempotencyService::class);

        $reservation = $service->reserve($request, $organization->id, 'UNIT-1');
        $this->assertNotNull($reservation);
        $service->complete($reservation, response()->json(['data' => ['id' => 'entry-1']], 201));

        $replay = $service->reserve($request, $organization->id, 'UNIT-1');

        $this->assertTrue($replay->replay);
        $this->assertSame(201, $service->replay($replay)->getStatusCode());
        $this->assertSame('entry-1', $service->replay($replay)->getData(true)['data']['id']);
    }

    public function test_it_rejects_a_changed_payload_for_an_existing_key(): void
    {
        $organization = Organization::factory()->create();
        $request = Request::create('/api/v1/journal-entries', 'POST', ['reference' => 'UNIT-2']);
        $request->headers->set('Idempotency-Key', 'unit-key');
        $service = app(ApiIdempotencyService::class);
        $reservation = $service->reserve($request, $organization->id, null);
        $service->complete($reservation, response()->json(['ok' => true], 201));

        $changedRequest = Request::create('/api/v1/journal-entries', 'POST', ['reference' => 'UNIT-2-changed']);
        $changedRequest->headers->set('Idempotency-Key', 'unit-key');

        $this->expectException(ApiIdempotencyConflictException::class);
        $service->reserve($changedRequest, $organization->id, null);
    }
}
