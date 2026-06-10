<?php

namespace App\Http\Controllers;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;

/**
 * Marks the current organization's onboarding checklist as dismissed.
 */
class OnboardingDismissController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrg): RedirectResponse
    {
        $org = $currentOrg->get();

        if ($org->onboarding_dismissed_at === null) {
            $org->forceFill(['onboarding_dismissed_at' => now()])->save();
        }

        return redirect()->route('dashboard');
    }
}
