<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Actions\CompleteSetupAction;
use App\Domains\Organizations\DTOs\CompleteSetupData;
use App\Domains\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupWizardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        // Redirect if already set up
        if (Organization::exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Setup/Wizard');
    }

    public function store(Request $request, CompleteSetupAction $completeSetupAction): RedirectResponse
    {
        if (Organization::exists()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|email|unique:users,email',
            'user_password' => 'required|string|min:8|confirmed',
            'org_name' => 'required|string|max:255',
            'org_legal_name' => 'nullable|string|max:255',
            'org_address' => 'nullable|string',
            'org_city' => 'nullable|string|max:100',
            'org_postal_code' => 'nullable|string|max:10',
            'org_canton' => 'nullable|string|size:2',
            'org_vat_number' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'locale' => 'required|string|in:en,fr,de,it,rm',
        ]);

        $user = $completeSetupAction->execute(CompleteSetupData::fromArray($validated));
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', __('app.welcome_setup'));
    }
}
