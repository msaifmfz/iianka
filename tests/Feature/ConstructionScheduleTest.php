<?php

use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
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

test('non admin navigation across calendar dates never gains manage access', function (): void {
    $user = User::factory()->create();
    $firstDate = '2026-05-04';
    $secondDate = '2026-05-05';

    foreach ([$firstDate, $secondDate] as $scheduledOn) {
        $schedule = ConstructionSchedule::factory()->create([
            'scheduled_on' => $scheduledOn,
            'location' => "予定 {$scheduledOn}",
        ]);

        $schedule->assignedUsers()->attach($user);
    }

    $this->actingAs($user);

    foreach ([$firstDate, $secondDate, $firstDate] as $selectedDate) {
        $this->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $selectedDate,
            'type' => 'all',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('construction-schedules/index')
                ->where('filters.date', $selectedDate)
                ->where('canManage', false)
                ->where('userOptions', [])
            );
    }
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

test('index shares today date separately from the selected date', function (): void {
    $user = User::factory()->create();
    $selectedDate = today()->addMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString();

    $this->actingAs($user)
        ->get(route('construction-schedules.index', [
            'range' => 'month',
            'date' => $selectedDate,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.date', $selectedDate)
            ->where('todayDate', today()->toDateString())
        );
});

test('index keeps the selected date across day week and month ranges', function (): void {
    $user = User::factory()->create();
    $selectedDate = today()->addMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString();

    $this->actingAs($user);

    foreach (['today', 'week', 'month'] as $range) {
        $this->get(route('construction-schedules.index', [
            'range' => $range,
            'date' => $selectedDate,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('construction-schedules/index')
                ->where('filters.range', $range)
                ->where('filters.date', $selectedDate)
            );
    }
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

test('calendar day count changes with type parameters', function (
    array|string|null $type,
    int $expectedCount,
    int $expectedConstructionCount,
    int $expectedBusinessCount,
    int $expectedInternalNoticeCount,
    int $expectedCleaningDutyCount,
): void {
    $user = User::factory()->create();
    $date = '2026-04-06';

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '工事予定',
    ]);
    $constructionSchedule->assignedUsers()->attach($user);

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '業務予定',
        'content' => '業務予定',
    ]);
    $businessSchedule->assignedUsers()->attach($user);

    $internalNotice = InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'title' => '業務連絡',
    ]);
    $internalNotice->assignedUsers()->attach($user);

    $cleaningDutyRule = CleaningDutyRule::factory()->create([
        'weekday' => 1,
        'label' => '掃除当番',
        'location' => '事務所',
        'is_active' => true,
    ]);
    $cleaningDutyRule->assignedUsers()->attach($user);

    $parameters = [
        'range' => 'today',
        'date' => $date,
    ];

    if ($type !== null) {
        $parameters['type'] = $type;
    }

    $this->actingAs($user)
        ->get(route('construction-schedules.index', $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('calendarDays', fn ($calendarDays): bool => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['count'] === $expectedCount
                    && $day['construction_count'] === $expectedConstructionCount
                    && $day['business_count'] === $expectedBusinessCount
                    && $day['internal_notice_count'] === $expectedInternalNoticeCount
                    && $day['cleaning_duty_count'] === $expectedCleaningDutyCount
            ))
        );
})->with([
    'default construction and business' => [
        null,
        2,
        1,
        1,
        0,
        0,
    ],
    'all types' => [
        'all',
        4,
        1,
        1,
        1,
        1,
    ],
    'business only' => [
        'business',
        1,
        0,
        1,
        0,
        0,
    ],
    'construction business and cleaning duty' => [
        ['construction', 'business', 'cleaning_duty'],
        3,
        1,
        1,
        0,
        1,
    ],
]);

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
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $siteGuide = SiteGuideFile::factory()->create([
        'name' => '東京タワー_搬入口.pdf',
    ]);

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
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
            'guide_file_names' => [
                '現場全体図',
            ],
        ])
        ->assertRedirect();

    $schedule = ConstructionSchedule::query()->where('location', '東京タワー改修')->firstOrFail();
    $uploadedGuide = SiteGuideFile::query()
        ->where('name', '現場全体図')
        ->first();

    expect($schedule->assignedUsers()->whereKey($worker)->exists())->toBeTrue();
    expect($schedule->schedule_number)->toBe(7);
    expect($schedule->selectedGuideFiles()->whereKey($siteGuide)->exists())->toBeTrue();
    expect($uploadedGuide)->not->toBeNull();
    expect($schedule->selectedGuideFiles()->whereKey($uploadedGuide)->exists())->toBeTrue();
    expect(GeneralContractor::query()->where('name', '山田建設')->exists())->toBeTrue();

    Storage::disk('local')->assertExists($uploadedGuide->path);
});

test('uploaded guide files from a schedule become standalone library files on edit', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => '南口',
            'location' => '品川南口工区',
            'content' => '仮囲い設置',
            'navigation_address' => '東京都港区港南1-1-1',
            'guide_files' => [
                UploadedFile::fake()->create('追加案内図.png', 100, 'image/png'),
            ],
            'guide_file_names' => [
                '追加案内図',
            ],
        ])
        ->assertRedirect();

    $schedule = ConstructionSchedule::query()->where('location', '品川南口工区')->firstOrFail();
    $uploadedGuide = SiteGuideFile::query()
        ->where('name', '追加案内図')
        ->firstOrFail();

    expect($schedule->selectedGuideFiles()->whereKey($uploadedGuide)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('construction-schedules.edit', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('schedule.id', $schedule->id)
            ->where('schedule.selected_site_guide_file_ids', fn ($ids): bool => collect($ids)->contains($uploadedGuide->id))
            ->where('siteGuideFiles', fn ($guideFiles): bool => collect($guideFiles)
                ->contains(fn (array $guideFile): bool => $guideFile['id'] === $uploadedGuide->id
                    && $guideFile['name'] === '追加案内図'))
            ->etc()
        );

    $this->actingAs($admin)
        ->get(route('construction-sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/index')
            ->where('guideFiles', fn ($guideFiles): bool => collect($guideFiles)
                ->contains(fn (array $guideFile): bool => $guideFile['id'] === $uploadedGuide->id
                    && $guideFile['name'] === '追加案内図'))
            ->etc()
        );
});

test('admins can update a construction schedule number from the index flow', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-04',
        'schedule_number' => 2,
    ]);

    $this->actingAs($admin)
        ->from(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-05-04',
        ]))
        ->patch(route('construction-schedules.number.update', $schedule), [
            'schedule_number' => 9,
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-05-04',
        ]));

    expect($schedule->fresh()->schedule_number)->toBe(9);
});

test('admins can update a business schedule number from the index flow', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-04',
        'schedule_number' => 4,
    ]);

    $this->actingAs($admin)
        ->from(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-05-04',
            'type' => 'business',
        ]))
        ->patch(route('business-schedules.number.update', $schedule), [
            'schedule_number' => 8,
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-05-04',
            'type' => 'business',
        ]));

    expect($schedule->fresh()->schedule_number)->toBe(8);
});

test('inline schedule number updates cannot overlap with another schedule on the same day', function (): void {
    $admin = User::factory()->admin()->create();
    $scheduledOn = '2026-05-04';

    ConstructionSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'schedule_number' => 5,
    ]);

    $schedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'schedule_number' => 2,
    ]);

    $this->actingAs($admin)
        ->from(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $scheduledOn,
            'type' => 'business',
        ]))
        ->patch(route('business-schedules.number.update', $schedule), [
            'schedule_number' => 5,
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $scheduledOn,
            'type' => 'business',
        ]))
        ->assertSessionHasErrors('schedule_number');

    expect($schedule->fresh()->schedule_number)->toBe(2);
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

test('business schedule times cannot overlap selected users existing schedules', function (): void {
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
        ->from(route('business-schedules.create'))
        ->post(route('business-schedules.store'), [
            'scheduled_on' => $scheduledOn,
            'starts_at' => '10:30',
            'ends_at' => '12:00',
            'location' => '重複業務予定',
            'content' => '安全協議会',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect(route('business-schedules.create'))
        ->assertSessionHasErrors('starts_at');

    expect(BusinessSchedule::query()->where('location', '重複業務予定')->exists())->toBeFalse();
});

test('business schedule create form includes schedule availability for assigned user conflict ux', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => '山田太郎']);
    $scheduledOn = today()->toDateString();

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'location' => '既存工事',
    ]);
    $constructionSchedule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->get(route('business-schedules.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->has('scheduleAvailability', 1)
            ->where('scheduleAvailability.0.type', 'construction')
            ->where('scheduleAvailability.0.title', '既存工事')
            ->where('scheduleAvailability.0.scheduled_on', $scheduledOn)
            ->where('scheduleAvailability.0.time', '09:00 - 11:00')
            ->where('scheduleAvailability.0.user_ids.0', $worker->id)
            ->where('scheduleAvailability.0.user_names.0', '山田太郎')
        );
});

test('business schedule edit form excludes the current schedule from availability data', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => '山田太郎']);
    $scheduledOn = today()->toDateString();

    $currentSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'location' => '編集中の業務予定',
    ]);
    $currentSchedule->assignedUsers()->attach($worker);

    $otherSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $scheduledOn,
        'starts_at' => '13:00',
        'ends_at' => '15:00',
        'location' => '別の業務予定',
    ]);
    $otherSchedule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->get(route('business-schedules.edit', $currentSchedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->has('scheduleAvailability', 1)
            ->where('scheduleAvailability.0.id', $otherSchedule->id)
            ->where('scheduleAvailability.0.title', '別の業務予定')
            ->etc()
        );
});

test('business schedule form includes remembered content options', function (): void {
    $admin = User::factory()->admin()->create();

    BusinessSchedule::factory()->create(['content' => '安全協議会']);
    BusinessSchedule::factory()->create(['content' => '定時総会']);

    $this->actingAs($admin)
        ->get(route('business-schedules.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->where('contentOptions', function ($options): bool {
                $options = collect($options);

                return $options->contains('安全協議会')
                    && $options->contains('定時総会')
                    && $options->contains('見積もり作成')
                    && $options->contains('単価記入')
                    && $options->contains('安全書類作成')
                    && $options->contains('施行要領書作成')
                    && $options->contains('作業日報作成（週末）')
                    && $options->contains('作業日報作成（月末）')
                    && $options->contains('外回り（東)')
                    && $options->contains('外回り（西)')
                    && $options->contains('外回り（南)')
                    && $options->contains('外回り（北)')
                    && $options->contains('外回り（県内)')
                    && $options->contains('外回り（県外)');
            })
            ->etc()
        );
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
    Storage::fake('local');

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

test('schedule guide uploads may be smartphone photo formats up to 50 megabytes', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => today()->toDateString(),
            'status' => ConstructionSchedule::STATUS_SCHEDULED,
            'meeting_place' => '正面ゲート',
            'location' => 'スマートフォン写真現場',
            'content' => '作業内容',
            'navigation_address' => '東京都千代田区1-1',
            'guide_files' => [
                UploadedFile::fake()->create('site-photo.heif', 50 * 1024, 'image/heif'),
            ],
            'guide_file_names' => [
                'スマートフォン写真',
            ],
        ])
        ->assertRedirect();

    $uploadedGuide = SiteGuideFile::query()
        ->where('name', 'スマートフォン写真')
        ->firstOrFail();

    expect($uploadedGuide->mime_type)->toBe('image/heif');

    Storage::disk('local')->assertExists($uploadedGuide->path);
});

test('schedule guide uploads may not exceed 50 megabytes', function (): void {
    Storage::fake('local');

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
                UploadedFile::fake()->create('too-large-guide.pdf', (50 * 1024) + 1, 'application/pdf'),
            ],
            'guide_file_names' => [
                '大きすぎる案内図',
            ],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('guide_files.0');
});

test('schedule guide uploads require display names', function (): void {
    Storage::fake('local');

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
                UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
            ],
            'guide_file_names' => [
                '',
            ],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('guide_file_names.0');
});

test('schedule guide upload display names must be unique', function (): void {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    SiteGuideFile::factory()->create([
        'name' => '既存案内図',
    ]);

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
                UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
            ],
            'guide_file_names' => [
                '既存案内図',
            ],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('guide_file_names.0');

    expect(SiteGuideFile::query()->where('name', '既存案内図')->count())->toBe(1);
});

test('schedule guide upload display names must be distinct in the same request', function (): void {
    Storage::fake('local');

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
                UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
            ],
            'guide_file_names' => [
                '重複案内図',
                '重複案内図',
            ],
        ])
        ->assertRedirect(route('construction-schedules.create'))
        ->assertSessionHasErrors('guide_file_names.0');

    expect(SiteGuideFile::query()->where('name', '重複案内図')->exists())->toBeFalse();
});
