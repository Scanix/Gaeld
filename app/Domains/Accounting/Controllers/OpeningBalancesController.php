<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\RecordOpeningBalancesAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Requests\StoreOpeningBalancesRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard for seeding opening balances when an organization starts using
 * Gäld without a prior closed fiscal year in the system.
 */
class OpeningBalancesController extends Controller
{
    use HandlesFlashErrorResponses;

    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', JournalEntry::class);

        $org = $currentOrg->get();
        $orgId = $currentOrg->id();

        $balanceSheetTypes = [
            AccountType::Asset->value,
            AccountType::Liability->value,
            AccountType::Equity->value,
        ];

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('type', $balanceSheetTypes)
            ->where('code', '!=', AccountCode::OPENING_BALANCE)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->display_name,
                'type' => $a->type->value,
            ]);

        $existingOpening = JournalEntry::where('organization_id', $orgId)
            ->where('reference', 'like', 'OPENING-%')
            ->where('is_posted', true)
            ->orderByDesc('date')
            ->first(['id', 'date', 'reference']);

        return Inertia::render('Accounting/OpeningBalances', [
            'accounts' => $accounts,
            'defaultDate' => sprintf('%d-01-01', now()->year),
            'existingOpening' => $existingOpening,
            'isStartingFresh' => $org->setup_mode === 'fresh',
        ]);
    }

    public function store(
        StoreOpeningBalancesRequest $request,
        CurrentOrganization $currentOrg,
        RecordOpeningBalancesAction $action,
    ): RedirectResponse {
        $this->authorize('create', JournalEntry::class);

        $validated = $request->validated();

        try {
            $entry = $action->execute(
                orgId: $currentOrg->id(),
                date: $validated['date'],
                balances: $validated['balances'],
                reference: $validated['reference'] ?? null,
                description: $validated['description'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->backWithError($e);
        }

        if ($entry === null) {
            return $this->backWithError(__('app.opening_balances_all_zero'));
        }

        return redirect()->route('accounting.journal')
            ->with('success', __('app.opening_balances_recorded'));
    }
}
