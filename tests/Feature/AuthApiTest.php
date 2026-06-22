<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');
    }

    public function test_user_can_register_and_login(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '919876543210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'India',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
            'phone_country_code' => '91',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('user_profiles', [
            'country' => 'India',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'phone' => '919876543210',
            'password' => 'password123',
            'device_name' => 'test',
        ]);

        $login->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '9876543210',
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '919876543211',
            'password' => 'password123',
        ])->assertUnauthorized();

        $token = $login->json('data.token');
        $this->getJson('/api/v1/user/profile', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_login_with_email(): void
    {
        $this->ensureLoginTestUser();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'demo1@mbxzone.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_login_with_login_field_and_mixed_case_email(): void
    {
        $this->ensureLoginTestUser();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'Demo1@MBXZone.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_login_with_demo_phone_that_already_includes_country_code(): void
    {
        $user = $this->ensureLoginTestUser();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '9100000001',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '919100000001',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    private function ensureLoginTestUser(): User
    {
        $user = User::query()->where('email', 'demo1@mbxzone.test')->first();

        if ($user === null) {
            $user = User::factory()->create([
                'phone' => '9100000001',
                'email' => 'demo1@mbxzone.test',
                'password' => 'password',
            ]);
        } else {
            $user->password = 'password';
            $user->save();
        }

        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }

        return $user;
    }

    public function test_public_prices_endpoint(): void
    {
        $this->getJson('/api/v1/prices')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
