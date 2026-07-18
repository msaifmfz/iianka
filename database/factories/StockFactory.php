<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Stock\Parsing\StockNameNormalizer;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'sku' => null,
            'name' => $name,
            'normalized_name' => new StockNameNormalizer()->normalize($name),
            'allows_fractional_quantity' => false,
            'is_active' => true,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
            'normalized_name' => new StockNameNormalizer()->normalize($name),
        ]);
    }

    public function quantity(string $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'current_quantity' => $quantity,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function fractional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'allows_fractional_quantity' => true,
        ]);
    }
}
