<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\PostSocialChargesAction;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Requests\PostSocialChargesRequest;
use App\Domains\Accounting\Services\SocialChargesService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Swiss AVS/AI/APG social charges calculator for independent workers.
 */
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

    public function post(PostSocialChargesRequest $request, PostSocialChargesAction $action, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();

        $action->execute(
            $currentOrg->id(),
            (string) $validated['amount'],
            $validated['description'],
            $validated['date'] ?? null,
        );

        return redirect()->route('accounting.social-charges')
            ->with('success', __('app.social_charges_posted'));
    }
}
