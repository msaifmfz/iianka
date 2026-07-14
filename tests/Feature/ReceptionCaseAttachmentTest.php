<?php

use App\Models\AuditLog;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\ReceptionCaseAttachment;
use App\Models\User;
use App\ReceptionCaseAttachmentSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('reception draft owners can upload attachments', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('contract.pdf', 256, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
            'name' => '契約書',
        ])
        ->assertCreated()
        ->assertJsonPath('attachment.name', '契約書')
        ->assertJsonPath('attachment.kind', 'document')
        ->assertJsonPath('attachment.preview_mode', 'pdf');

    $attachment = ReceptionCaseAttachment::query()->findOrFail($response->json('attachment.id'));

    Storage::disk('local')->assertExists($attachment->path);

    expect($attachment->reception_case_id)->toBe($case->id)
        ->and($attachment->uploaded_by_user_id)->toBe($user->id)
        ->and($case->activities()->where('type', ReceptionCaseActivity::TYPE_ATTACHMENT_ADDED)->exists())->toBeFalse();
});

test('draft attachments stay private to the draft owner', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'path' => 'reception-attachments/private.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'private draft contents');

    $this->actingAs($otherUser)
        ->get(route('reception.attachments.show', $attachment))
        ->assertForbidden();

    $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->create('other.pdf', 64, 'application/pdf'),
        'source' => ReceptionCaseAttachmentSource::Upload->value,
    ])->assertForbidden();
});

test('submitted case attachments follow case visibility', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'name' => 'received.pdf',
        'path' => 'reception-attachments/received.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'received contents');

    $this->actingAs($viewer)
        ->get(route('reception.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=received.pdf')
        ->assertHeader('x-content-type-options', 'nosniff');
});

test('downloads carry an rfc-compliant utf-8 filename with the stored extension', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
            'name' => '契約書', // the UI sends a display name without the extension
        ])
        ->assertCreated();

    $attachment = ReceptionCaseAttachment::query()->findOrFail($response->json('attachment.id'));

    expect($attachment->name)->toBe('契約書');

    $disposition = $this->get(route('reception.attachments.show', [$attachment, 'download' => 1]))
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)
        ->toStartWith('attachment;')
        ->toContain("filename*=utf-8''")
        ->toContain('.pdf');
});

test('inline attachment views are not audited but explicit downloads are', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'name' => 'audit.pdf',
        'path' => 'reception-attachments/audit.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'audit contents');

    $this->actingAs($owner)
        ->get(route('reception.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=audit.pdf');

    expect(AuditLog::query()->where('event', 'reception_case_attachments.downloaded')->count())->toBe(0);

    $this->get(route('reception.attachments.show', [$attachment, 'download' => 1]))
        ->assertOk();

    expect(AuditLog::query()->where('event', 'reception_case_attachments.downloaded')->count())->toBe(1);
});

test('download only attachments force downloads and audit direct access', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'name' => 'notes',
        'path' => 'reception-attachments/notes.txt',
        'mime_type' => 'text/plain',
        'extension' => 'txt',
    ]);

    Storage::disk('local')->put($attachment->path, 'download only notes');

    $disposition = $this->actingAs($owner)
        ->get(route('reception.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->headers->get('content-disposition');

    expect($disposition)->toStartWith('attachment;');
    expect(AuditLog::query()->where('event', 'reception_case_attachments.downloaded')->count())->toBe(1);
});

test('a missing stored file returns 404', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'path' => 'reception-attachments/never-written.pdf',
    ]);

    $this->actingAs($owner)
        ->get(route('reception.attachments.show', $attachment))
        ->assertNotFound();
});

test('names containing path separators still serve with a safe filename', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->recording()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'name' => '録音 2026/07/03 14:30',
        'path' => 'reception-attachments/recording.webm',
    ]);

    Storage::disk('local')->put($attachment->path, 'recording contents');

    $disposition = $this->actingAs($owner)
        ->get(route('reception.attachments.show', $attachment))
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)->toStartWith('inline;');
    expect($disposition)->not->toContain('/');
    expect($disposition)->not->toContain('\\');
});

test('active case attachment changes record activity', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
        'last_activity_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('photo.jpg', 64, 'image/jpeg'),
            'source' => ReceptionCaseAttachmentSource::Capture->value,
            'name' => '現地写真',
        ])
        ->assertCreated();

    $attachment = ReceptionCaseAttachment::query()->findOrFail($response->json('attachment.id'));

    expect($case->refresh()->activities()->where('type', ReceptionCaseActivity::TYPE_ATTACHMENT_ADDED)->exists())->toBeTrue()
        ->and($case->last_activity_at)->not->toBeNull();

    $this->delete(route('reception.attachments.destroy', $attachment))
        ->assertOk()
        ->assertJsonPath('deleted_id', $attachment->id);

    expect($case->refresh()->activities()->where('type', ReceptionCaseActivity::TYPE_ATTACHMENT_DELETED)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($attachment->path);
});

test('unsafe files and extension mismatches are rejected', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('contract.jpg', 64, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
        ])
        ->assertSessionHasErrors('file');

    $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
        'source' => ReceptionCaseAttachmentSource::Upload->value,
    ])->assertSessionHasErrors('file');

    $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->create('clip.mov', 64, 'application/pdf'),
        'source' => ReceptionCaseAttachmentSource::Upload->value,
    ])->assertSessionHasErrors('file');

    $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->create('browser-recording.m4a', 64, 'video/mp4'),
        'source' => ReceptionCaseAttachmentSource::Upload->value,
    ])->assertSessionHasErrors('file');

    expect($case->attachments()->count())->toBe(0);
});

test('invalid mobile camera uploads return validation errors instead of crashing', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => new UploadedFile('', 'camera-photo.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
        ])
        ->assertSessionHasErrors('file');

    expect($case->attachments()->count())->toBe(0);
});

test('smartphone camera photo and video uploads are accepted', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    foreach ([
        ['iphone-photo.jpg', 'image/jpeg', 'image', 'image'],
        ['iphone-photo.heic', 'image/heic', 'image', 'download'],
        ['android-photo.heif', 'image/heif', 'image', 'download'],
        ['iphone-video.mov', 'video/quicktime', 'video', 'download'],
        ['android-video.mp4', 'video/mp4', 'video', 'video'],
        ['iphone-video.m4v', 'video/mp4', 'video', 'video'],
        ['android-video.3gp', 'video/3gpp', 'video', 'download'],
        ['android-video.3gpp', 'video/3gpp', 'video', 'download'],
        ['android-video.3g2', 'video/3gpp2', 'video', 'download'],
        ['browser-video.webm', 'video/webm', 'video', 'video'],
    ] as [$filename, $mimeType, $kind, $previewMode]) {
        $response = $this->actingAs($owner)
            ->post(route('reception.cases.attachments.store', $case), [
                'file' => UploadedFile::fake()->create($filename, 64, $mimeType),
                'source' => ReceptionCaseAttachmentSource::Upload->value,
            ])
            ->assertCreated()
            ->assertJsonPath('attachment.kind', $kind)
            ->assertJsonPath('attachment.preview_mode', $previewMode);

        $attachment = ReceptionCaseAttachment::query()->findOrFail($response->json('attachment.id'));

        Storage::disk('local')->assertExists($attachment->path);
    }

    expect($case->attachments()->count())->toBe(10);
});

test('browser recording container mime types are accepted across platforms', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    foreach ([
        ['voice-webm-video.webm', 'video/webm'],
        ['voice-webm-audio.webm', 'audio/webm'],
        ['voice-m4a-video.m4a', 'video/mp4'],
        ['voice-m4a-audio.m4a', 'audio/mp4'],
        ['voice-ogg-audio.ogg', 'audio/ogg'],
        ['voice-ogg-application.ogg', 'application/ogg'],
    ] as [$filename, $mimeType]) {
        $this->actingAs($owner)
            ->post(route('reception.cases.attachments.store', $case), [
                'file' => UploadedFile::fake()->create($filename, 64, $mimeType),
                'source' => ReceptionCaseAttachmentSource::Recording->value,
                'duration_seconds' => 60,
            ])
            ->assertCreated()
            ->assertJsonPath('attachment.kind', 'audio')
            ->assertJsonPath('attachment.preview_mode', 'audio');
    }

    expect($case->attachments()->count())->toBe(6);
});

test('completed cases do not accept attachment changes', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->completed()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'path' => 'reception-attachments/completed.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'completed contents');

    $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('after.pdf', 64, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
        ])
        ->assertForbidden();

    $this->delete(route('reception.attachments.destroy', $attachment))
        ->assertForbidden();
});

test('attachment count and recording count are limited', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    ReceptionCaseAttachment::factory()
        ->count(ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE)
        ->create(['reception_case_id' => $case->id]);

    $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('too-many.pdf', 64, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
        ])
        ->assertSessionHasErrors('file');

    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    ReceptionCaseAttachment::factory()
        ->recording()
        ->count(ReceptionCaseAttachment::MAX_RECORDINGS_PER_CASE)
        ->create(['reception_case_id' => $case->id]);

    $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->create('voice.webm', 64, 'audio/webm'),
        'source' => ReceptionCaseAttachmentSource::Recording->value,
        'duration_seconds' => 60,
    ])->assertSessionHasErrors('file');
});

test('recordings are capped at ten minutes', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('voice.webm', 64, 'audio/webm'),
            'source' => ReceptionCaseAttachmentSource::Recording->value,
            'duration_seconds' => ReceptionCaseAttachment::MAX_RECORDING_SECONDS + 1,
        ])
        ->assertSessionHasErrors('duration_seconds');
});

test('deleting a draft removes stored attachment files', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $owner->id,
    ]);
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'disk' => 'local',
        'path' => 'reception-attachments/draft.pdf',
    ]);

    Storage::disk('local')->put($attachment->path, 'draft contents');

    $this->actingAs($owner)
        ->delete(route('reception.cases.destroy-draft', $case))
        ->assertRedirect(route('reception.home'));

    Storage::disk('local')->assertMissing($attachment->path);
});

test('case detail presents attachments', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $owner->id,
    ]);
    ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'uploaded_by_user_id' => $owner->id,
        'name' => '添付テスト.pdf',
    ]);

    $this->actingAs($owner)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.attachments.0.name', '添付テスト.pdf')
            ->where('caseData.attachments.0.kind', 'document')
            ->where('caseData.attachments.0.preview_mode', 'pdf')
            ->has('caseData.attachments.0.download_url')
            ->where('attachmentConstraints.max_attachments', ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE)
        );
});

test('the assigned worker can manage attachments without editing the case fields', function (): void {
    Storage::fake('local');

    $receptor = User::factory()->create();
    $worker = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($worker)->create([
        'receptor_user_id' => $receptor->id,
    ]);

    $this->actingAs($worker)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.can.update', false)
            ->where('caseData.can.attach_files', true)
        );

    $response = $this->post(route('reception.cases.attachments.store', $case), [
        'file' => UploadedFile::fake()->create('worker.pdf', 64, 'application/pdf'),
        'source' => ReceptionCaseAttachmentSource::Upload->value,
    ])->assertCreated();

    $attachment = ReceptionCaseAttachment::query()->findOrFail($response->json('attachment.id'));

    $this->delete(route('reception.attachments.destroy', $attachment))
        ->assertOk()
        ->assertJsonPath('deleted_id', $attachment->id);
});

test('a non-assigned viewer cannot manage attachments on an active case', function (): void {
    Storage::fake('local');

    $worker = User::factory()->create();
    $viewer = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($worker)->create();
    $attachment = ReceptionCaseAttachment::factory()->create([
        'reception_case_id' => $case->id,
        'disk' => 'local',
        'path' => 'reception-attachments/viewer.pdf',
    ]);

    $this->actingAs($viewer)
        ->post(route('reception.cases.attachments.store', $case), [
            'file' => UploadedFile::fake()->create('nope.pdf', 64, 'application/pdf'),
            'source' => ReceptionCaseAttachmentSource::Upload->value,
        ])
        ->assertForbidden();

    $this->delete(route('reception.attachments.destroy', $attachment))
        ->assertForbidden();
});
