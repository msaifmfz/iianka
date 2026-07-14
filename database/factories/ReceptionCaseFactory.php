<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReceptionCase;
use App\Models\ReceptionDocumentType;
use App\Models\User;
use App\ReceptionCasePriority;
use App\ReceptionCaseStatus;
use App\Services\BusinessDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionCase>
 */
class ReceptionCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_number' => fake()->unique()->bothify('WJA-C-'.BusinessDate::today()->format('Ymd').'-####'),
            'status' => ReceptionCaseStatus::Draft,
            'priority' => ReceptionCasePriority::Normal,
            'company_name' => fake()->company(),
            'site_name' => fake()->streetName(),
            'reception_document_type_id' => ReceptionDocumentType::factory(),
            'reception_content' => fake()->sentence(),
            'work_memo' => null,
            'due_on' => BusinessDate::today()->addDays(3)->toDateString(),
            'scheduled_on' => null,
            'receptor_user_id' => User::factory(),
            'assigned_user_id' => null,
            'completed_at' => null,
            'completed_by_user_id' => null,
            // Default (draft) state stays null; submitted/active states below set it.
            'last_activity_at' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReceptionCaseStatus::Received,
            'last_activity_at' => now(),
        ]);
    }

    public function inProgress(?User $assignedUser = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReceptionCaseStatus::InProgress,
            'assigned_user_id' => $assignedUser->id ?? User::factory(),
            'last_activity_at' => now(),
        ]);
    }

    public function handover(?User $assignedUser = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReceptionCaseStatus::Handover,
            'assigned_user_id' => $assignedUser->id ?? User::factory(),
            'last_activity_at' => now(),
        ]);
    }

    public function completed(?User $completedBy = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReceptionCaseStatus::Completed,
            'completed_at' => now(),
            'completed_by_user_id' => $completedBy->id ?? User::factory(),
            'last_activity_at' => now(),
        ]);
    }
}
