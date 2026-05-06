<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\PaymentInitiationService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outbound payments: aggregates payable expenses and produces a pain.001
 * batch (download in CE, bLink push in EE) for the user's e-banking.
 */
class PaymentInitiationController extends Controller
{
    use HandlesFlashErrorResponses;

    public function index(CurrentOrganization $currentOrg, PaymentInitiationService $service): InertiaResponse
    {
        $this->authorize('viewAny', BankAccount::class);

        $expenses = $service->pendingExpenses($currentOrg->get())
            ->map(fn ($e) => [
                'id' => $e->id,
                'date' => $e->date->toDateString(),
                'description' => $e->description ?? $e->vendor ?? $e->category,
                'vendor' => $e->vendor,
                'supplier' => $e->supplier ? [
                    'id' => $e->supplier->id,
                    'name' => $e->supplier->name,
                    'iban' => $e->supplier->iban,
                ] : null,
                'amount' => (string) $e->amount,
                'currency' => $e->currency,
                'status' => $e->status->value,
            ])
            ->values();

        $bankAccounts = BankAccount::query()
            ->where('organization_id', $currentOrg->id())
            ->where('is_active', true)
            ->whereNotNull('iban')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'iban', 'currency'])
            ->map(fn (BankAccount $ba) => [
                'id' => $ba->id,
                'uuid' => $ba->uuid,
                'name' => $ba->name,
                'iban' => $ba->iban,
                'currency' => $ba->currency,
            ])
            ->values();

        return Inertia::render('Banking/PaymentsOutgoing', [
            'expenses' => $expenses,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function download(
        Request $request,
        PaymentInitiationService $service,
    ): RedirectResponse|Response {
        $validated = $request->validate([
            'bank_account_id' => 'required|integer',
            'expense_ids' => 'required|array|min:1',
            'expense_ids.*' => 'string',
            'execution_date' => 'nullable|date',
        ]);

        /** @var BankAccount $debtor */
        $debtor = BankAccount::query()->findOrFail($validated['bank_account_id']);
        $this->authorize('view', $debtor);

        try {
            $result = $service->prepareBatch(
                $debtor,
                $validated['expense_ids'],
                isset($validated['execution_date']) ? Carbon::parse($validated['execution_date']) : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->backWithError($e);
        }

        if ($result->download !== null) {
            return $result->download;
        }

        return redirect()->route('payments.outgoing.index')
            ->with('success', __('app.payments_outgoing_submitted', [
                'count' => $result->count,
                'id' => $result->remoteBatchId,
            ]));
    }
}
