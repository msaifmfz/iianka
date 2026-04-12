<?php

declare(strict_types=1);

use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('site guide library index shows all guide files without site grouping', function (): void {
    $admin = User::factory()->admin()->create();

    SiteGuideFile::factory()->create([
        'name' => '新宿ビル_搬入口.pdf',
    ]);
    SiteGuideFile::factory()->create([
        'name' => '渋谷駅前_集合場所.png',
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($admin)
        ->get(route('construction-sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/index')
            ->where('canManage', true)
            ->has('guideFiles', 2)
            ->where('guideFiles.0.name', '新宿ビル_搬入口.pdf')
            ->where('guideFiles.1.name', '渋谷駅前_集合場所.png')
        );
});

test('site guide detail shows a single guide file', function (): void {
    $user = User::factory()->create();
    $guideFile = SiteGuideFile::factory()->create([
        'name' => '案内図A.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('construction-sites.show', $guideFile))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/show')
            ->where('canManage', false)
            ->where('guideFile.id', $guideFile->id)
            ->where('guideFile.name', '案内図A.pdf')
            ->where('guideFile.url', route('site-guide-files.show', $guideFile))
        );
});

test('admins can open the site guide create form', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('construction-sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('guideFile', null)
        );
});

test('editors can open the site guide create form', function (): void {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->get(route('construction-sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('guideFile', null)
        );
});

test('viewers cannot open the site guide create form', function (): void {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('construction-sites.create'))
        ->assertForbidden();
});

test('admins can open the site guide edit form', function (): void {
    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create([
        'name' => '案内図B.png',
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($admin)
        ->get(route('construction-sites.edit', $guideFile))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('guideFile.id', $guideFile->id)
            ->where('guideFile.name', '案内図B.png')
        );
});

test('admins can create standalone site guide files', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-sites.store'), [
            'name' => '横浜駅前_搬入口',
            'guide_file' => UploadedFile::fake()->create('original.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('construction-sites.index'));

    $guideFiles = SiteGuideFile::query()->orderBy('name')->get();

    expect($guideFiles)->toHaveCount(1)
        ->and($guideFiles->first()->name)->toBe('横浜駅前_搬入口');

    $guideFiles->each(fn (SiteGuideFile $file): mixed => Storage::disk('local')->assertExists($file->path));
});

test('admins can create standalone site guide files from smartphone photos', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-sites.store'), [
            'name' => '現地写真',
            'guide_file' => UploadedFile::fake()->create('site-photo.heic', 100, 'image/heic'),
        ])
        ->assertRedirect(route('construction-sites.index'));

    $guideFile = SiteGuideFile::query()->where('name', '現地写真')->firstOrFail();

    expect($guideFile->mime_type)->toBe('image/heic')
        ->and($guideFile->size)->toBeGreaterThan(0);

    Storage::disk('local')->assertExists($guideFile->path);
});

test('standalone site guide file uploads accept png images larger than phps default upload limit', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-sites.store'), [
            'name' => 'PNG案内図',
            'guide_file' => UploadedFile::fake()->create('guide.png', 3 * 1024, 'image/png'),
        ])
        ->assertRedirect(route('construction-sites.index'));

    $guideFile = SiteGuideFile::query()->where('name', 'PNG案内図')->firstOrFail();

    expect($guideFile->mime_type)->toBe('image/png');

    Storage::disk('local')->assertExists($guideFile->path);
});

test('standalone site guide file uploads may be up to 50 megabytes', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-sites.store'), [
            'name' => '大きい案内図',
            'guide_file' => UploadedFile::fake()->create('large-guide.pdf', 50 * 1024, 'application/pdf'),
        ])
        ->assertRedirect(route('construction-sites.index'));

    expect(SiteGuideFile::query()->where('name', '大きい案内図')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->from(route('construction-sites.create'))
        ->post(route('construction-sites.store'), [
            'name' => '大きすぎる案内図',
            'guide_file' => UploadedFile::fake()->create('too-large-guide.pdf', (50 * 1024) + 1, 'application/pdf'),
        ])
        ->assertRedirect(route('construction-sites.create'))
        ->assertSessionHasErrors('guide_file');
});

test('admins must provide a name when creating a standalone site guide file', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-sites.store'), [
            'guide_file' => UploadedFile::fake()->create('original.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('name');

    expect(SiteGuideFile::query()->count())->toBe(0);
});

test('admins must provide a unique name when creating a standalone site guide file', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    SiteGuideFile::factory()->create([
        'name' => '現場案内図',
    ]);

    $this->actingAs($admin)
        ->from(route('construction-sites.create'))
        ->post(route('construction-sites.store'), [
            'name' => '現場案内図',
            'guide_file' => UploadedFile::fake()->create('original.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('construction-sites.create'))
        ->assertSessionHasErrors('name');

    expect(SiteGuideFile::query()->where('name', '現場案内図')->count())->toBe(1);
});

test('standalone site guide file validation attributes are displayed in japanese', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('construction-sites.create'))
        ->post(route('construction-sites.store'), [
            'name' => '現場案内図',
        ])
        ->assertRedirect(route('construction-sites.create'))
        ->assertSessionHasErrors([
            'guide_file' => '案内図ファイルは必須項目です。',
        ]);
});

test('admins can rename a standalone site guide file', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create([
        'name' => '名古屋駅前_旧案内図.pdf',
    ]);

    $this->actingAs($admin)
        ->put(route('construction-sites.update', $guideFile), [
            'name' => '名古屋駅前_追加案内図.pdf',
        ])
        ->assertRedirect(route('construction-sites.show', $guideFile));

    expect($guideFile->refresh()->name)->toBe('名古屋駅前_追加案内図.pdf');

    $this->actingAs($admin)
        ->get(route('construction-sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/index')
            ->where('guideFiles.0.id', $guideFile->id)
            ->where('guideFiles.0.name', '名古屋駅前_追加案内図.pdf')
        );
});

test('admins must provide a unique name when renaming a standalone site guide file', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    SiteGuideFile::factory()->create([
        'name' => '既存案内図',
    ]);
    $guideFile = SiteGuideFile::factory()->create([
        'name' => '変更前案内図',
    ]);

    $this->actingAs($admin)
        ->from(route('construction-sites.edit', $guideFile))
        ->put(route('construction-sites.update', $guideFile), [
            'name' => '既存案内図',
        ])
        ->assertRedirect(route('construction-sites.edit', $guideFile))
        ->assertSessionHasErrors('name');

    expect($guideFile->refresh()->name)->toBe('変更前案内図');
});
