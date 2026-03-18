<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'en',
            'email_verified_at' => $data['email_verified_at'] ?? null,
        ]);
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'locale' => $data['locale'] ?? null,
        ]));

        return $user;
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
