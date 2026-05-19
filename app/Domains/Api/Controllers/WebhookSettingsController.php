<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Models\Webhook;
use App\Domains\Api\Requests\StoreWebhookRequest;
use App\Domains\Api\Requests\UpdateWebhookRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web UI for managing outbound webhook subscriptions.
 */
class WebhookSettingsController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $webhooks = Webhook::query()
            ->withCount('calls')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Settings/Webhooks', [
            'webhooks' => $webhooks,
            'availableEvents' => WebhookEvent::all(),
        ]);
    }

    public function store(StoreWebhookRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validated();

        $webhook = Webhook::create([
            'organization_id' => $currentOrg->id(),
            'url' => $validated['url'],
            'secret' => Webhook::generateSecret(),
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('settings.webhooks')
            ->with('success', __('app.webhook_created'))
            ->with('webhookSecret', $webhook->secret);
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $validated = $request->validated();

        $webhook->update($validated);

        return redirect()->route('settings.webhooks')
            ->with('success', __('app.webhook_updated'));
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return redirect()->route('settings.webhooks')
            ->with('success', __('app.webhook_deleted'));
    }

    public function regenerateSecret(Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $webhook->update(['secret' => Webhook::generateSecret()]);

        return redirect()->route('settings.webhooks')
            ->with('success', __('app.webhook_secret_regenerated'))
            ->with('webhookSecret', $webhook->secret);
    }
}
