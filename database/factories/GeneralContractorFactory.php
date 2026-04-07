<?php

namespace Database\Factories;

use App\Models\GeneralContractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneralContractor>
 */
class GeneralContractorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
