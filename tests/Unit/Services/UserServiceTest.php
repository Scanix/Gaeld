<?php

namespace Tests\Unit\Services;

use App\Domains\Users\DTOs\UpdateUserProfileData;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserService();
    }

    public function test_update_profile_only_persists_name_and_locale(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        /** @var User $user */

        $user->shouldReceive('update')->once()->with([
            'name' => 'Updated Name',
            'locale' => 'fr',
        ]);

        $result = $this->service->updateProfile($user, UpdateUserProfileData::fromArray([
            'name' => 'Updated Name',
            'locale' => 'fr',
        ]));

        $this->assertSame($user, $result);
    }

    public function test_toggle_help_flips_user_flag_and_returns_new_state(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        /** @var User $user */
        $user->show_help = false;

        $user->shouldReceive('update')->once()->with(['show_help' => true])->andReturnUsing(function (array $attributes) use ($user) {
            $user->show_help = $attributes['show_help'];

            return true;
        });

        $result = $this->service->toggleHelp($user);

        $this->assertTrue($result);
        $this->assertTrue($user->show_help);
    }

    public function test_update_password_hashes_before_persisting(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        /** @var User $user */

        Hash::shouldReceive('make')->once()->with('new-secret')->andReturn('hashed-secret');
        $user->shouldReceive('update')->once()->with(['password' => 'hashed-secret']);

        $this->service->updatePassword($user, 'new-secret');
    }
}