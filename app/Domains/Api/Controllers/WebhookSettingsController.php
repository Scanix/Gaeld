<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Enums\WebhookEvent;
use App\Domains\Api\Models\Webhook;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Support\Rules\ValidWebhookUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebhookSettingsController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $webhooks = Webhook::query()
            ->where('organization_id', $currentOrg->id())
            ->withCount('calls')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Settings/Webhooks', [
            'webhooks' => $webhooks,
            'availableEvents' => WebhookEvent::all(),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEvent::all())],
            'is_active' => 'boolean',
        ]);

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

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048', new ValidWebhookUrl],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEvent::all())],
            'is_active' => 'sometimes|boolean',
        ]);

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
