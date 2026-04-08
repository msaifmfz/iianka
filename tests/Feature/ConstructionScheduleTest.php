<?php

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ConstructionSite;
use App\Models\GeneralContractor;
use App\Models\InternalNotice;
use App\Models\SiteGuideFile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('users can see their own and others schedules for today', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $mySchedule = ConstructionSchedule::factory()
        ->scheduledToday()
        ->create(['location' => '銀座ビル改修']);
    $mySchedule->assignedUsers()->attach($user);

    $teamSchedule = ConstructionSchedule::factory()
        ->scheduledToday()
        ->create(['location' => '渋谷駅前工事']);
    $teamSchedule->assignedUsers()->attach($otherUser);

    $this->actingAs($user)
        ->get(route('construction-schedules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '銀座ビル改修')
                ->etc()
            )
            ->has('teamSchedules', 2)
            ->where('teamSchedules', fn ($schedules): bool => collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['location'] === '銀座ビル改修'
            ) && collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['location'] === '渋谷駅前工事'
            ))
        );
});

test('schedules are ordered by number before time', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-04';

    $thirdSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 3,
        'starts_at' => '08:00',
        'location' => '3番の工事',
    ]);
    $firstSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 1,
        'starts_at' => '17:00',
        'location' => '1番の業務',
    ]);
    $secondSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 2,
        'starts_at' => '09:00',
        'location' => '2番の工事',
    ]);

    $thirdSchedule->assignedUsers()->attach($user);
    $firstSchedule->assignedUsers()->attach($user);
    $secondSchedule->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('mySchedules.0.location', '1番の業務')
            ->where('mySchedules.1.location', '2番の工事')
            ->where('mySchedules.2.location', '3番の工事')
            ->where('teamSchedules.0.schedule_number', 1)
            ->where('teamSchedules.1.schedule_number', 2)
            ->where('teamSchedules.2.schedule_number', 3)
        );
});

test('admin can filter calendar schedules by multiple users', function (): void {
    $admin = User::factory()->admin()->create();
    $firstWorker = User::factory()->create(['name' => '一番作業員']);
    $secondWorker = User::factory()->create(['name' => '二番作業員']);
    $hiddenWorker = User::factory()->create(['name' => '非表示作業員']);
    $date = '2026-05-04';

    $firstSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 2,
        'location' => '一番作業員の工事',
    ]);
    $firstSchedule->assignedUsers()->attach($firstWorker);

    $secondSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 1,
        'location' => '二番作業員の業務',
    ]);
    $secondSchedule->assignedUsers()->attach($secondWorker);

    $hiddenSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 3,
        'location' => '非表示の工事',
    ]);
    $hiddenSchedule->assignedUsers()->attach($hiddenWorker);

    $this->actingAs($admin)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
            'user_ids' => [$firstWorker->id, $secondWorker->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.user_ids', [$firstWorker->id, $secondWorker->id])
            ->has('userOptions', 4)
            ->has('selectedUserSchedules', 2)
            ->where('selectedUserSchedules.0.location', '二番作業員の業務')
            ->where('selectedUserSchedules.1.location', '一番作業員の工事')
            ->where('selectedUserSchedules', fn ($schedules): bool => ! collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['location'] === '非表示の工事'
            ))
        );
});

test('users can browse schedules in the requested month', function (): void {
    $user = User::factory()->create();
    $currentMonthSchedule = ConstructionSchedule::factory()
        ->scheduledToday()
        ->create(['location' => '今月の現場']);
    $nextMonthDate = today()->addMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString();
    $nextMonthSchedule = ConstructionSchedule::factory()
        ->create([
            'scheduled_on' => $nextMonthDate,
            'location' => '翌月の現場',
        ]);

    $currentMonthSchedule->assignedUsers()->attach($user);
    $nextMonthSchedule->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'month',
            'date' => $nextMonthDate,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.range', 'month')
            ->where('filters.date', $nextMonthDate)
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '翌月の現場')
                ->etc()
            )
        );
});

test('calendar includes adjacent month offset days and non empty day navigation', function (): void {
    $user = User::factory()->create();
    $previousDate = '2026-04-30';
    $selectedDate = '2026-05-04';
    $nextDate = '2026-06-01';

    foreach ([
        [$previousDate, '前月の表示対象日'],
        [$selectedDate, '選択中の日'],
        [$nextDate, '翌月の表示対象日'],
    ] as [$scheduledOn, $location]) {
        $schedule = ConstructionSchedule::factory()
            ->create([
                'scheduled_on' => $scheduledOn,
                'location' => $location,
            ]);

        $schedule->assignedUsers()->attach($user);
    }

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'month',
            'date' => $selectedDate,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('scheduleNavigation.previous_date', $previousDate)
            ->where('scheduleNavigation.next_date', $nextDate)
            ->where('calendarDays', fn ($calendarDays): bool => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $previousDate && $day['count'] === 1
            ) && collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $nextDate && $day['count'] === 1
            ))
        );
});

test('calendar includes construction and business schedules together', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-04';

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '選択中の工事',
    ]);
    $constructionSchedule->assignedUsers()->attach($user);

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '安全協議会',
        'content' => '安全協議会',
        'memo' => '名刺持参',
    ]);
    $businessSchedule->assignedUsers()->attach($user);

    $internalNotice = InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'title' => '未選択の業務連絡',
    ]);
    $internalNotice->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.type', ['construction', 'business'])
            ->has('mySchedules', 2)
            ->where('mySchedules', fn ($schedules): bool => collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['type'] === 'construction' && $schedule['location'] === '選択中の工事'
            ) && collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['type'] === 'business' && $schedule['location'] === '安全協議会'
            ) && collect($schedules)->doesntContain(
                fn (array $schedule): bool => $schedule['type'] === 'internal_notice'
            ))
            ->where('calendarDays', fn ($calendarDays) => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['count'] === 2
                    && $day['construction_count'] === 1
                    && $day['business_count'] === 1
            ))
        );
});

test('calendar can filter to business schedules', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-04';

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '選択中の工事',
    ]);
    $constructionSchedule->assignedUsers()->attach($user);

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '定時総会',
        'content' => '定時総会',
    ]);
    $businessSchedule->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
            'type' => 'business',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.type', ['business'])
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'business')
                ->where('location', '定時総会')
                ->etc()
            )
            ->where('calendarDays', fn ($calendarDays) => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['count'] === 1
                    && $day['construction_count'] === 0
                    && $day['business_count'] === 1
            ))
        );
});

test('users can open a day with only business schedules', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-04';

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '定時総会',
        'content' => '定時総会',
    ]);
    $businessSchedule->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
            'type' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.type', ['construction', 'business', 'internal_notice', 'cleaning_duty'])
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'business')
                ->where('location', '定時総会')
                ->etc()
            )
        );
});

test('database seeder creates demo schedules across adjacent months', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@example.com')->where('is_admin', true)->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->toDateString())->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->subMonthNoOverflow()->startOfMonth()->addDays(9)->toDateString())->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->addMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString())->exists())->toBeTrue()
        ->and(BusinessSchedule::query()->whereDate('scheduled_on', today()->addDays(2)->toDateString())->exists())->toBeTrue();
});

test('admins can create schedules with assigned users and guide files', function (): void {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $site = ConstructionSite::factory()->create(['name' => '東京タワー現場']);
    $siteGuide = SiteGuideFile::factory()->create([
        'construction_site_id' => $site->id,
        'name' => '搬入口.pdf',
    ]);

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'construction_site_id' => $site->id,
            'scheduled_on' => today()->toDateString(),
            'schedule_number' => 7,
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'time_note' => null,
            'status' => ConstructionSchedule::STATUS_CONFIRMED,
            'meeting_place' => '正面ゲート',
            'personnel' => '5名',
            'location' => '東京タワー改修',
            'general_contractor' => '山田建設',
            'person_in_charge' => '佐藤',
            'content' => '足場点検と資材搬入',
            'navigation_address' => '東京都港区芝公園4丁目2-8',
            'assigned_user_ids' => [$worker->id],
            'site_guide_file_ids' => [$siteGuide->id],
            'guide_files' => [
                UploadedFile::fake()->create('現場全体図.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect();

    $schedule = ConstructionSchedule::query()->where('location', '東京タワー改修')->firstOrFail();

    expect($schedule->assignedUsers()->whereKey($worker)->exists())->toBeTrue();
    expect($schedule->schedule_number)->toBe(7);
    expect($schedule->selectedGuideFiles()->whereKey($siteGuide)->exists())->toBeTrue();
    expect($schedule->directGuideFiles)->toHaveCount(1);
    expect(GeneralContractor::query()->where('name', '山田建設')->exists())->toBeTrue();

    Storage::disk('public')->assertExists($schedule->directGuideFiles->first()->path);
});

test('schedule form includes remembered general contractor options', function (): void {
    $admin = User::factory()->admin()->create();

    GeneralContractor::factory()->create(['name' => '大成建設']);
    ConstructionSchedule::factory()->create(['general_contractor' => '清水建設']);

    $this->actingAs($admin)
        ->get(route('construction-schedules.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('generalContractorOptions', fn ($options): bool => collect($options)->contains('大成建設')
                && collect($options)->contains('清水建設'))
            ->etc()
        );
});

test('blank general contractor names are not remembered', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => '正面ゲート',
            'location' => '東京現場',
            'general_contractor' => '   ',
            'content' => '作業内容',
            'navigation_address' => '東京都千代田区1-1',
        ])
        ->assertRedirect();

    expect(GeneralContractor::query()->count())->toBe(0);
});

test('admins can create construction schedules without optional details', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'location' => '東京現場',
        ])
        ->assertRedirect();

    $schedule = ConstructionSchedule::query()->where('location', '東京現場')->firstOrFail();

    expect($schedule->meeting_place)->toBeNull()
        ->and($schedule->content)->toBeNull()
        ->and($schedule->navigation_address)->toBeNull();
});

test('construction schedule times cannot overlap selected users existing schedules', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $scheduledOn = today()->toDateString();

    $existingSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'location' => '既存予定',
    ]);
    $existingSchedule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->from(route('construction-schedules.create'))
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => $scheduledOn,
            'starts_at' => '10:30',
            'ends_at' => '12:00',
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'location' => '重複予定',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('starts_at');

    expect(ConstructionSchedule::query()->where('location', '重複予定')->exists())->toBeFalse();
});

test('construction schedule times can start when selected users previous schedules end', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $scheduledOn = today()->toDateString();

    $existingSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'location' => '午前予定',
    ]);
    $existingSchedule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => $scheduledOn,
            'starts_at' => '11:00',
            'ends_at' => '12:00',
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'location' => '連続予定',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect();

    expect(ConstructionSchedule::query()->where('location', '連続予定')->exists())->toBeTrue();
});

test('admins can create business schedules with assigned users', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('business-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'schedule_number' => 4,
            'starts_at' => '10:00',
            'ends_at' => '11:30',
            'time_note' => null,
            'personnel' => '3名',
            'location' => '本社会議室',
            'general_contractor' => '山田建設',
            'person_in_charge' => '佐藤',
            'content' => '安全協議会',
            'memo' => '名刺持参',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => today()->toDateString(),
            'type' => 'business',
        ]));

    $schedule = BusinessSchedule::query()->where('location', '本社会議室')->firstOrFail();

    expect($schedule->assignedUsers()->whereKey($worker)->exists())->toBeTrue();
    expect($schedule->schedule_number)->toBe(4);
    expect(GeneralContractor::query()->where('name', '山田建設')->exists())->toBeTrue();
});

test('schedule numbers cannot overlap on the same day across construction and business schedules', function (): void {
    $admin = User::factory()->admin()->create();
    $scheduledOn = today()->toDateString();

    ConstructionSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'schedule_number' => 5,
        'location' => '既存番号の工事',
    ]);

    $this->actingAs($admin)
        ->from(route('business-schedules.create'))
        ->post(route('business-schedules.store'), [
            'scheduled_on' => $scheduledOn,
            'schedule_number' => 5,
            'location' => '重複番号の業務',
            'content' => '安全協議会',
        ])
        ->assertRedirect(route('business-schedules.create'))
        ->assertSessionHasErrors('schedule_number');

    expect(BusinessSchedule::query()->where('location', '重複番号の業務')->exists())->toBeFalse();
});

test('schedule time note supports same day preset', function (): void {
    $schedule = ConstructionSchedule::factory()->make([
        'starts_at' => '08:00',
        'ends_at' => '17:00',
        'time_note' => '本日中',
    ]);

    expect($schedule->formattedTime())->toBe('本日中');
});

test('non admins cannot create schedules', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => '正面ゲート',
            'location' => '東京現場',
            'content' => '作業内容',
            'navigation_address' => '東京都千代田区1-1',
        ])
        ->assertForbidden();
});

test('google maps url encodes japanese addresses', function (): void {
    $schedule = ConstructionSchedule::factory()->make([
        'navigation_address' => '東京都港区芝公園4丁目2-8',
    ]);

    expect($schedule->googleMapsUrl())
        ->toContain('https://www.google.com/maps/search/?api=1&query=')
        ->toContain(rawurlencode('東京都港区芝公園4丁目2-8'));
});

test('schedule guide uploads must be pdfs or images', function (): void {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('construction-schedules.create'))
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => '正面ゲート',
            'location' => '東京現場',
            'content' => '作業内容',
            'navigation_address' => '東京都千代田区1-1',
            'guide_files' => [
                UploadedFile::fake()->create('memo.txt', 10, 'text/plain'),
            ],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('guide_files.0');
});
