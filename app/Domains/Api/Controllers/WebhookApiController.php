<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Requests\StoreWebhookRequest;
use App\Domains\Api\Requests\UpdateWebhookRequest;
use App\Domains\Api\Resources\WebhookResource;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API: webhook endpoint CRUD.
 */
class WebhookApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::query()
            ->orderBy('created_at', 'desc')
            ->paginate(config('accounting.pagination.webhooks'));

        return WebhookResource::collection($webhooks);
    }

    public function show(Webhook $webhook): WebhookResource
    {
        $this->authorize('view', $webhook);

        return new WebhookResource($webhook);
    }

    public function store(StoreWebhookRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Webhook::class);

        $validated = $request->validated();

        $webhook = Webhook::create([
            'organization_id' => $currentOrg->id(),
            'url' => $validated['url'],
            'secret' => Webhook::generateSecret(),
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return (new WebhookResource($webhook))
            ->additional(['secret' => $webhook->secret])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): WebhookResource
    {
        $this->authorize('update', $webhook);

        $validated = $request->validated();

        $webhook->update($validated);

        return new WebhookResource($webhook->fresh());
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return response()->json(null, 204);
    }

    public function regenerateSecret(Webhook $webhook): JsonResponse
    {
        $this->authorize('regenerateSecret', $webhook);

        $webhook->update(['secret' => Webhook::generateSecret()]);

        return response()->json([
            'secret' => $webhook->secret,
            'message' => 'Webhook secret regenerated.',
        ]);
    }
}
