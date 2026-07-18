<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reception\Enums\ReceptionCaseAttachmentKind;
use App\Domain\Reception\Enums\ReceptionCaseAttachmentSource;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionCaseAttachment>
 */
class ReceptionCaseAttachmentFactory extends Factory
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
            'uploaded_by_user_id' => User::factory(),
            'kind' => ReceptionCaseAttachmentKind::Document,
            'source' => ReceptionCaseAttachmentSource::Upload,
            'name' => fake()->word().'.pdf',
            'disk' => 'local',
            'path' => 'reception-attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'duration_seconds' => null,
        ];
    }

    public function recording(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ReceptionCaseAttachmentKind::Audio,
            'source' => ReceptionCaseAttachmentSource::Recording,
            'name' => fake()->word().'.webm',
            'path' => 'reception-attachments/'.fake()->uuid().'.webm',
            'mime_type' => 'audio/webm',
            'extension' => 'webm',
            'duration_seconds' => fake()->numberBetween(10, 120),
        ]);
    }
}
