<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Resources\WebhookResource;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Controllers\Controller;
use App\Support\Rules\ValidWebhookUrl;

class WebhookApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::query()
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return WebhookResource::collection($webhooks);
    }

    public function show(Webhook $webhook): WebhookResource
    {
        $this->authorize('view', $webhook);

        return new WebhookResource($webhook);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Webhook::class);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => 'required|array|min:1',
            'events.*' => ['required', 'string', $this->webhookEventRule()],
            'is_active' => 'boolean',
        ]);

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

    public function update(Request $request, Webhook $webhook): WebhookResource
    {
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => 'sometimes|array|min:1',
            'events.*' => ['required', 'string', $this->webhookEventRule()],
            'is_active' => 'sometimes|boolean',
        ]);

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

    private function webhookEventRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! WebhookEvent::isValid($value)) {
                $fail("The event '{$value}' is not a valid webhook event.");
            }
        };
    }
}
