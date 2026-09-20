<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPlaceLogAttachment>
 */
class ClientPlaceLogAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_place_log_id' => ClientPlaceLog::factory(),
            'uploaded_by_user_id' => User::factory(),
            'kind' => ClientPlaceLogAttachmentKind::Image->value,
            'name' => 'photo',
            'disk' => ClientPlaceLogAttachment::DISK,
            'path' => 'crm-attachments/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'duration_seconds' => null,
        ];
    }
}
