<?php

namespace Database\Factories;

use App\Models\ConstructionSite;
use App\Models\SiteGuideFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteGuideFile>
 */
class SiteGuideFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'construction_site_id' => ConstructionSite::factory(),
            'construction_schedule_id' => null,
            'name' => fake()->words(2, true).'.pdf',
            'disk' => 'public',
            'path' => 'site-guides/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
