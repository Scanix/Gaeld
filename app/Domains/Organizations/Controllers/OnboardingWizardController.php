<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Accounting\DTOs\FiscalYearData;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Organizations\Enums\OrganizationModule;
use App\Domains\Organizations\Requests\OnboardingWizardRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guided post-signup setup wizard.
 *
 * Lets a new owner pick their activity type and which optional modules to
 * enable (so the menu isn't cluttered by default), complete their company
 * details, and optionally create a first fiscal year and bank account.
 *
 * Edition-agnostic: gated purely on the user's onboarding_completed_at flag
 * and the current organization, so it works in both SaaS and self-hosted CE.
 */
class OnboardingWizardController extends Controller
{
    public function __construct(
        private readonly FiscalYearService $fiscalYears,
    ) {}

    public function show(Request $request, CurrentOrganization $currentOrg): Response|RedirectResponse
    {
        if ($request->user()->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $org = $currentOrg->get();
        $this->authorize('update', $org);

        return Inertia::render('Onboarding/Wizard', [
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
                'legal_name' => $org->legal_name,
                'address' => $org->address,
                'city' => $org->city,
                'postal_code' => $org->postal_code,
                'canton' => $org->canton,
                'vat_number' => $org->vat_number,
                'business_type' => $org->business_type?->value,
                'enabled_modules' => $org->enabled_modules,
            ],
            'modules' => OrganizationModule::values(),
            'modulePresets' => OrganizationModule::presets(),
            'hasFiscalYear' => FiscalYear::where('organization_id', $org->id)->exists(),
            'hasBankAccount' => BankAccount::where('organization_id', $org->id)->exists(),
        ]);
    }

    public function store(OnboardingWizardRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $org = $currentOrg->get();
        $this->authorize('update', $org);

        $data = $request->validated();

        try {
            DB::transaction(function () use ($org, $data, $request) {
                $update = [
                    'legal_name' => $data['legal_name'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'canton' => $data['canton'] ?? null,
                    'vat_number' => $data['vat_number'] ?? null,
                    'enabled_modules' => $request->modules(),
                ];

                if (in_array($data['business_type'] ?? null, BusinessType::values(), true)) {
                    $update['business_type'] = $data['business_type'];
                }

                $org->update($update);

                if (! empty($data['fiscal_year_start']) && ! empty($data['fiscal_year_end'])) {
                    $this->fiscalYears->create($org, FiscalYearData::fromArray([
                        'name' => $data['fiscal_year_name'],
                        'start_date' => $data['fiscal_year_start'],
                        'end_date' => $data['fiscal_year_end'],
                    ]));
                }

                if (! empty($data['bank_account_name'])) {
                    BankAccount::create(CreateBankAccountData::fromArray([
                        'organization_id' => $org->id,
                        'name' => $data['bank_account_name'],
                        'bank_name' => $data['bank_name'] ?? null,
                        'iban' => $data['iban'] ?? null,
                        'currency' => $org->currency ?? 'CHF',
                    ])->toArray());
                }

                $request->user()->update(['onboarding_completed_at' => now()]);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')
            ->with('success', __('app.onboarding_wizard_complete'));
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard');
    }
}
