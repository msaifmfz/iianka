<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionCaseActivity>
 */
class ReceptionCaseActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reception_case_id' => ReceptionCase::factory(),
            'user_id' => User::factory(),
            'type' => ReceptionCaseActivity::TYPE_UPDATED,
            'memo' => fake()->optional()->sentence(),
            'from_status' => null,
            'to_status' => null,
            'from_assigned_user_id' => null,
            'to_assigned_user_id' => null,
        ];
    }
}
