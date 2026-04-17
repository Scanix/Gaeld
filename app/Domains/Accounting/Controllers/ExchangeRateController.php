<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Requests\StoreExchangeRateRequest;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $rates = ExchangeRate::query()
            ->where('organization_id', $currentOrg->id())
            ->orderByDesc('date')
            ->orderBy('currency_from')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Accounting/ExchangeRates', [
            'rates' => $rates,
        ]);
    }

    public function store(StoreExchangeRateRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();

        ExchangeRate::updateOrCreate(
            [
                'organization_id' => $currentOrg->id(),
                'currency_from' => strtoupper($validated['currency_from']),
                'currency_to' => strtoupper($validated['currency_to']),
                'date' => $validated['date'],
                'source' => 'manual',
            ],
            [
                'rate' => $validated['rate'],
            ],
        );

        return back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request, ExchangeRate $exchangeRate, CurrentOrganization $currentOrg): RedirectResponse
    {
        abort_unless($request->user()?->hasPermissionTo(Permission::AccountingDelete), 403);

        if ($exchangeRate->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        if ($exchangeRate->source !== 'manual') {
            return back()->withErrors(['rate' => __('app.forbidden')]);
        }

        $exchangeRate->delete();

        return back()->with('success', __('app.deleted'));
    }

    public function fetchEcb(CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $response = Http::timeout(10)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');

        if (! $response->successful()) {
            return back()->withErrors(['exchange_rates' => __('app.unexpected_error')]);
        }

        $xml = @simplexml_load_string($response->body());
        if (! $xml) {
            return back()->withErrors(['exchange_rates' => __('app.unexpected_error')]);
        }

        $namespaces = $xml->getNamespaces(true);
        $cube = $xml->children($namespaces['gesmes'] ?? null)->Cube ?? $xml->Cube;

        $dateNode = null;
        if (isset($cube->Cube)) {
            $dateNode = $cube->Cube;
        }

        if (! $dateNode || ! isset($dateNode['time'])) {
            return back()->withErrors(['exchange_rates' => __('app.unexpected_error')]);
        }

        $date = (string) $dateNode['time'];
        $chfRate = null;
        foreach ($dateNode->Cube as $currencyNode) {
            if ((string) $currencyNode['currency'] === 'CHF') {
                $chfRate = (float) $currencyNode['rate'];
                break;
            }
        }

        if (! $chfRate || $chfRate <= 0) {
            return back()->withErrors(['exchange_rates' => __('app.unexpected_error')]);
        }

        $rates = [];
        foreach ($dateNode->Cube as $currencyNode) {
            $currency = (string) $currencyNode['currency'];
            $rate = (float) $currencyNode['rate'];
            if ($rate > 0) {
                $rates[$currency] = $rate;
            }
        }

        // Keep parity with former EE behavior by importing EUR, USD and GBP cross-rates to/from CHF.
        foreach (['EUR', 'USD', 'GBP'] as $currency) {
            $rateToChf = null;

            if ($currency === 'EUR') {
                $rateToChf = $chfRate;
            } elseif (isset($rates[$currency])) {
                $rateToChf = $chfRate / $rates[$currency];
            }

            if (! $rateToChf || $rateToChf <= 0) {
                continue;
            }

            ExchangeRate::updateOrCreate(
                [
                    'organization_id' => $currentOrg->id(),
                    'currency_from' => $currency,
                    'currency_to' => 'CHF',
                    'date' => $date,
                    'source' => 'ecb',
                ],
                [
                    'rate' => round($rateToChf, 8),
                ],
            );

            ExchangeRate::updateOrCreate(
                [
                    'organization_id' => $currentOrg->id(),
                    'currency_from' => 'CHF',
                    'currency_to' => $currency,
                    'date' => $date,
                    'source' => 'ecb',
                ],
                [
                    'rate' => round(1 / $rateToChf, 8),
                ],
            );
        }

        return back()->with('success', __('app.saved'));
    }
}
