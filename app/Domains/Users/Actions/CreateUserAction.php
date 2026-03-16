<?php

namespace App\Domains\Users\Actions;

use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;

class CreateUserAction
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function execute(array $data): User
    {
        return $this->userService->create($data);
    }
}
