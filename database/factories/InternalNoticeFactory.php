<?php

namespace Database\Factories;

use App\Models\InternalNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalNotice>
 */
class InternalNoticeFactory extends Factory
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
            'starts_at' => null,
            'ends_at' => null,
            'time_note' => '本日中',
            'title' => fake()->randomElement(['健康診断', '社内連絡', '書類提出']),
            'location' => fake()->randomElement(['本社', '会議室', null]),
            'content' => fake()->sentence(),
            'memo' => fake()->optional()->sentence(),
        ];
    }

    public function scheduledToday(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_on' => today()->toDateString(),
        ]);
    }
}
