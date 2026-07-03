<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReceptionDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionDocumentType>
 */
class ReceptionDocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
