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
     * Show open items for an account.
     */
    public function index(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        $date = $request->query('date');
        $openItems = $this->service->getOpenItems($account, $date);

        $lots = LettrageLot::where('account_id', $account->id)
            ->where('is_reversed', false)
            ->orderBy('letter_key')
            ->get();

        return Inertia::render('Accounting/Lettrage', [
            'account' => $account,
            'openItems' => $openItems,
            'lots' => $lots,
            'filterDate' => $date,
        ]);
    }

    /**
     * Letter a set of transaction lines.
     */
    public function store(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('manage', $account);

        $validated = $request->validate([
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['required', 'integer'],
        ]);

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
