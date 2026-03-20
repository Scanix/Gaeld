<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\DTOs\CreateUserData;
use App\Domains\Users\DTOs\UpdateUserProfileData;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(CreateUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'locale' => $data->locale,
            'email_verified_at' => $data->emailVerifiedAt,
        ]);
    }

    public function updateProfile(User $user, UpdateUserProfileData $data): User
    {
        $user->update([
            'name' => $data->name,
            'locale' => $data->locale,
        ]);

        return $user;
    }

    public function toggleHelp(User $user): bool
    {
        $user->update(['show_help' => ! $user->show_help]);

        return $user->show_help;
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
