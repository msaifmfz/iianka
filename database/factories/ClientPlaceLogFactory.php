<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Domain\Crm\Enums\ClientReaction;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPlaceLog>
 */
class ClientPlaceLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_place_id' => ClientPlace::factory(),
            'user_id' => User::factory(),
            'client_contact_id' => null,
            'type' => ClientPlaceLogType::Visit->value,
            'occurred_at' => now(),
            'summary' => fake()->paragraph(),
            'reaction' => fake()->randomElement(ClientReaction::cases())->value,
        ];
    }
}
