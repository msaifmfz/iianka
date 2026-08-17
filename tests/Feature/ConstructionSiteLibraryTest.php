<?php

declare(strict_types=1);

use App\Models\ConstructionSchedule;
use App\Models\SiteGuideFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Headers for the follow-up request the library makes the first time a card is
 * held. `usageSchedules` is an optional prop, so it stays out of the payload
 * until a partial reload asks for it by name; that reload answers with JSON,
 * hence the assertJsonPath() assertions on it below.
 *
 * Inertia only knows the asset version once it has answered a request, so the
 * callers below load the page first — which is what the browser does anyway.
 *
 * @return array<string, string>
 */
function usageScheduleHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'construction-sites/index',
        'X-Inertia-Partial-Data' => 'usageSchedules',
    ];
}

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
            ->where('filters.search', '')
            ->where('totalCount', 2)
            ->has('guideFiles', 2)
            ->where('guideFiles.0.name', '新宿ビル_搬入口.pdf')
            ->where('guideFiles.1.name', '渋谷駅前_集合場所.png')
            ->missing('usageSchedules')
        );
});

test('site guide library index filters guide files by name', function (): void {
    $admin = User::factory()->admin()->create();

    SiteGuideFile::factory()->create(['name' => '新宿ビル_搬入口.pdf']);
    SiteGuideFile::factory()->create(['name' => '渋谷駅前_集合場所.png']);

    $this->actingAs($admin)
        ->get(route('construction-sites.index', ['search' => '渋谷']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/index')
            ->where('filters.search', '渋谷')
            // The unfiltered library size, so the count tile can say "1 / 2".
            ->where('totalCount', 2)
            ->has('guideFiles', 1)
            ->where('guideFiles.0.name', '渋谷駅前_集合場所.png')
        );
});

test('site guide library search matches like wildcards verbatim', function (): void {
    $admin = User::factory()->admin()->create();

    SiteGuideFile::factory()->create(['name' => '進捗100%_案内図.pdf']);
    SiteGuideFile::factory()->create(['name' => '別の案内図.pdf']);

    $this->actingAs($admin)
        ->get(route('construction-sites.index', ['search' => '100%_案内']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('guideFiles', 1)
            ->where('guideFiles.0.name', '進捗100%_案内図.pdf')
        );

    // A bare % is a literal too: it matches only the name that contains one,
    // rather than acting as a wildcard and matching everything.
    $this->actingAs($admin)
        ->get(route('construction-sites.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('guideFiles', 1)
            ->where('guideFiles.0.name', '進捗100%_案内図.pdf')
        );
});

test('site guide library index counts the schedules using each guide file', function (): void {
    $admin = User::factory()->admin()->create();
    $usedGuideFile = SiteGuideFile::factory()->create(['name' => 'A_使用中.pdf']);
    $unusedGuideFile = SiteGuideFile::factory()->create(['name' => 'B_未使用.pdf']);

    ConstructionSchedule::factory()->count(2)->usingGuideFile($usedGuideFile)->create();

    $this->actingAs($admin)
        ->get(route('construction-sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('guideFiles.0.id', $usedGuideFile->id)
            ->where('guideFiles.0.schedules_count', 2)
            ->where('guideFiles.1.id', $unusedGuideFile->id)
            ->where('guideFiles.1.schedules_count', 0)
        );
});

test('site guide usage schedules are returned newest first on a partial reload', function (): void {
    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create(['name' => '案内図.pdf']);

    $older = ConstructionSchedule::factory()
        ->scheduledOn('2026-05-01')
        ->usingGuideFile($guideFile)
        ->create(['location' => '古い現場']);
    $newer = ConstructionSchedule::factory()
        ->scheduledOn('2026-06-01')
        ->usingGuideFile($guideFile)
        ->create(['location' => '新しい現場']);

    $usage = "props.usageSchedules.{$guideFile->id}";

    $this->actingAs($admin)->get(route('construction-sites.index'))->assertOk();

    $this->actingAs($admin)
        ->get(route('construction-sites.index'), usageScheduleHeaders())
        ->assertOk()
        ->assertJsonPath("{$usage}.0.id", $newer->id)
        ->assertJsonPath("{$usage}.0.type", 'construction')
        ->assertJsonPath("{$usage}.0.title", '新しい現場')
        ->assertJsonPath("{$usage}.0.scheduled_on", '2026-06-01')
        ->assertJsonPath("{$usage}.0.status", ConstructionSchedule::STATUS_SCHEDULED)
        ->assertJsonPath("{$usage}.1.id", $older->id)
        ->assertJsonPath("{$usage}.1.scheduled_on", '2026-05-01');
});

test('site guide usage schedules only cover the filtered guide files', function (): void {
    $admin = User::factory()->admin()->create();
    $matching = SiteGuideFile::factory()->create(['name' => '渋谷_案内図.pdf']);
    $other = SiteGuideFile::factory()->create(['name' => '新宿_案内図.pdf']);

    ConstructionSchedule::factory()->usingGuideFile($matching)->create();
    ConstructionSchedule::factory()->usingGuideFile($other)->create();

    $this->actingAs($admin)->get(route('construction-sites.index'))->assertOk();

    $this->actingAs($admin)
        ->get(route('construction-sites.index', ['search' => '渋谷']), usageScheduleHeaders())
        ->assertOk()
        ->assertJsonCount(1, "props.usageSchedules.{$matching->id}")
        ->assertJsonMissingPath("props.usageSchedules.{$other->id}");
});

test('site guide usage schedules list a shared schedule under every guide file it uses', function (): void {
    $admin = User::factory()->admin()->create();
    $first = SiteGuideFile::factory()->create(['name' => 'A_案内図.pdf']);
    $second = SiteGuideFile::factory()->create(['name' => 'B_案内図.pdf']);

    // The eager load is constrained to the guide files being asked about, so a
    // schedule spanning two of them has to surface under both keys.
    $shared = ConstructionSchedule::factory()->usingGuideFile($first, $second)->create();

    $this->actingAs($admin)->get(route('construction-sites.index'))->assertOk();

    $this->actingAs($admin)
        ->get(route('construction-sites.index'), usageScheduleHeaders())
        ->assertOk()
        ->assertJsonPath("props.usageSchedules.{$first->id}.0.id", $shared->id)
        ->assertJsonPath("props.usageSchedules.{$second->id}.0.id", $shared->id);
});

test('the usage schedule reload does not rebuild the listing props', function (): void {
    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create();

    ConstructionSchedule::factory()->usingGuideFile($guideFile)->create();

    $this->actingAs($admin)->get(route('construction-sites.index'))->assertOk();

    // guideFiles and totalCount are closures precisely so this reload skips
    // them; asserting their absence is what keeps them closures.
    $this->actingAs($admin)
        ->get(route('construction-sites.index'), usageScheduleHeaders())
        ->assertOk()
        ->assertJsonCount(1, "props.usageSchedules.{$guideFile->id}")
        ->assertJsonMissingPath('props.guideFiles')
        ->assertJsonMissingPath('props.totalCount')
        ->assertJsonMissingPath('props.filters');
});

test('site guide usage schedules hide assignees hidden from workers', function (): void {
    $admin = User::factory()->admin()->create();
    $visibleUser = User::factory()->create(['name' => '表示される担当者']);
    $hiddenUser = User::factory()->create([
        'name' => '隠れた担当者',
        'is_hidden_from_workers' => true,
    ]);
    $guideFile = SiteGuideFile::factory()->create();

    $schedule = ConstructionSchedule::factory()->usingGuideFile($guideFile)->create();
    $schedule->assignedUsers()->attach([$visibleUser->id, $hiddenUser->id]);

    $this->actingAs($admin)->get(route('construction-sites.index'))->assertOk();

    $response = $this->actingAs($admin)
        ->get(route('construction-sites.index'), usageScheduleHeaders())
        ->assertOk();

    $assignedNames = collect($response->json("props.usageSchedules.{$guideFile->id}.0.assigned_users"))
        ->pluck('name');

    expect($assignedNames)->toContain('表示される担当者')
        ->and($assignedNames)->not->toContain('隠れた担当者');
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
            ->where('guideFile.schedules_count', 0)
            ->has('usageSchedules', 0)
        );
});

test('site guide detail lists the schedules using the guide file', function (): void {
    $user = User::factory()->create();
    $guideFile = SiteGuideFile::factory()->create();
    $schedule = ConstructionSchedule::factory()
        ->scheduledOn('2026-06-01')
        ->usingGuideFile($guideFile)
        ->create(['location' => '品川タワー']);

    // A schedule that uses a different guide file must not leak in.
    ConstructionSchedule::factory()->usingGuideFile(SiteGuideFile::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('construction-sites.show', $guideFile))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('guideFile.schedules_count', 1)
            ->has('usageSchedules', 1)
            ->where('usageSchedules.0.id', $schedule->id)
            ->where('usageSchedules.0.title', '品川タワー')
            ->where('usageSchedules.0.scheduled_on', '2026-06-01')
        );
});

test('the site guide library is an accepted return target for a schedule edit', function (): void {
    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create();
    $schedule = ConstructionSchedule::factory()->usingGuideFile($guideFile)->create();
    $returnTo = '/construction-sites?search='.rawurlencode('渋谷');

    // The dialog's 編集ページへ button carries the library URL through, so the
    // edit page has to both echo it back and honour it on save.
    $this->actingAs($admin)
        ->get(route('construction-schedules.edit', [$schedule, 'return_to' => $returnTo]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->put(route('construction-schedules.update', [$schedule, 'return_to' => $returnTo]), [
            ...$schedule->only([
                'scheduled_on', 'starts_at', 'ends_at', 'status', 'meeting_place',
                'personnel', 'location', 'general_contractor', 'person_in_charge', 'content',
            ]),
        ])
        ->assertRedirect($returnTo);
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

    // Replacing the file behind a guide changes every schedule using it, so
    // the edit form is given the real count rather than a zero placeholder.
    ConstructionSchedule::factory()->count(2)->usingGuideFile($guideFile)->create();

    $this->actingAs($admin)
        ->get(route('construction-sites.edit', $guideFile))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('guideFile.id', $guideFile->id)
            ->where('guideFile.name', '案内図B.png')
            ->where('guideFile.schedules_count', 2)
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
        ->assertRedirect(route('construction-sites.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '現場案内図を追加しました。')
        ->assertInertiaFlash('toast.resource.type', 'site_guide_file')
        ->assertInertiaFlash('toast.resource.action', 'created')
        ->assertInertiaFlash('toast.resource.label', '横浜駅前_搬入口');

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
        ->assertRedirect(route('construction-sites.show', $guideFile))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '現場案内図を修正しました。')
        ->assertInertiaFlash('toast.resource.type', 'site_guide_file')
        ->assertInertiaFlash('toast.resource.id', $guideFile->id)
        ->assertInertiaFlash('toast.resource.action', 'updated')
        ->assertInertiaFlash('toast.resource.label', '名古屋駅前_追加案内図.pdf');

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

test('admins can delete standalone site guide files', function (): void {
    $admin = User::factory()->admin()->create();
    $guideFile = SiteGuideFile::factory()->create();

    $this->actingAs($admin)
        ->delete(route('construction-sites.destroy', $guideFile))
        ->assertRedirect(route('construction-sites.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '現場案内図を削除しました。');

    $this->assertModelMissing($guideFile);
});

test('deleting a site guide file removes it from storage', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $path = UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf')->store('site-guides', 'local');
    $guideFile = SiteGuideFile::factory()->create(['path' => $path]);

    $this->actingAs($admin)
        ->delete(route('construction-sites.destroy', $guideFile))
        ->assertRedirect(route('construction-sites.index'));

    $this->assertModelMissing($guideFile);
    Storage::disk('local')->assertMissing($path);
});

test('replacing a site guide file removes the old file from storage', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $oldPath = UploadedFile::fake()->create('old.pdf', 100, 'application/pdf')->store('site-guides', 'local');
    $guideFile = SiteGuideFile::factory()->create(['path' => $oldPath]);

    $this->actingAs($admin)
        ->put(route('construction-sites.update', $guideFile), [
            'name' => '差し替え案内図',
            'guide_file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('construction-sites.show', $guideFile));

    $guideFile->refresh();
    Storage::disk('local')->assertExists($guideFile->path);
    Storage::disk('local')->assertMissing($oldPath);
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
