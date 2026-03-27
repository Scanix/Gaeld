<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\PostSocialChargesAction;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\SocialChargesService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialChargesController extends Controller
{
    public function index(SocialChargesService $service): Response
    {
        $this->authorize('viewAny', Account::class);

        return Inertia::render('Accounting/SocialCharges', [
            'rates' => $service->rates(),
        ]);
    }

    public function calculate(Request $request, SocialChargesService $service): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validate([
            'annual_income' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $result = $service->calculate((string) $validated['annual_income']);

        return response()->json($result);
    }

    public function post(Request $request, PostSocialChargesAction $action, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        $action->execute(
            $currentOrg->id(),
            (string) $validated['amount'],
            $validated['description'],
            $validated['date'] ?? null,
        );

        return redirect()->route('accounting.socialCharges')
            ->with('success', __('Social charges entry posted successfully.'));
    }
}
