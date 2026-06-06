<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\ReferralCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('9#########'),
            'password' => static::$password ??= 'password',
            'referral_code' => ReferralCodeGenerator::generate(),
            'status' => 'active',
            'kyc_status' => 'not_submitted',
            'phone_verified' => true,
        ];
    }
}
