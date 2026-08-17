<?php

namespace Database\Factories;

use App\Models\ConstructionSchedule;
use App\Models\SiteGuideFile;
use App\Services\BusinessDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConstructionSchedule>
 */
class ConstructionScheduleFactory extends Factory
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
            'schedule_number' => null,
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'time_note' => null,
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => fake()->streetAddress(),
            'personnel' => fake()->numberBetween(2, 8).'名',
            'location' => fake()->city().' 建設現場',
            'general_contractor' => fake()->company(),
            'person_in_charge' => fake()->name(),
            'content' => fake()->sentence(),
            'navigation_address' => fake()->address(),
        ];
    }

    public function scheduledToday(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_on' => BusinessDate::today()->toDateString(),
        ]);
    }

    public function scheduledOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_on' => $date,
        ]);
    }

    public function usingGuideFile(SiteGuideFile ...$guideFiles): static
    {
        return $this->afterCreating(function (ConstructionSchedule $schedule) use ($guideFiles): void {
            $schedule->selectedGuideFiles()->attach(collect($guideFiles)->pluck('id'));
        });
    }
}
