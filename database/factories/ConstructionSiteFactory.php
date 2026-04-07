<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConstructionSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConstructionSite>
 */
class ConstructionSiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' 現場',
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
