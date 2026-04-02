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
 * @group Webhooks
 *
 * CRUD operations on webhook endpoints. Webhooks notify external URLs when events occur.
 */
class WebhookApiController extends Controller
{
    /**
     * List webhooks
     *
     * Returns a paginated list of webhook endpoints for the current organisation.
     *
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","url":"https://example.com/webhook","events":["invoice.created","invoice.paid"],"is_active":true,"last_triggered_at":"2025-03-01T12:00:00.000000Z","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"per_page":15,"total":1}}
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::query()
            ->orderBy('created_at', 'desc')
            ->paginate(config('accounting.pagination.webhooks'));

        return WebhookResource::collection($webhooks);
    }

    /**
     * Show a webhook
     *
     * Returns a single webhook endpoint by UUID.
     *
     * @urlParam webhook string required The webhook UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","url":"https://example.com/webhook","events":["invoice.created","invoice.paid"],"is_active":true,"last_triggered_at":"2025-03-01T12:00:00.000000Z","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Webhook not found."}
     */
    public function show(Webhook $webhook): WebhookResource
    {
        $this->authorize('view', $webhook);

        return new WebhookResource($webhook);
    }

    /**
     * Create a webhook
     *
     * Creates a new webhook endpoint. The webhook secret is returned only in the response
     * of this endpoint — store it securely.
     *
     * @bodyParam url string required The endpoint URL (must be HTTPS). Example: https://example.com/webhook
     * @bodyParam events string[] required Events to subscribe to. Example: ["invoice.created","invoice.paid"]
     * @bodyParam is_active boolean Whether the webhook is active. Default: true. Example: true
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","url":"https://example.com/webhook","events":["invoice.created","invoice.paid"],"is_active":true,"last_triggered_at":null,"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"},"secret":"whsec_abc123..."}
     * @response 422 scenario="Validation error" {"message":"The url field is required.","errors":{"url":["The url field is required."]}}
     */
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

    /**
     * Update a webhook
     *
     * Updates an existing webhook endpoint. Only provided fields are changed.
     *
     * @urlParam webhook string required The webhook UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @bodyParam url string The endpoint URL (must be HTTPS). Example: https://example.com/webhook-v2
     * @bodyParam events string[] Events to subscribe to. Example: ["invoice.created","customer.created"]
     * @bodyParam is_active boolean Whether the webhook is active. Example: false
     *
     * @response 200 scenario="Updated" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","url":"https://example.com/webhook-v2","events":["invoice.created","customer.created"],"is_active":false,"last_triggered_at":"2025-03-01T12:00:00.000000Z","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-03-20T14:30:00.000000Z"}}
     */
    public function update(UpdateWebhookRequest $request, Webhook $webhook): WebhookResource
    {
        $this->authorize('update', $webhook);

        $validated = $request->validated();

        $webhook->update($validated);

        return new WebhookResource($webhook->fresh());
    }

    /**
     * Delete a webhook
     *
     * Permanently deletes a webhook endpoint.
     *
     * @urlParam webhook string required The webhook UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 204 scenario="Deleted"
     * @response 404 scenario="Not found" {"message":"Webhook not found."}
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * Regenerate webhook secret
     *
     * Generates a new signing secret for the webhook. The old secret is immediately invalidated.
     *
     * @urlParam webhook string required The webhook UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"secret":"whsec_new789...","message":"Webhook secret regenerated."}
     * @response 404 scenario="Not found" {"message":"Webhook not found."}
     */
    public function regenerateSecret(Webhook $webhook): JsonResponse
    {
        $this->authorize('regenerateSecret', $webhook);

        $webhook->update(['secret' => Webhook::generateSecret()]);

        return response()->json([
            'secret' => $webhook->secret,
            'message' => __('app.webhook_secret_regenerated'),
        ]);
    }
}
