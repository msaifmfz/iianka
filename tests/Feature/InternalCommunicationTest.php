<?php

use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can create internal notices with assigned users', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => 'Mr.A']);

    $this->actingAs($admin)
        ->post(route('internal-notices.store'), [
            'scheduled_on' => '2026-04-08',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'time_note' => null,
            'title' => '健康診断',
            'location' => '本社',
            'content' => '健康診断を受診してください。',
            'memo' => '保険証持参',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-04-08',
            'type' => 'internal_notice',
        ]));

    $notice = InternalNotice::query()->where('title', '健康診断')->firstOrFail();

    expect($notice->assignedUsers()->whereKey($worker)->exists())->toBeTrue();
});

test('calendar includes internal notices in personal schedules and filters', function (): void {
    $worker = User::factory()->create(['name' => 'Mr.A']);
    $otherUser = User::factory()->create();
    $date = '2026-04-08';

    $notice = InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'title' => '健康診断',
        'content' => '健康診断を受診してください。',
    ]);
    $notice->assignedUsers()->attach($worker);

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'location' => '外部工事',
    ]);
    $constructionSchedule->assignedUsers()->attach($otherUser);

    $this->actingAs($worker)
        ->get(route('construction-schedules.index', [
            'range' => 'today',
            'date' => $date,
            'type' => 'internal_notice',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.type', 'internal_notice')
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'internal_notice')
                ->where('title', '健康診断')
                ->etc()
            )
            ->has('teamSchedules', 0)
            ->where('calendarDays', fn ($calendarDays): bool => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['count'] === 1
                    && $day['internal_notice_count'] === 1
                    && $day['construction_count'] === 0
            ))
        );
});

test('cleaning duty rules render virtual calendar events without generated schedule rows', function (): void {
    $worker = User::factory()->create(['name' => 'Mr.B']);
    $monday = '2026-04-06';

    $rule = CleaningDutyRule::factory()->create([
        'weekday' => 1,
        'label' => '掃除当番',
        'location' => '事務所',
        'notes' => 'ゴミ出し',
        'is_active' => true,
    ]);
    $rule->assignedUsers()->attach($worker);

    $this->actingAs($worker)
        ->get(route('construction-schedules.index', [
            'range' => 'week',
            'date' => $monday,
            'type' => 'cleaning_duty',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('filters.type', 'cleaning_duty')
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'cleaning_duty')
                ->where('title', '掃除当番')
                ->where('scheduled_on', $monday)
                ->where('weekday_label', '月曜日')
                ->etc()
            )
            ->where('calendarDays', fn ($calendarDays): bool => collect($calendarDays)->contains(
                fn (array $day): bool => $day['date'] === $monday
                    && $day['count'] === 1
                    && $day['cleaning_duty_count'] === 1
            ))
        );

    expect(InternalNotice::query()->count())->toBe(0)
        ->and(ConstructionSchedule::query()->count())->toBe(0);
});

test('inactive cleaning duty rules are hidden from the calendar', function (): void {
    $worker = User::factory()->create();
    $monday = '2026-04-06';

    $rule = CleaningDutyRule::factory()->create([
        'weekday' => 1,
        'is_active' => false,
    ]);
    $rule->assignedUsers()->attach($worker);

    $this->actingAs($worker)
        ->get(route('construction-schedules.index', [
            'range' => 'week',
            'date' => $monday,
            'type' => 'cleaning_duty',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->has('mySchedules', 0)
            ->has('teamSchedules', 0)
        );
});

test('non admins cannot create internal communication settings', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('internal-notices.store'), [
            'scheduled_on' => '2026-04-08',
            'title' => '健康診断',
            'content' => '健康診断を受診してください。',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('cleaning-duty-rules.store'), [
            'weekday' => 1,
            'label' => '掃除当番',
            'assigned_user_ids' => [$user->id],
        ])
        ->assertForbidden();
});
