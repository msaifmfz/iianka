<?php

namespace Database\Factories;

use App\Models\BusinessSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSchedule>
 */
class BusinessScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_on' => fake()->dateTimeBetween('-1 week', '+1 month')->format('Y-m-d'),
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'time_note' => null,
            'personnel' => fake()->numberBetween(1, 5).'名',
            'location' => fake()->city().' 会議室',
            'general_contractor' => fake()->company(),
            'person_in_charge' => fake()->name(),
            'content' => fake()->randomElement(['安全協議会', '定時総会', '営業打合せ']),
            'memo' => fake()->sentence(),
        ];
    }

    public function scheduledToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_on' => today()->toDateString(),
        ]);
    }
}
