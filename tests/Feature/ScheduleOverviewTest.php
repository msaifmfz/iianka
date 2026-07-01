<?php

use App\Models\AttendanceRecord;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can view company wide schedule overview counts by day', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-13';

    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
        'carry_out_note' => '発電機',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
        'carry_out_note' => '   ',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $user->id,
        'carry_out_note' => '測定器',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'status' => ConstructionSchedule::STATUS_POSTPONED,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
        'carry_out_note' => '延期でも持ち出し',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'status' => ConstructionSchedule::STATUS_CANCELED,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
        'carry_out_note' => '中止でも持ち出し',
    ]);
    BusinessSchedule::factory()->count(5)->create([
        'scheduled_on' => $date,
    ]);
    InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'title' => '社内連絡',
    ]);

    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-20',
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);
    BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-20',
    ]);

    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-04-30',
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
        'carry_out_note' => '脚立',
    ]);
    BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-04-30',
    ]);
    InternalNotice::factory()->create([
        'scheduled_on' => '2026-04-30',
        'title' => '月末連絡',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-06-10',
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('schedule-overview.index', ['date' => $date]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('schedule-overview/index')
            ->where('canManageSchedules', false)
            ->where('filters.date', $date)
            ->where('month.starts_on', '2026-05-01')
            ->where('month.ends_on', '2026-05-31')
            ->where('calendarDays', fn ($days): bool => collect($days)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['construction_count'] === 5
                    && $day['business_count'] === 5
                    && $day['internal_notice_count'] === 1
                    && $day['carry_out_count'] === 4
                    && $day['voucher_confirmation_count'] === 3
                    && $day['unconfirmed_voucher_count'] === 2
                    && $day['schedule_count'] === 11
            ))
            ->where('calendarDays', fn ($days): bool => collect($days)->contains(
                fn (array $day): bool => $day['date'] === '2026-04-30'
                    && $day['construction_count'] === 1
                    && $day['business_count'] === 1
                    && $day['internal_notice_count'] === 1
                    && $day['carry_out_count'] === 1
                    && $day['voucher_confirmation_count'] === 1
                    && $day['unconfirmed_voucher_count'] === 1
            ))
            ->where('calendarDays', fn ($days): bool => ! collect($days)->contains(
                fn (array $day): bool => $day['date'] === '2026-06-10'
            ))
            ->where('monthSummary', [
                'construction_count' => 6,
                'business_count' => 6,
                'internal_notice_count' => 1,
                'carry_out_count' => 4,
                'unconfirmed_voucher_count' => 3,
                'schedule_count' => 13,
            ])
        );
});

test('users can view selected day timeline for visible workers and assigned events', function (): void {
    $viewer = User::factory()->hiddenFromWorkers()->create();
    $firstWorker = User::factory()->create(['name' => '青木']);
    $secondWorker = User::factory()->create(['name' => '佐藤']);
    $thirdWorker = User::factory()->create(['name' => '田中']);
    $hiddenWorker = User::factory()->hiddenFromWorkers()->create(['name' => '非表示']);
    $date = '2026-05-13';

    AttendanceRecord::factory()->leave()->create([
        'user_id' => $firstWorker->id,
        'work_date' => $date,
        'note' => '私用',
    ]);
    AttendanceRecord::factory()->working()->create([
        'user_id' => $secondWorker->id,
        'work_date' => $date,
    ]);
    AttendanceRecord::factory()->leave()->create([
        'user_id' => $thirdWorker->id,
        'work_date' => '2026-05-14',
    ]);
    AttendanceRecord::factory()->leave()->create([
        'user_id' => $hiddenWorker->id,
        'work_date' => $date,
    ]);

    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 7,
        'starts_at' => '08:00',
        'ends_at' => '10:00',
        'location' => '中央ビル',
        'content' => '配線工事',
        'carry_out_note' => '台車',
    ]);
    $constructionSchedule->assignedUsers()->attach([$firstWorker->id, $secondWorker->id]);

    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => $date,
        'schedule_number' => 12,
        'starts_at' => '13:00',
        'ends_at' => null,
        'location' => '本社会議室',
        'content' => '営業会議',
    ]);
    $businessSchedule->assignedUsers()->attach($secondWorker);

    $internalNotice = InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'starts_at' => null,
        'ends_at' => null,
        'time_note' => '本日中',
        'title' => '書類提出',
    ]);
    $internalNotice->assignedUsers()->attach($firstWorker);

    $hiddenOnlyNotice = InternalNotice::factory()->create([
        'scheduled_on' => $date,
        'title' => '非表示担当の連絡',
    ]);
    $hiddenOnlyNotice->assignedUsers()->attach($hiddenWorker);

    InternalNotice::factory()->create([
        'scheduled_on' => '2026-05-14',
        'title' => '翌日の連絡',
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('schedule-overview.index', ['date' => $date]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('schedule-overview/index')
            ->has('selectedDayTimeline.users', 3)
            ->has('selectedDayTimeline.events', 4)
            ->has('selectedDayTimeline.attendanceLeaveRecords', 1)
        );

    $timeline = $response->inertiaProps('selectedDayTimeline');
    $users = collect($timeline['users']);
    $events = collect($timeline['events']);
    $leaveRecords = collect($timeline['attendanceLeaveRecords']);

    expect($users->pluck('name'))->toContain('青木', '佐藤', '田中')
        ->and($users->pluck('name'))->not->toContain('非表示')
        ->and($events)->toHaveCount(4)
        ->and($leaveRecords)->toHaveCount(1)
        ->and($leaveRecords->first())->toMatchArray([
            'user_id' => $firstWorker->id,
            'user_name' => '青木',
            'work_date' => $date,
            'note' => '私用',
        ])
        ->and($events->contains(fn (array $event): bool => $event['type'] === 'construction'
            && $event['title'] === '中央ビル'
            && $event['schedule_number'] === 7
            && $event['carry_out_note'] === '台車'
            && $event['starts_at'] === '08:00'
            && $event['ends_at'] === '10:00'
            && collect($event['assigned_users'])->pluck('name')->contains('青木')
            && collect($event['assigned_users'])->pluck('name')->contains('佐藤')))->toBeTrue()
        ->and($events->contains(fn (array $event): bool => $event['type'] === 'business'
            && $event['title'] === '本社会議室'
            && $event['schedule_number'] === 12
            && $event['starts_at'] === '13:00'
            && $event['ends_at'] === null
            && collect($event['assigned_users'])->pluck('name')->all() === ['佐藤']))->toBeTrue()
        ->and($events->contains(fn (array $event): bool => $event['type'] === 'internal_notice'
            && $event['title'] === '書類提出'
            && $event['schedule_number'] === null
            && $event['starts_at'] === null
            && $event['ends_at'] === null
            && $event['time_note'] === '本日中'
            && collect($event['assigned_users'])->pluck('name')->all() === ['青木']))->toBeTrue()
        ->and($events->contains(fn (array $event): bool => $event['type'] === 'internal_notice'
            && $event['title'] === '非表示担当の連絡'
            && $event['assigned_users'] === []))->toBeTrue();
});

test('admins get schedule management actions on overview', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('schedule-overview.index', ['date' => '2026-05-13']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('schedule-overview/index')
            ->where('canManageSchedules', true)
        );
});

test('overview accepts schedule search highlight and return query values', function (): void {
    $user = User::factory()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
    ]);
    $returnTo = '/schedule-search?location=%E4%B8%AD%E5%A4%AE&selected_type=construction&selected_id='.$schedule->id;

    $this->actingAs($user)
        ->get(route('schedule-overview.index', [
            'date' => '2026-05-13',
            'highlight_type' => 'construction',
            'highlight_id' => $schedule->id,
            'return_to' => $returnTo,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('highlightedSchedule', [
                'type' => 'construction',
                'id' => $schedule->id,
            ])
            ->where('returnTo', $returnTo)
        );
});

test('overview ignores invalid schedule search return query values', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('schedule-overview.index', [
            'date' => '2026-05-13',
            'highlight_type' => 'internal_notice',
            'highlight_id' => 'abc',
            'return_to' => 'https://example.com/schedule-search',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('highlightedSchedule', [
                'type' => null,
                'id' => null,
            ])
            ->where('returnTo', null)
        );
});

test('timeline create links can prefill schedule form values', function (string $routeName, string $component): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();

    $this->actingAs($admin)
        ->get(route($routeName, [
            'scheduled_on' => '2026-05-13',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'assigned_user_ids' => [$worker->id],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component($component)
            ->where('initialScheduledOn', '2026-05-13')
            ->where('initialStartsAt', '09:00')
            ->where('initialEndsAt', '10:00')
            ->where('initialAssignedUserIds', [$worker->id])
        );
})->with([
    'construction schedule' => [
        'construction-schedules.create',
        'construction-schedules/form',
    ],
    'business schedule' => [
        'business-schedules.create',
        'business-schedules/form',
    ],
    'internal notice' => [
        'internal-notices.create',
        'internal-notices/form',
    ],
]);

test('guests cannot view the schedule overview', function (): void {
    $this->get(route('schedule-overview.index'))
        ->assertRedirect(route('login'));
});
