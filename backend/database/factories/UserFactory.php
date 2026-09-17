<?php

namespace Database\Factories;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model this factory creates. Declared explicitly because the model
     * lives under App\Modules\Auth, not the App\ root namespace Factory's
     * convention-based guessing assumes.
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'auth_provider' => 'email',
            'auth_provider_id' => null,
            'guest_device_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user authenticates via a social provider (apple/google), not email/password.
     */
    public function withProvider(string $provider, ?string $providerId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'auth_provider' => $provider,
            'auth_provider_id' => $providerId ?? fake()->uuid(),
            'password_hash' => null,
        ]);
    }
}
