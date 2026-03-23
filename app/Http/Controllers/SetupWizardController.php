<?php

namespace App\Http\Controllers;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Database\Seeders\SwissChartOfAccountsSeeder;
use Database\Seeders\SwissVatRatesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SetupWizardController extends Controller
{
    public function __construct(
        private readonly SwissChartOfAccountsSeeder $chartOfAccountsSeeder,
        private readonly SwissVatRatesSeeder $vatRatesSeeder,
    ) {}

    public function index(): Response|RedirectResponse
    {
        // Redirect if already set up
        if (Organization::exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Setup/Wizard');
    }

    public function store(Request $request): RedirectResponse
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

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['user_name'],
                'email' => $validated['user_email'],
                'password' => Hash::make($validated['user_password']),
                'locale' => $validated['locale'],
                'email_verified_at' => now(),
            ]);

            $org = Organization::create([
                'name' => $validated['org_name'],
                'legal_name' => $validated['org_legal_name'] ?? $validated['org_name'],
                'address' => $validated['org_address'] ?? null,
                'city' => $validated['org_city'] ?? null,
                'postal_code' => $validated['org_postal_code'] ?? null,
                'canton' => $validated['org_canton'] ?? null,
                'currency' => $validated['currency'],
                'locale' => $validated['locale'],
            ]);

            $org->users()->attach($user->id, ['role' => 'owner']);

            // Seed default chart of accounts and VAT rates
            $this->chartOfAccountsSeeder->run();
            $this->vatRatesSeeder->run();

            Auth::login($user);
        });

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to Gäld! Your organization has been set up.');
    }
}
