<?php

use App\Models\ConstructionSchedule;
use App\Models\ConstructionSite;
use App\Models\SiteGuideFile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('users can see their own and others schedules for today', function () {
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('construction-schedules/index')
            ->has('mySchedules', 1, fn (Assert $page) => $page
                ->where('location', '銀座ビル改修')
                ->etc()
            )
            ->has('teamSchedules', 1, fn (Assert $page) => $page
                ->where('location', '渋谷駅前工事')
                ->etc()
            )
        );
});

test('users can browse schedules in the requested month', function () {
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('construction-schedules/index')
            ->where('filters.range', 'month')
            ->where('filters.date', $nextMonthDate)
            ->has('mySchedules', 1, fn (Assert $page) => $page
                ->where('location', '翌月の現場')
                ->etc()
            )
        );
});

test('calendar includes adjacent month offset days and non empty day navigation', function () {
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('construction-schedules/index')
            ->where('scheduleNavigation.previous_date', $previousDate)
            ->where('scheduleNavigation.next_date', $nextDate)
            ->where('calendarDays', fn ($calendarDays) => collect($calendarDays)->contains(
                fn (array $day) => $day['date'] === $previousDate && $day['count'] === 1
            ) && collect($calendarDays)->contains(
                fn (array $day) => $day['date'] === $nextDate && $day['count'] === 1
            ))
        );
});

test('database seeder creates demo schedules across adjacent months', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@example.com')->where('is_admin', true)->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->toDateString())->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->subMonthNoOverflow()->startOfMonth()->addDays(9)->toDateString())->exists())->toBeTrue()
        ->and(ConstructionSchedule::query()->whereDate('scheduled_on', today()->addMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString())->exists())->toBeTrue();
});

test('admins can create schedules with assigned users and guide files', function () {
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
    expect($schedule->selectedGuideFiles()->whereKey($siteGuide)->exists())->toBeTrue();
    expect($schedule->directGuideFiles)->toHaveCount(1);

    Storage::disk('public')->assertExists($schedule->directGuideFiles->first()->path);
});

test('non admins cannot create schedules', function () {
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

test('google maps url encodes japanese addresses', function () {
    $schedule = ConstructionSchedule::factory()->make([
        'navigation_address' => '東京都港区芝公園4丁目2-8',
    ]);

    expect($schedule->googleMapsUrl())
        ->toContain('https://www.google.com/maps/search/?api=1&query=')
        ->toContain(rawurlencode('東京都港区芝公園4丁目2-8'));
});

test('schedule guide uploads must be pdfs or images', function () {
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
