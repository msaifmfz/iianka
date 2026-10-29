<?php

declare(strict_types=1);

use App\Http\Requests\StoreConstructionScheduleRequest;
use App\Http\Requests\UpdateConstructionScheduleRequest;
use App\Models\ConstructionSchedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

mutates(StoreConstructionScheduleRequest::class, UpdateConstructionScheduleRequest::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function constructionValidationPayload(array $overrides = []): array
{
    return [
        'scheduled_on' => '2026-11-02',
        'schedule_number' => 5,
        'starts_at' => '09:00',
        'ends_at' => '10:00',
        'status' => ConstructionSchedule::STATUS_SCHEDULED,
        'location' => '検証対象の現場',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function blankConstructionValidationPayload(): array
{
    return constructionValidationPayload([
        'schedule_number' => '',
        'starts_at' => '',
        'ends_at' => '',
        'time_note' => '',
        'meeting_place' => '',
        'personnel' => '',
        'site_region' => '',
        'general_contractor' => '',
        'person_in_charge' => '',
        'content' => '',
        'carry_out_note' => '',
        'navigation_address' => '',
    ]);
}

dataset('invalid construction schedule input', [
    'missing required fields' => [
        ['scheduled_on' => null, 'status' => null, 'location' => null],
        ['scheduled_on' => '予定日は必須項目です。', 'status' => 'ステータスは必須項目です。', 'location' => '現場名は必須項目です。'],
    ],
    'invalid date' => [['scheduled_on' => 'not-a-date'], ['scheduled_on' => '予定日は、正しい日付ではありません。']],
    'zero schedule number' => [['schedule_number' => 0], ['schedule_number' => '予定番号には、1以上の数値を指定してください。']],
    'fractional schedule number' => [['schedule_number' => 1.5], ['schedule_number' => '予定番号には、整数を指定してください。']],
    'invalid start time' => [['starts_at' => '25:00'], ['starts_at' => "開始時間の形式が'H:i'と一致しません。"]],
    'end before start' => [['ends_at' => '08:00'], ['ends_at' => '終了時間には、開始時間より後の日付を指定してください。']],
    'invalid status' => [['status' => 'draft'], ['status' => '選択されたステータスは、有効ではありません。']],
    'invalid prefecture' => [['site_region' => '架空県'], ['site_region' => '選択された現場地域は、有効ではありません。']],
    'unknown assignee' => [['assigned_user_ids' => [999_999]], ['assigned_user_ids.0' => '選択されたスタッフは、有効ではありません。']],
    'meeting place too long' => [['meeting_place' => str_repeat('あ', 256)], ['meeting_place' => '集合場所の文字数は、255文字以下である必要があります。']],
    'time note too long' => [['time_note' => str_repeat('あ', 256)], ['time_note' => '時間備考の文字数は、255文字以下である必要があります。']],
]);

test('invalid construction schedule creation returns validation messages without saving a schedule', function (
    array $invalidFields,
    array $errors,
): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('construction-schedules.create'))
        ->post(route('construction-schedules.store'), constructionValidationPayload($invalidFields))
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors($errors);

    $this->assertDatabaseCount('construction_schedules', 0);
    $this->assertDatabaseCount('site_guide_files', 0);
})->with('invalid construction schedule input');

test('invalid construction schedule updates return validation messages without changing the schedule', function (
    array $invalidFields,
    array $errors,
): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create(['location' => '変更前の現場']);
    $original = $schedule->refresh()->getRawOriginal();

    $this->actingAs($admin)
        ->from(route('construction-schedules.edit', $schedule))
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload($invalidFields))
        ->assertRedirect(route('construction-schedules.edit', $schedule))
        ->assertSessionHasErrors($errors);

    expect($schedule->refresh()->getRawOriginal())->toBe($original);
    $this->assertDatabaseCount('construction_schedules', 1);
    $this->assertDatabaseCount('site_guide_files', 0);
})->with('invalid construction schedule input');

test('updating a construction schedule rejects overlapping assigned staff times', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $existing = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create([
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'location' => '既存工事',
    ]);
    $existing->assignedUsers()->attach($worker);
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create([
        'starts_at' => '13:00',
        'ends_at' => '14:00',
    ]);
    $schedule->assignedUsers()->attach($worker);
    $original = $schedule->refresh()->getRawOriginal();

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload([
            'starts_at' => '10:30',
            'ends_at' => '12:00',
            'assigned_user_ids' => [$worker->id],
        ]))
        ->assertSessionHasErrors([
            'starts_at' => '選択したスタッフは 09:00 - 11:00 に 既存工事 の予定があります。重ならない開始時間・終了時間を選択してください。',
        ]);

    expect($schedule->refresh()->getRawOriginal())->toBe($original);
    expect($schedule->assignedUsers()->pluck('users.id')->all())->toBe([$worker->id]);
});

test('updating a construction schedule rejects a duplicate number for its existing assignees', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $existing = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create(['schedule_number' => 5]);
    $existing->assignedUsers()->attach($worker);
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create(['schedule_number' => 6]);
    $schedule->assignedUsers()->attach($worker);
    $original = $schedule->refresh()->getRawOriginal();

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload())
        ->assertSessionHasErrors([
            'schedule_number' => '同じ日付に同じ番号の予定が既にあります。別の番号を入力してください。',
        ]);

    expect($schedule->refresh()->getRawOriginal())->toBe($original);
    expect($schedule->assignedUsers()->pluck('users.id')->all())->toBe([$worker->id]);
});

test('updating a construction schedule rejects guide files without display names before storing files', function (
    int $fileCount,
    array $names,
    string $errorField,
): void {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create();
    $original = $schedule->refresh()->getRawOriginal();
    $files = [];

    foreach (range(1, $fileCount) as $index) {
        $files[] = UploadedFile::fake()->create("guide-{$index}.pdf", 10, 'application/pdf');
    }

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload([
            'guide_files' => $files,
            'guide_file_names' => $names,
        ]))
        ->assertSessionHasErrors([$errorField => '表示名を入力してください。']);

    expect($schedule->refresh()->getRawOriginal())->toBe($original);
    $this->assertDatabaseCount('site_guide_files', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'missing name' => [1, [], 'guide_file_names.0'],
    'whitespace name' => [1, ['   '], 'guide_file_names.0'],
    'missing second name' => [2, ['最初の案内図'], 'guide_file_names.1'],
]);

test('a construction schedule can retain its own time and number when updated', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create([
        'schedule_number' => 5,
        'starts_at' => '09:00',
        'ends_at' => '10:00',
    ]);
    $schedule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload([
            'assigned_user_ids' => [$worker->id],
            'location' => '時間と番号を保持した現場',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('construction_schedules', [
        'id' => $schedule->id,
        'schedule_number' => 5,
        'starts_at' => '09:00',
        'ends_at' => '10:00',
        'location' => '時間と番号を保持した現場',
    ]);
    expect($schedule->assignedUsers()->pluck('users.id')->all())->toBe([$worker->id]);
});

test('construction schedule updates normalize optional text and new subcontractor details', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create();

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), constructionValidationPayload([
            'general_contractor' => '  和城建設  ',
            'meeting_place' => '  正門  ',
            'site_region' => '  東京都  ',
            'content' => '  現場の確認  ',
            'carry_out_note' => '  ',
            'navigation_address' => '  東京都千代田区  ',
            'new_subcontractors' => [['name' => '  協力会社  ', 'phone' => '  03-1234-5678  ']],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('construction_schedules', [
        'id' => $schedule->id,
        'general_contractor' => '和城建設',
        'meeting_place' => '正門',
        'site_region' => '東京都',
        'content' => '現場の確認',
        'carry_out_note' => null,
        'navigation_address' => '東京都千代田区',
    ]);
    $this->assertDatabaseHas('construction_subcontractors', ['name' => '協力会社', 'phone' => '03-1234-5678']);
    expect($schedule->subcontractors()->pluck('name')->all())->toBe(['協力会社']);
});

test('construction schedules can be created with blank optional fields', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), blankConstructionValidationPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('construction_schedules', 1);
    $this->assertDatabaseHas('construction_schedules', [
        'location' => '検証対象の現場',
        'schedule_number' => null,
        'starts_at' => null,
        'ends_at' => null,
        'time_note' => null,
        'meeting_place' => null,
        'personnel' => null,
        'site_region' => null,
        'general_contractor' => null,
        'person_in_charge' => null,
        'content' => null,
        'carry_out_note' => null,
        'navigation_address' => null,
    ]);
});

test('construction schedule updates can clear previously populated optional fields', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->scheduledOn('2026-11-02')->create([
        'schedule_number' => 5,
        'site_region' => '東京都',
        'time_note' => '午前',
        'carry_out_note' => '搬出あり',
    ]);

    $this->actingAs($admin)
        ->patch(route('construction-schedules.update', $schedule), blankConstructionValidationPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('construction_schedules', [
        'id' => $schedule->id,
        'schedule_number' => null,
        'starts_at' => null,
        'ends_at' => null,
        'time_note' => null,
        'meeting_place' => null,
        'personnel' => null,
        'site_region' => null,
        'general_contractor' => null,
        'person_in_charge' => null,
        'content' => null,
        'carry_out_note' => null,
        'navigation_address' => null,
    ]);
});
