<?php

use App\Models\ConstructionSchedule;
use App\Models\User;
use App\Services\BusinessDate;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('users can view voucher confirmations for construction schedules', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $checkedAt = Carbon::parse('2026-05-10 00:30:00', 'UTC');
    $checkedSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => BusinessDate::today()->toDateString(),
        'location' => '確認済み現場',
        'voucher_note' => '日付を確認済み',
        'voucher_checked_at' => $checkedAt,
        'voucher_checked_by_user_id' => $admin->id,
    ]);
    $uncheckedSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => BusinessDate::today()->toDateString(),
        'location' => '未確認現場',
    ]);

    $checkedSchedule->assignedUsers()->attach($user);
    $uncheckedSchedule->assignedUsers()->attach($user);

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('voucher-confirmations/index')
            ->where('canManage', false)
            ->has('schedules', 2)
            ->where('schedules', fn ($schedules): bool => collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['location'] === '確認済み現場'
                    && $schedule['voucher_checked'] === true
                    && $schedule['voucher_note'] === '日付を確認済み'
                    && $schedule['voucher_checked_at'] === $checkedAt->toJSON()
                    && $schedule['voucher_checked_by']['name'] === $admin->name
            ) && collect($schedules)->contains(
                fn (array $schedule): bool => $schedule['location'] === '未確認現場'
                    && $schedule['voucher_checked'] === false
                    && $schedule['voucher_checked_at'] === null
            ))
        );
});

test('admins can check vouchers and add notes', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'voucher_note' => null,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('construction-schedules.voucher-confirmation.update', $schedule), [
            'voucher_checked' => true,
            'voucher_note' => 'ゼネコン名を確認してください。',
        ])
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->voucher_note)->toBe('ゼネコン名を確認してください。')
        ->and($schedule->voucher_checked_at)->not->toBeNull()
        ->and($schedule->voucher_checked_by_user_id)->toBe($admin->id);
});

test('admins can uncheck vouchers without losing the note', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'voucher_note' => '修正依頼あり',
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('construction-schedules.voucher-confirmation.update', $schedule), [
            'voucher_checked' => false,
            'voucher_note' => '修正依頼あり',
        ])
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->voucher_note)->toBe('修正依頼あり')
        ->and($schedule->voucher_checked_at)->toBeNull()
        ->and($schedule->voucher_checked_by_user_id)->toBeNull();
});

test('non admins cannot update voucher confirmations', function (): void {
    $user = User::factory()->create();
    $schedule = ConstructionSchedule::factory()->create();

    $this->actingAs($user)
        ->patch(route('construction-schedules.voucher-confirmation.update', $schedule), [
            'voucher_checked' => true,
            'voucher_note' => '確認しました',
        ])
        ->assertForbidden();
});

test('voucher confirmation page filters checked state for the selected month', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-10',
        'location' => '確認済み現場',
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $admin->id,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-12',
        'location' => '未確認現場',
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-06-01',
        'location' => '翌月現場',
    ]);

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index', [
            'date' => '2026-05-01',
            'checked' => 'checked',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.checked', 'checked')
            ->where('summary.total', 2)
            ->where('summary.checked', 1)
            ->where('summary.unchecked', 1)
            ->has('schedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '確認済み現場')
                ->where('voucher_checked', true)
                ->etc()
            )
        );

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index', [
            'date' => '2026-05-01',
            'checked' => 'unchecked',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.checked', 'unchecked')
            ->where('summary.total', 2)
            ->where('summary.checked', 1)
            ->where('summary.unchecked', 1)
            ->has('schedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '未確認現場')
                ->where('voucher_checked', false)
                ->etc()
            )
        );
});

test('voucher confirmation page excludes postponed and canceled construction schedules', function (): void {
    $user = User::factory()->create();
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-10',
        'status' => ConstructionSchedule::STATUS_SCHEDULED,
        'location' => '確認対象現場',
        'voucher_checked_at' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-11',
        'status' => ConstructionSchedule::STATUS_CONFIRMED,
        'location' => '確定現場',
        'voucher_checked_at' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-12',
        'status' => ConstructionSchedule::STATUS_POSTPONED,
        'location' => '延長現場',
        'voucher_checked_at' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'status' => ConstructionSchedule::STATUS_CANCELED,
        'location' => '中止現場',
        'voucher_checked_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index', [
            'date' => '2026-05-01',
            'day' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('summary.total', 2)
            ->where('summary.checked', 0)
            ->where('summary.unchecked', 2)
            ->has('dayOptions', 2)
            ->where('schedules', fn ($schedules): bool => collect($schedules)->pluck('location')->all() === [
                '確認対象現場',
                '確定現場',
            ])
        );
});

test('admins cannot update voucher confirmation for postponed or canceled construction schedules', function (string $status): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'status' => $status,
        'voucher_note' => null,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('construction-schedules.voucher-confirmation.update', $schedule), [
            'voucher_checked' => true,
            'voucher_note' => '確認しました',
        ])
        ->assertNotFound();

    $schedule->refresh();

    expect($schedule->voucher_note)->toBeNull()
        ->and($schedule->voucher_checked_at)->toBeNull()
        ->and($schedule->voucher_checked_by_user_id)->toBeNull();
})->with([
    'postponed' => ConstructionSchedule::STATUS_POSTPONED,
    'canceled' => ConstructionSchedule::STATUS_CANCELED,
]);

test('voucher confirmation page can filter schedules by day or show the whole month', function (): void {
    $user = User::factory()->create();
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-12',
        'location' => '十二日現場',
        'voucher_checked_at' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'location' => '十三日現場',
        'voucher_checked_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index', [
            'date' => '2026-05-01',
            'day' => '2026-05-12',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.day', '2026-05-12')
            ->where('summary.total', 2)
            ->has('dayOptions', 2)
            ->has('schedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '十二日現場')
                ->etc()
            )
        );

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index', [
            'date' => '2026-05-01',
            'day' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.day', 'all')
            ->where('summary.total', 2)
            ->has('schedules', 2)
        );
});

test('voucher confirmation page defaults to the current japan business date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-12 15:30:00', 'UTC'));

    $user = User::factory()->create();
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-12',
        'location' => '前日現場',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'location' => '今日現場',
    ]);

    $this->actingAs($user)
        ->get(route('voucher-confirmations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.date', '2026-05-13')
            ->where('filters.day', '2026-05-13')
            ->where('filters.today', '2026-05-13')
            ->has('schedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '今日現場')
                ->etc()
            )
        );

    Carbon::setTestNow();
});

test('editing construction schedule details does not reset voucher confirmation', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => BusinessDate::today()->toDateString(),
        'voucher_note' => '確認済みメモ',
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('construction-schedules.update', $schedule), [
            'scheduled_on' => BusinessDate::today()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'time_note' => null,
            'status' => ConstructionSchedule::STATUS_CONFIRMED,
            'meeting_place' => '変更後ゲート',
            'personnel' => '6名',
            'location' => '更新後現場',
            'general_contractor' => '更新後ゼネコン',
            'person_in_charge' => '更新後担当',
            'content' => '更新後作業内容',
            'navigation_address' => '東京都千代田区1-1',
        ])
        ->assertRedirect(route('construction-schedules.show', $schedule));

    $schedule->refresh();

    expect($schedule->voucher_note)->toBe('確認済みメモ')
        ->and($schedule->voucher_checked_at)->not->toBeNull()
        ->and($schedule->voucher_checked_by_user_id)->toBe($admin->id);
});
