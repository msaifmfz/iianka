<?php

use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use App\Domain\Crm\Enums\ClientReaction;
use App\Models\AuditLog;
use App\Models\ClientContact;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
    $this->travelTo('2026-09-19 06:00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function logPayload(array $overrides = []): array
{
    return [
        'type' => 'visit',
        'occurred_at' => '2026-09-19T10:30',
        'summary' => '新規案件の見積もり依頼。来月着工予定。',
        'reaction' => 'positive',
        'client_contact_id' => '',
        ...$overrides,
    ];
}

test('any signed-in user logs a visit, read in Tokyo time and stored in UTC', function (): void {
    $viewer = User::factory()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($viewer)
        ->post(route('crm.places.logs.store', $place), logPayload())
        ->assertRedirect();

    $log = ClientPlaceLog::query()->sole();

    expect($log->user->is($viewer))->toBeTrue()
        ->and($log->reaction)->toBe(ClientReaction::Positive)
        ->and($log->occurred_at->toDateTimeString())->toBe('2026-09-19 01:30:00')
        ->and($place->refresh()->last_logged_at?->toDateTimeString())->toBe('2026-09-19 01:30:00')
        ->and(AuditLog::query()->where('event', 'client_place_logs.created')->exists())->toBeTrue();
});

test('a log carries photos and voice memos in the same request', function (): void {
    $place = ClientPlace::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [
                ['file' => UploadedFile::fake()->image('site.jpg')],
                ['file' => UploadedFile::fake()->create('memo.m4a', 20, 'audio/mp4'), 'duration_seconds' => 42],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $attachments = ClientPlaceLog::query()->sole()->attachments()->orderBy('id')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0]->kind)->toBe(ClientPlaceLogAttachmentKind::Image)
        ->and($attachments[0]->name)->toBe('site')
        ->and($attachments[1]->kind)->toBe(ClientPlaceLogAttachmentKind::Audio)
        ->and($attachments[1]->duration_seconds)->toBe(42);

    Storage::disk(ClientPlaceLogAttachment::DISK)->assertExists($attachments->pluck('path')->all());
});

test('a file that is not really an image or audio is rejected whatever its extension', function (): void {
    $place = ClientPlace::factory()->create();
    // A real file, not UploadedFile::fake(): fakes report a MIME type from
    // the name, while real uploads are sniffed from their content.
    $path = tempnam(sys_get_temp_dir(), 'crm');
    file_put_contents($path, '<html><body><script>alert(1)</script></body></html>');
    $disguised = new UploadedFile($path, 'photo.jpg', null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $disguised]],
        ]))
        ->assertSessionHasErrors('attachments.0.file');

    expect(ClientPlaceLog::query()->count())->toBe(0);
    expect(Storage::disk(ClientPlaceLogAttachment::DISK)->allFiles())->toBe([]);
});

test('an SVG disguised as a photo is rejected', function (): void {
    $place = ClientPlace::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'crm');
    file_put_contents($path, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $disguised = new UploadedFile($path, 'photo.png', null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $disguised]],
        ]))
        ->assertSessionHasErrors('attachments.0.file');

    expect(ClientPlaceLog::query()->count())->toBe(0);
});

test('a photo whose content is audio is rejected', function (): void {
    $place = ClientPlace::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'crm');
    // Minimal WAV header: sniffed as audio, named as a photo.
    file_put_contents($path, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xac\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00data\x00\x00\x00\x00");
    $mismatched = new UploadedFile($path, 'photo.jpg', null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $mismatched]],
        ]))
        ->assertSessionHasErrors('attachments.0.file');
});

test('a real HEIC photo is accepted even when libmagic cannot name it', function (): void {
    // iPhones send HEIC and many libmagic builds report it as
    // application/octet-stream, so the ISO base media header has the say.
    $place = ClientPlace::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'crm');
    file_put_contents($path, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64));
    $heic = new UploadedFile($path, 'IMG_0001.heic', null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $heic]],
        ]))
        ->assertSessionHasNoErrors();

    expect(ClientPlaceLogAttachment::query()->sole()->kind)->toBe(ClientPlaceLogAttachmentKind::Image);
});

test('an unknown binary wearing a HEIC name is still rejected', function (): void {
    $place = ClientPlace::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'crm');
    file_put_contents($path, 'MZ'.str_repeat("\x00", 128));
    $disguised = new UploadedFile($path, 'IMG_0002.heic', null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $disguised]],
        ]))
        ->assertSessionHasErrors('attachments.0.file');

    expect(ClientPlaceLog::query()->count())->toBe(0);
});

test('log input is validated', function (array $overrides, string $field): void {
    $place = ClientPlace::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing summary' => [['summary' => '  '], 'summary'],
    'unknown reaction' => [['reaction' => 'ecstatic'], 'reaction'],
    'unknown type' => [['type' => 'fax'], 'type'],
    'far future' => [['occurred_at' => '2026-12-31T10:00'], 'occurred_at'],
    'beyond the clock-skew grace' => [['occurred_at' => '2026-09-19T15:06'], 'occurred_at'],
    'not a datetime-local value' => [['occurred_at' => '2026-09-19 15:00'], 'occurred_at'],
    'invalid contact' => [['client_contact_id' => 'invalid'], 'client_contact_id'],
    'fractional contact' => [['client_contact_id' => '1.5'], 'client_contact_id'],
]);

test('active SVG content cannot be uploaded as a photo or recording', function (string $name): void {
    $place = ClientPlace::factory()->create();
    $source = UploadedFile::fake()->createWithContent($name, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>');
    $file = new UploadedFile($source->getPathname(), $name, null, null, true);

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload([
            'attachments' => [['file' => $file]],
        ]))
        ->assertSessionHasErrors('attachments.0.file');

    expect(ClientPlaceLog::query()->count())->toBe(0);
    expect(Storage::disk(ClientPlaceLogAttachment::DISK)->allFiles())->toBe([]);
})->with(['photo.jpg', 'recording.m4a']);

test('a few minutes of device clock skew still logs, further ahead does not', function (): void {
    // Time is frozen at 15:00 in Tokyo; a phone running a little fast should
    // not be able to lock a field user out of recording the visit.
    $place = ClientPlace::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crm.places.logs.store', $place), logPayload(['occurred_at' => '2026-09-19T15:04']))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('crm.places.logs.store', $place), logPayload([
            'summary' => '秒まで送ってくる端末からの記録。',
            'occurred_at' => '2026-09-19T14:30:45',
        ]))
        ->assertSessionHasNoErrors();

    expect(ClientPlaceLog::query()->count())->toBe(2)
        ->and(ClientPlaceLog::query()->orderBy('occurred_at')->first()?->occurred_at->toDateTimeString())
        ->toBe('2026-09-19 05:30:45');
});

test('the contact must belong to the place client', function (): void {
    $place = ClientPlace::factory()->create();
    $ownContact = ClientContact::factory()->for($place->client)->create();
    $otherContact = ClientContact::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload(['client_contact_id' => $otherContact->id]))
        ->assertSessionHasErrors('client_contact_id');

    $this->actingAs(User::factory()->create())
        ->post(route('crm.places.logs.store', $place), logPayload(['client_contact_id' => $ownContact->id]))
        ->assertSessionHasNoErrors();

    expect(ClientPlaceLog::query()->sole()->contact->is($ownContact))->toBeTrue();
});

test('only the author or an admin edits or deletes a log', function (): void {
    $author = User::factory()->create();
    $colleague = User::factory()->editor()->create();
    $admin = User::factory()->admin()->create();
    $log = ClientPlaceLog::factory()->for($author)->create();

    $this->actingAs($colleague)->patch(route('crm.logs.update', $log), logPayload())->assertForbidden();
    $this->actingAs($colleague)->delete(route('crm.logs.destroy', $log))->assertForbidden();

    $this->actingAs($author)
        ->post(route('crm.logs.update', $log), logPayload(['_method' => 'patch', 'summary' => '修正済み']))
        ->assertRedirect();
    expect($log->refresh()->summary)->toBe('修正済み');

    $this->actingAs($admin)->delete(route('crm.logs.destroy', $log))->assertRedirect();
    expect(ClientPlaceLog::query()->count())->toBe(0);
});

test('re-dating or deleting a log keeps the place freshness right', function (): void {
    $author = User::factory()->create();
    $place = ClientPlace::factory()->create();
    $older = ClientPlaceLog::factory()->for($place, 'place')->for($author)->create(['occurred_at' => '2026-06-01 00:00:00']);
    $newer = ClientPlaceLog::factory()->for($place, 'place')->for($author)->create(['occurred_at' => '2026-09-01 00:00:00']);
    $place->refreshLastLoggedAt();

    $this->actingAs($author)->delete(route('crm.logs.destroy', $newer))->assertRedirect();
    expect($place->refresh()->last_logged_at?->toDateString())->toBe('2026-06-01');

    $this->actingAs($author)->delete(route('crm.logs.destroy', $older))->assertRedirect();
    expect($place->refresh()->last_logged_at)->toBeNull();
});

test('deleting a log removes its files', function (): void {
    $author = User::factory()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($author)->post(route('crm.places.logs.store', $place), logPayload([
        'attachments' => [['file' => UploadedFile::fake()->image('a.png')]],
    ]));

    $log = ClientPlaceLog::query()->sole();
    $path = $log->attachments()->sole()->path;
    Storage::disk(ClientPlaceLogAttachment::DISK)->assertExists($path);

    $this->actingAs($author)->delete(route('crm.logs.destroy', $log))->assertRedirect();

    Storage::disk(ClientPlaceLogAttachment::DISK)->assertMissing($path);
    expect(ClientPlaceLogAttachment::query()->count())->toBe(0);
});

test('any user views an attachment; only the log author deletes it', function (): void {
    $author = User::factory()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($author)->post(route('crm.places.logs.store', $place), logPayload([
        'attachments' => [['file' => UploadedFile::fake()->image('a.png')]],
    ]));

    $attachment = ClientPlaceLogAttachment::query()->sole();
    $colleague = User::factory()->create();

    $this->actingAs($colleague)
        ->get(route('crm.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'");

    app('auth')->forgetGuards();
    $this->get(route('crm.attachments.show', $attachment))->assertRedirect(route('login'));

    $this->actingAs($colleague)->delete(route('crm.attachments.destroy', $attachment))->assertForbidden();
    $this->actingAs($author)->delete(route('crm.attachments.destroy', $attachment))->assertRedirect();

    Storage::disk(ClientPlaceLogAttachment::DISK)->assertMissing($attachment->path);
});

test('editing a log adds attachments up to the limit', function (): void {
    $author = User::factory()->create();
    $log = ClientPlaceLog::factory()->for($author)->create();
    ClientPlaceLogAttachment::factory()->for($log, 'log')->count(ClientPlaceLogAttachment::MAX_PER_LOG)->create();

    $this->actingAs($author)
        ->post(route('crm.logs.update', $log), logPayload([
            '_method' => 'patch',
            'attachments' => [['file' => UploadedFile::fake()->image('one-more.jpg')]],
        ]))
        ->assertSessionHasErrors('attachments');
});
