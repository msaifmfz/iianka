<?php

namespace Database\Factories;

use App\Models\CleaningDutyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CleaningDutyRule>
 */
class CleaningDutyRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekday' => fake()->numberBetween(1, 5),
            'label' => '掃除当番',
            'location' => fake()->randomElement(['事務所', '休憩室', null]),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
