<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'work_date' => fake()->dateTimeBetween('-1 week', '+1 month')->format('Y-m-d'),
            'status' => fake()->randomElement([
                AttendanceRecord::STATUS_WORKING,
                AttendanceRecord::STATUS_LEAVE,
            ]),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function working(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceRecord::STATUS_WORKING,
        ]);
    }

    public function leave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceRecord::STATUS_LEAVE,
        ]);
    }
}
