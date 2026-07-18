<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Stock\Parsing\StockNameNormalizer;
use App\Models\Stock;
use App\Models\StockAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAlias>
 */
class StockAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = fake()->unique()->words(2, true);

        return [
            'stock_id' => Stock::factory(),
            'alias' => $alias,
            'normalized_alias' => new StockNameNormalizer()->normalize($alias),
            'is_active' => true,
        ];
    }

    public function named(string $alias): static
    {
        return $this->state(fn (array $attributes): array => [
            'alias' => $alias,
            'normalized_alias' => new StockNameNormalizer()->normalize($alias),
        ]);
    }
}
