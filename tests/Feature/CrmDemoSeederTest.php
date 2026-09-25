<?php

use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use App\Http\Controllers\CrmMapController;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDocument;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use Database\Seeders\CrmDemoSeeder;
use Illuminate\Support\Facades\Storage;

test('CRM demo data is realistic, repeatable and preserves existing clients', function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
    $existing = Client::factory()->create(['note' => 'Keep me']);
    $this->seed(CrmDemoSeeder::class);
    $this->seed(CrmDemoSeeder::class);

    expect(Client::query()->count())->toBe(11)
        ->and(ClientContact::query()->count())->toBe(11)
        ->and(ClientPlace::query()->count())->toBe(15)
        ->and(ClientPlace::query()->whereNotNull('archived_at')->count())->toBe(3)
        ->and(ClientPlaceLog::query()->count())->toBe(14)
        ->and(ClientPlaceLogAttachment::query()->count())->toBe(9)
        ->and(ClientDocument::query()->count())->toBe(8)
        ->and(ClientPlace::query()->whereNull('last_logged_at')->count())->toBe(6)
        ->and(ClientPlaceLog::query()->whereNotNull('user_id')->count())->toBe(13)
        ->and(ClientPlaceLog::query()->whereNull('user_id')->count())->toBe(1)
        ->and(ClientPlaceLog::query()->distinct('type')->count('type'))->toBe(4)
        ->and(ClientPlaceLog::query()->pluck('reaction')->unique()->count())->toBe(4)
        ->and(ClientPlace::query()->where('lat', 34.7025)->where('lng', 135.4959)->count())->toBe(2)
        ->and($existing->refresh()->note)->toBe('Keep me');

    $emptyClient = Client::query()->where('name', '【デモ】空地点テスト商事')->sole();
    $contactlessClient = Client::query()->where('name', '【デモ】堺ベイエリア工業株式会社')->sole();
    $archivedOnlyClient = Client::query()->where('name', '【デモ】尼崎つばさ建装株式会社')->sole();

    expect($emptyClient->places()->count())->toBe(0)
        ->and($contactlessClient->contacts()->count())->toBe(0)
        ->and($archivedOnlyClient->places()->whereNull('archived_at')->count())->toBe(0);

    foreach ([...ClientPlaceLogAttachment::query()->pluck('path'), ...ClientDocument::query()->pluck('path')] as $path) {
        Storage::disk(ClientPlaceLogAttachment::DISK)->assertExists($path);
    }
});

test('CRM demo documents cover the panel limit and the upload variations', function (): void {
    Storage::fake(ClientDocument::DISK);
    $this->seed(CrmDemoSeeder::class);

    $documents = ClientDocument::query()->get();
    $busiestClientCount = $documents->groupBy('client_id')->map->count()->max();

    expect($busiestClientCount)->toBeGreaterThan(CrmMapController::RECENT_DOCUMENT_LIMIT)
        ->and($documents->whereNull('client_place_id')->count())->toBeGreaterThan(0)
        ->and($documents->whereNotNull('client_place_id')->count())->toBeGreaterThan(0)
        ->and($documents->whereNull('issued_on')->count())->toBeGreaterThan(0)
        ->and($documents->whereNull('uploaded_by_user_id')->count())->toBeGreaterThan(0)
        ->and($documents->pluck('extension')->unique()->sort()->values()->all())->toBe(['csv', 'jpg', 'pdf', 'txt'])
        ->and(ClientPlaceLogAttachment::query()->where('kind', ClientPlaceLogAttachmentKind::Document)->count())->toBe(2);

    // Demo PDFs must be real files the browser viewer can open.
    foreach ($documents->where('extension', 'pdf') as $pdf) {
        $contents = (string) Storage::disk(ClientDocument::DISK)->get($pdf->path);

        expect($contents)->toStartWith('%PDF-')->toContain('startxref')
            ->and(new finfo(FILEINFO_MIME_TYPE)->buffer($contents))->toBe('application/pdf');
    }
});

test('CRM demo data covers the layout and formatting extremes', function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
    $this->seed(CrmDemoSeeder::class);

    $sameMinute = ClientPlaceLog::query()
        ->select('occurred_at')
        ->groupBy('occurred_at')
        ->havingRaw('count(*) > 1')
        ->count();

    // Lengths are measured in PHP: length() counts bytes on MySQL and
    // characters on SQLite, and every one of these strings is multi-byte.
    $longest = fn (iterable $values): int => max(array_map(mb_strlen(...), [...$values, '']));

    expect(Client::query()->whereNull('note')->count())->toBeGreaterThan(0)
        // short_label is capped at three characters; the demo uses all three.
        ->and($longest(Client::query()->pluck('short_label')))->toBe(3)
        ->and($longest(Client::query()->pluck('name')))->toBeGreaterThan(20)
        ->and($longest(ClientPlace::query()->pluck('name')))->toBeGreaterThan(30)
        ->and($longest(ClientPlaceLog::query()->pluck('summary')))->toBeGreaterThan(300)
        ->and(ClientPlace::query()->whereNull('address')->count())->toBeGreaterThan(0)
        ->and(ClientPlaceLog::query()->where('summary', 'like', "%\n%")->count())->toBeGreaterThan(0)
        // Two entries at one timestamp exercise the latest('id') tiebreak.
        ->and($sameMinute)->toBeGreaterThan(0);

    $attachments = ClientPlaceLogAttachment::query()->get();

    expect($attachments->where('kind', ClientPlaceLogAttachmentKind::Audio)->whereNull('duration_seconds')->count())->toBeGreaterThan(0)
        ->and($attachments->where('kind', ClientPlaceLogAttachmentKind::Audio)->whereNotNull('duration_seconds')->count())->toBeGreaterThan(0)
        ->and($attachments->where('kind', ClientPlaceLogAttachmentKind::Image)->pluck('extension')->unique()->sort()->values()->all())
        ->toBe(['jpg', 'png', 'webp'])
        // One entry carries several photos so the grid wraps.
        ->and($attachments->where('kind', ClientPlaceLogAttachmentKind::Image)->groupBy('client_place_log_id')->map->count()->max())
        ->toBeGreaterThanOrEqual(3);

    // The seeded photos must be real decodable images, not placeholders.
    foreach ($attachments->where('kind', ClientPlaceLogAttachmentKind::Image) as $image) {
        $contents = Storage::disk(ClientPlaceLogAttachment::DISK)->get($image->path);

        expect(getimagesizefromstring((string) $contents))->not->toBeFalse();
    }
});

test('re-seeding repairs a missing demo file but keeps one that was replaced', function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
    $this->seed(CrmDemoSeeder::class);
    $disk = Storage::disk(ClientPlaceLogAttachment::DISK);
    [$missing, $replaced] = ClientPlaceLogAttachment::query()->limit(2)->pluck('path')->all();
    $disk->delete($missing);
    $disk->put($replaced, 'a photo swapped in by hand');

    $this->seed(CrmDemoSeeder::class);

    $disk->assertExists($missing);
    expect($disk->get($replaced))->toBe('a photo swapped in by hand');
});

test('CRM demo seeding enforces the environment guard even with force', function (string $environment, bool $allowed): void {
    app()->instance('env', $environment);

    try {
        if ($allowed) {
            $this->artisan('db:seed', ['--class' => CrmDemoSeeder::class, '--force' => true])->assertSuccessful();
            expect(Client::query()->count())->toBe(10);
        } else {
            expect(fn () => $this->artisan('db:seed', ['--class' => CrmDemoSeeder::class, '--force' => true])->run())
                ->toThrow(LogicException::class, 'local, testing or staging');
            expect(Client::query()->count())->toBe(0);
        }
    } finally {
        app()->instance('env', 'testing');
    }
})->with([
    'production is forbidden' => ['production', false],
    'staging is allowed' => ['staging', true],
    'unknown environment is forbidden' => ['preview', false],
]);

test('CRM demo seeding does not restore deleted demo clients', function (): void {
    $this->seed(CrmDemoSeeder::class);
    $client = Client::query()->firstOrFail();
    $client->delete();
    $this->seed(CrmDemoSeeder::class);

    expect($client->refresh()->trashed())->toBeTrue()
        ->and(Client::withTrashed()->count())->toBe(10);
});
