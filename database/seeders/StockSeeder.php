<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Stock;
use App\Services\Stock\StockNameNormalizer;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Seed demo stock items until the admin stock-management UI ships.
     * Normalized names and aliases must stay globally unique.
     */
    public function run(): void
    {
        $normalizer = new StockNameNormalizer;

        $items = [
            ['name' => '牛乳', 'sku' => 'MILK-001', 'quantity' => '20.000', 'fractional' => false, 'active' => true, 'aliases' => ['ミルク']],
            ['name' => '牛乳パック', 'sku' => 'MILK-002', 'quantity' => '30.000', 'fractional' => false, 'active' => true, 'aliases' => []],
            ['name' => '軍手', 'sku' => 'GLOVE-001', 'quantity' => '100.000', 'fractional' => false, 'active' => true, 'aliases' => []],
            ['name' => '養生テープ', 'sku' => 'TAPE-001', 'quantity' => '50.000', 'fractional' => false, 'active' => true, 'aliases' => ['テープ']],
            ['name' => 'ウエス', 'sku' => 'RAG-001', 'quantity' => '12.500', 'fractional' => true, 'active' => true, 'aliases' => []],
            ['name' => 'Milk', 'sku' => 'MILK-EN-001', 'quantity' => '20.000', 'fractional' => false, 'active' => true, 'aliases' => ['Fresh Milk']],
            ['name' => 'Milk Powder', 'sku' => 'MILK-EN-002', 'quantity' => '7.000', 'fractional' => false, 'active' => true, 'aliases' => []],
            ['name' => 'Milk 2L', 'sku' => 'MILK-EN-003', 'quantity' => '15.000', 'fractional' => false, 'active' => true, 'aliases' => []],
            ['name' => 'Desk', 'sku' => 'DESK-001', 'quantity' => '15.000', 'fractional' => false, 'active' => true, 'aliases' => ['机']],
            ['name' => 'Paper', 'sku' => 'PAPER-001', 'quantity' => '100.000', 'fractional' => false, 'active' => true, 'aliases' => []],
            ['name' => '旧資材', 'sku' => 'LEGACY-001', 'quantity' => '5.000', 'fractional' => false, 'active' => false, 'aliases' => []],
        ];

        foreach ($items as $item) {
            $stock = Stock::query()->updateOrCreate(
                ['normalized_name' => $normalizer->normalize($item['name'])],
                [
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'allows_fractional_quantity' => $item['fractional'],
                    'is_active' => $item['active'],
                ],
            );

            if ($stock->wasRecentlyCreated) {
                $stock->forceFill(['current_quantity' => $item['quantity']])->save();
            }

            foreach ($item['aliases'] as $alias) {
                $stock->aliases()->updateOrCreate(
                    ['normalized_alias' => $normalizer->normalize($alias)],
                    ['alias' => $alias, 'is_active' => true],
                );
            }
        }
    }
}
