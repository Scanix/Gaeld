<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use Illuminate\Support\Facades\DB;

class CompleteSetupAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly OrganizationService $organizationService,
        private readonly OrganizationSetupService $organizationSetupService,
    ) {}

    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userService->create([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
                'password' => $data['user_password'],
                'locale' => $data['locale'],
                'email_verified_at' => now(),
            ]);

            $this->organizationService->create($user, [
                'name' => $data['org_name'],
                'legal_name' => $data['org_legal_name'] ?? $data['org_name'],
                'address' => $data['org_address'] ?? null,
                'city' => $data['org_city'] ?? null,
                'postal_code' => $data['org_postal_code'] ?? null,
                'canton' => $data['org_canton'] ?? null,
                'currency' => $data['currency'],
                'locale' => $data['locale'],
            ]);

            $this->organizationSetupService->seedSwissDefaults();

            return $user;
        });
    }
}