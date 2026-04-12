<?php

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
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
            'name' => fake()->name(),
            'login_id' => fake()->unique()->bothify('worker-####'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'role' => UserRole::Viewer,
            'is_hidden_from_workers' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user can manage construction schedules.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate that the user can manage content but not users.
     */
    public function editor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Editor,
            'is_admin' => false,
        ]);
    }

    /**
     * Indicate that the user should be hidden from worker-facing pages.
     */
    public function hiddenFromWorkers(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_hidden_from_workers' => true,
        ]);
    }

    /**
     * Indicate that the model has no email address on file.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
