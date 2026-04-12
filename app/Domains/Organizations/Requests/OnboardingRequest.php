<?php

namespace App\Domains\Organizations\Requests;

class OnboardingRequest extends StoreOrganizationRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
