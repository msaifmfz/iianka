<?php

use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use App\Services\BusinessDate;
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
            ->where('filters.type', ['internal_notice'])
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'internal_notice')
                ->where('title', '健康診断')
                ->etc()
            )
            ->has('teamSchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'internal_notice')
                ->where('title', '健康診断')
                ->etc()
            )
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
            ->where('filters.type', ['cleaning_duty'])
            ->has('mySchedules', 1, fn (Assert $page): Assert => $page
                ->where('type', 'cleaning_duty')
                ->where('rule_id', $rule->id)
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

test('users can open a cleaning duty rule detail page from the schedule', function (): void {
    $worker = User::factory()->create(['name' => 'Mr.B']);

    $rule = CleaningDutyRule::factory()->create([
        'weekday' => 1,
        'label' => '掃除当番',
        'location' => '事務所',
        'notes' => 'ゴミ出しと床掃除をお願いします。',
        'is_active' => true,
    ]);
    $rule->assignedUsers()->attach($worker);

    $this->actingAs($worker)
        ->get(route('cleaning-duty-rules.show', [
            'cleaning_duty_rule' => $rule,
            'return_to' => '/construction-schedules?range=week&date=2026-04-06&type=cleaning_duty',
            'scheduled_on' => '2026-04-06',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cleaning-duty-rules/show')
            ->where('canManage', false)
            ->where('returnTo', '/construction-schedules?range=week&date=2026-04-06&type=cleaning_duty')
            ->where('scheduledOn', '2026-04-06')
            ->has('rule', fn (Assert $page): Assert => $page
                ->where('id', $rule->id)
                ->where('label', '掃除当番')
                ->where('location', '事務所')
                ->where('notes', 'ゴミ出しと床掃除をお願いします。')
                ->where('assigned_users.0.name', 'Mr.B')
                ->etc()
            )
        );
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

test('admins can open cleaning duty settings index', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => 'Mr.C']);

    $rule = CleaningDutyRule::factory()->create([
        'weekday' => 2,
        'label' => '掃除当番',
        'location' => '給湯室',
    ]);
    $rule->assignedUsers()->attach($worker);

    $this->actingAs($admin)
        ->get(route('cleaning-duty-rules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('cleaning-duty-rules/index')
            ->has('rules', 1, fn (Assert $page): Assert => $page
                ->where('label', '掃除当番')
                ->where('location', '給湯室')
                ->where('assigned_users.0.name', 'Mr.C')
                ->etc()
            )
        );
});

test('worker dashboard shares attention counts and worker summary', function (): void {
    $worker = User::factory()->create(['name' => 'Worker A']);
    $today = BusinessDate::today()->toDateString();

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $today,
        'status' => ConstructionSchedule::STATUS_POSTPONED,
        'voucher_checked_at' => null,
    ]);
    $constructionSchedule->assignedUsers()->attach($worker);

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $today,
        'location' => '朝礼',
    ]);
    $businessSchedule->assignedUsers()->attach($worker);

    $notice = InternalNotice::factory()->create([
        'scheduled_on' => $today,
        'title' => '安全周知',
    ]);
    $notice->assignedUsers()->attach($worker);

    $cleaningDutyRule = CleaningDutyRule::factory()->create([
        'weekday' => BusinessDate::today()->dayOfWeek,
        'is_active' => true,
    ]);
    $cleaningDutyRule->assignedUsers()->attach($worker);

    $this->actingAs($worker)
        ->get(route('construction-schedules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/index')
            ->where('attention.schedule_count', 4)
            ->where('attention.pending_voucher_count', 1)
            ->where('attention.internal_notice_count', 1)
            ->where('workerSummary.assigned_count', 4)
            ->where('workerSummary.notice_count', 1)
            ->where('workerSummary.pending_voucher_count', 1)
            ->where('workerSummary.status_change_count', 1)
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
