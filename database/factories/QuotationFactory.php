<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Application\Quotation\QuotationCatalog;
use App\Domain\Quotation\QuotationCalculator;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $catalog = app(QuotationCatalog::class)->indexed();
        $input = [
            'client_name' => '見積テスト株式会社', 'project_name' => 'アンカー取付工事',
            'delivery_location' => '栗東市 現場', 'person_in_charge' => '豊田', 'issued_on' => '2026-10-29',
            'category' => 'アンカー工事', 'expenses' => '0', 'minimum_charge' => '10000',
            'small_job' => false, 'holiday' => false, 'net_enabled' => false, 'net_multiplier' => '1',
            'display_mode' => 'detail', 'notes' => '',
            'items' => [[
                'mode' => 'master', 'catalog_id' => '0001', 'quantity' => '100',
                'adjustment' => 'standard', 'adjustment_value' => null,
                'conditions' => array_replace(array_fill_keys(array_keys(QuotationCalculator::CONDITIONS), false), ['deck' => true]),
                'rates' => array_map(fn (array $condition): string => (string) $condition['rate'], QuotationCalculator::CONDITIONS),
                'other_rate' => '0', 'length' => '300', 'processing_mode' => 'auto', 'processing_fee' => null, 'note' => '',
                'free_name' => '', 'free_specification' => '', 'free_unit' => '', 'free_price' => null,
            ]],
        ];

        return [
            'number' => 'Q-TEST-'.fake()->unique()->numerify('########'),
            'client_name' => $input['client_name'], 'project_name' => $input['project_name'], 'issued_on' => $input['issued_on'],
            'input' => $input, 'calculation' => (new QuotationCalculator)->calculate($input, $catalog),
            'catalog_snapshot' => ['0001' => $catalog['0001']],
            'issuer' => config('quotation.issuer'), 'version' => 1, 'created_by' => User::factory(),
        ];
    }
}
