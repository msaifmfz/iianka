<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Models\Client;
use App\Models\ClientPlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPlace>
 */
class ClientPlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'kind' => ClientPlaceKind::Site->value,
            'name' => fake()->streetName(),
            'address' => fake()->optional()->address(),
            'lat' => fake()->latitude(34.4, 35.1),
            'lng' => fake()->longitude(135.1, 135.9),
            'archived_at' => null,
            'last_logged_at' => null,
        ];
    }

    public function office(): static
    {
        return $this->state(fn (): array => ['kind' => ClientPlaceKind::Office->value]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
