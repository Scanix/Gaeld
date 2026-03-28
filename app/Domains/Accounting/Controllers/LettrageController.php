<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\LettrageLot;
use App\Domains\Accounting\Services\LettrageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LettrageController extends Controller
{
    public function __construct(private readonly LettrageService $service) {}

    /**
     * Show open items for an account (or an account picker if none selected).
     */
    public function index(Request $request): Response
    {
        $orgId = $request->user()->current_organization_id;
        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $accountId = $request->query('account');
        $account = $accountId
            ? Account::where('organization_id', $orgId)->findOrFail($accountId)
            : null;

        if ($account) {
            $this->authorize('view', $account);
        }

        $date = $request->query('date');
        $openItems = $account ? $this->service->getOpenItems($account, $date) : [];

        $lots = $account
            ? LettrageLot::where('account_id', $account->id)
                ->where('is_reversed', false)
                ->orderBy('letter_key')
                ->get()
            : [];

        return Inertia::render('Accounting/Lettrage', [
            'accounts' => $accounts,
            'account' => $account,
            'openItems' => $openItems,
            'lots' => $lots,
            'filterDate' => $date,
        ]);
    }

    /**
     * Letter a set of transaction lines.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['required', 'integer'],
        ]);

        $account = Account::where('organization_id', $request->user()->current_organization_id)
            ->findOrFail($validated['account_id']);

        $this->authorize('manage', $account);

        try {
            $this->service->letterLines($account, $validated['line_ids'], $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lettrage' => $e->getMessage()]);
        }

        return back()->with('success', __('app.lettrage_success'));
    }

    /**
     * Reverse a lettrage lot.
     */
    public function destroy(LettrageLot $lot): RedirectResponse
    {
        $this->authorize('manage', $lot->account);

        try {
            $this->service->unletter($lot);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lettrage' => $e->getMessage()]);
        }

        return back()->with('success', __('app.lettrage_reversed'));
    }
}
