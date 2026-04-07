<?php

use App\Models\ConstructionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can view voucher confirmations for construction schedules', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $checkedSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => today()->toDateString(),
        'location' => '確認済み現場',
        'voucher_note' => '日付を確認済み',
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $admin->id,
    ]);
    $uncheckedSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => today()->toDateString(),
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
            ->has('schedules', 1, fn (Assert $page): Assert => $page
                ->where('location', '未確認現場')
                ->where('voucher_checked', false)
                ->etc()
            )
        );
});

test('editing construction schedule details does not reset voucher confirmation', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => today()->toDateString(),
        'voucher_note' => '確認済みメモ',
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('construction-schedules.update', $schedule), [
            'construction_site_id' => $schedule->construction_site_id,
            'scheduled_on' => today()->addDay()->toDateString(),
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
