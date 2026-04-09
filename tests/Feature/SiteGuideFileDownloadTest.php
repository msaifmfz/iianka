<?php

declare(strict_types=1);

use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('authenticated users can open private guide files', function (): void {
    Storage::fake('local');

    $file = SiteGuideFile::factory()->create([
        'disk' => 'local',
        'name' => 'guide.pdf',
        'path' => 'site-guides/guide.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Storage::disk('local')->put($file->path, 'private guide contents');

    $this->actingAs(User::factory()->create())
        ->get(route('site-guide-files.show', $file))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="guide.pdf"');
});

test('guests cannot open private guide files', function (): void {
    $file = SiteGuideFile::factory()->create([
        'disk' => 'local',
    ]);

    $this->get(route('site-guide-files.show', $file))
        ->assertRedirect(route('login'));
});
