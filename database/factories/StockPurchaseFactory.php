<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Stock;
use App\Models\StockPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockPurchase>
 */
class StockPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_id' => Stock::factory(),
            'term_starts_on' => '2026-06-21',
            'quantity' => '0.000',
        ];
    }

    public function forTerm(string $termStartsOn): static
    {
        return $this->state(fn (array $attributes): array => [
            'term_starts_on' => $termStartsOn,
        ]);
    }

    public function quantity(string $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
        ]);
    }
}
