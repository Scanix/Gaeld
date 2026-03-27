<?php

namespace App\Domains\Organizations\Actions;

use App\Domains\Organizations\DTOs\CompleteSetupData;
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

    public function execute(CompleteSetupData $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userService->create($data->user);

            $org = $this->organizationService->create($user, $data->organization);

            $this->organizationSetupService->seedSwissDefaults($org);

            return $user;
        });
    }
}
