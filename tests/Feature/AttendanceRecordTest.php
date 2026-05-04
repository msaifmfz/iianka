<?php

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('users can view attendance records', function (): void {
    $user = User::factory()->create(['name' => '閲覧者']);
    $worker = User::factory()->create(['name' => '山田 太郎']);
    $hiddenUser = User::factory()->hiddenFromWorkers()->create(['name' => '非表示 管理者']);

    AttendanceRecord::factory()->leave()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-04',
        'note' => '有給',
    ]);
    AttendanceRecord::factory()->working()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-04-20',
    ]);
    AttendanceRecord::factory()->working()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-21',
    ]);
    AttendanceRecord::factory()->leave()->create([
        'user_id' => $hiddenUser->id,
        'work_date' => '2026-05-04',
        'note' => '秘密',
    ]);

    $this->actingAs($user)
        ->get(route('attendance-records.index', ['month' => '2026-04-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('attendance-records/index')
            ->where('canManage', false)
            ->where('filters.month', '2026-04-01')
            ->where('filters.previous_month', '2026-03-01')
            ->where('filters.next_month', '2026-05-01')
            ->has('days', 30)
            ->where('days.0.date', '2026-04-21')
            ->where('days.29.date', '2026-05-20')
            ->has('users', 2)
            ->has('records', 1)
            ->where('records.0.user.name', '山田 太郎')
            ->where('records.0.status', AttendanceRecord::STATUS_LEAVE)
            ->where('records.0.note', '有給')
            ->where('users', fn ($users): bool => ! collect($users)->contains(
                fn (array $user): bool => $user['id'] === $hiddenUser->id || $user['name'] === '非表示 管理者'
            ))
        );
});

test('attendance records default to the current attendance period month', function (string $today, string $month, int $dayCount, string $startsOn, string $endsOn): void {
    Carbon::setTestNow(Carbon::parse($today, 'Asia/Tokyo'));

    try {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('attendance-records.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('attendance-records/index')
                ->where('filters.month', $month)
                ->has('days', $dayCount)
                ->where('days.0.date', $startsOn)
                ->where('days.'.($dayCount - 1).'.date', $endsOn)
            );
    } finally {
        Carbon::setTestNow();
    }
})->with([
    'period start uses same month' => ['2026-08-21 09:00:00', '2026-08-01', 31, '2026-08-21', '2026-09-20'],
    'period end uses previous month' => ['2026-09-20 09:00:00', '2026-08-01', 31, '2026-08-21', '2026-09-20'],
    'next period start uses same month' => ['2026-09-21 09:00:00', '2026-09-01', 30, '2026-09-21', '2026-10-20'],
]);

test('admins cannot view hidden users on attendance records', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => '山田 太郎']);
    $hiddenUser = User::factory()->hiddenFromWorkers()->create(['name' => '非表示 管理者']);

    AttendanceRecord::factory()->leave()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-04',
        'note' => '有給',
    ]);
    AttendanceRecord::factory()->leave()->create([
        'user_id' => $hiddenUser->id,
        'work_date' => '2026-05-04',
        'note' => '秘密',
    ]);

    $this->actingAs($admin)
        ->get(route('attendance-records.index', ['month' => '2026-04-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('attendance-records/index')
            ->where('canManage', true)
            ->has('users', 2)
            ->has('records', 1)
            ->where('records.0.user.name', '山田 太郎')
            ->where('records.0.status', AttendanceRecord::STATUS_LEAVE)
            ->where('stats.leave', 1)
            ->where('users', fn ($users): bool => ! collect($users)->contains(
                fn (array $user): bool => $user['id'] === $hiddenUser->id || $user['name'] === '非表示 管理者'
            ))
        );
});

test('admins can mark and clear attendance records', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('attendance-records.store'), [
            'user_id' => $worker->id,
            'work_date' => '2026-05-04',
            'status' => AttendanceRecord::STATUS_LEAVE,
            'note' => '午前休',
        ])
        ->assertRedirect();

    $record = AttendanceRecord::query()->sole();

    expect($record->user_id)->toBe($worker->id)
        ->and($record->work_date->toDateString())->toBe('2026-05-04')
        ->and($record->status)->toBe(AttendanceRecord::STATUS_LEAVE)
        ->and($record->note)->toBe('午前休');

    $this->actingAs($admin)
        ->delete(route('attendance-records.destroy', $record))
        ->assertRedirect();

    expect(AttendanceRecord::query()->exists())->toBeFalse();
});

test('admins can edit an existing attendance record', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create();
    $record = AttendanceRecord::factory()->leave()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-04',
        'note' => '午前休',
    ]);

    $this->actingAs($admin)
        ->post(route('attendance-records.store'), [
            'user_id' => $worker->id,
            'work_date' => '2026-05-04',
            'status' => AttendanceRecord::STATUS_WORKING,
            'note' => '出勤に変更',
        ])
        ->assertRedirect();

    expect(AttendanceRecord::query()->count())->toBe(1);

    $record->refresh();

    expect($record->status)->toBe(AttendanceRecord::STATUS_WORKING)
        ->and($record->note)->toBe('出勤に変更');
});

test('editors can mark attendance records', function (): void {
    $editor = User::factory()->editor()->create();
    $worker = User::factory()->create();

    $this->actingAs($editor)
        ->post(route('attendance-records.store'), [
            'user_id' => $worker->id,
            'work_date' => '2026-05-04',
            'status' => AttendanceRecord::STATUS_WORKING,
        ])
        ->assertRedirect();

    expect(AttendanceRecord::query()
        ->where('user_id', $worker->id)
        ->whereDate('work_date', '2026-05-04')
        ->where('status', AttendanceRecord::STATUS_WORKING)
        ->exists())->toBeTrue();
});

test('viewers cannot edit attendance records', function (): void {
    $user = User::factory()->create();
    $worker = User::factory()->create();
    $record = AttendanceRecord::factory()->leave()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-04',
    ]);

    $this->actingAs($user)
        ->post(route('attendance-records.store'), [
            'user_id' => $worker->id,
            'work_date' => '2026-05-05',
            'status' => AttendanceRecord::STATUS_WORKING,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('attendance-records.destroy', $record))
        ->assertForbidden();
});

test('schedule forms include leave records as warnings without blocking assignment', function (): void {
    $admin = User::factory()->admin()->create();
    $worker = User::factory()->create(['name' => '休み担当']);

    AttendanceRecord::factory()->leave()->create([
        'user_id' => $worker->id,
        'work_date' => '2026-05-04',
        'note' => '有給',
    ]);

    $this->actingAs($admin)
        ->get(route('construction-schedules.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->has('attendanceLeaveRecords', 1)
            ->where('attendanceLeaveRecords.0.user_id', $worker->id)
            ->where('attendanceLeaveRecords.0.user_name', '休み担当')
        );

    $this->actingAs($admin)
        ->post(route('construction-schedules.store'), [
            'scheduled_on' => '2026-05-04',
            'status' => 'scheduled',
            'location' => '休みでも登録できる現場',
            'assigned_user_ids' => [$worker->id],
        ])
        ->assertRedirect(route('construction-schedules.index', [
            'range' => 'today',
            'date' => '2026-05-04',
        ]));
});
