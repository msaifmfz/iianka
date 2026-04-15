<?php

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can view company wide schedule overview counts by day', function (): void {
    $user = User::factory()->create();
    $date = '2026-05-13';

    ConstructionSchedule::factory()->count(2)->create([
        'scheduled_on' => $date,
        'voucher_checked_at' => null,
        'voucher_checked_by_user_id' => null,
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => $date,
        'voucher_checked_at' => now(),
        'voucher_checked_by_user_id' => $user->id,
    ]);
    BusinessSchedule::factory()->count(5)->create([
        'scheduled_on' => $date,
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
    ]);
    BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-04-30',
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
            ->where('filters.date', $date)
            ->where('month.starts_on', '2026-05-01')
            ->where('month.ends_on', '2026-05-31')
            ->where('calendarDays', fn ($days): bool => collect($days)->contains(
                fn (array $day): bool => $day['date'] === $date
                    && $day['construction_count'] === 3
                    && $day['business_count'] === 5
                    && $day['unconfirmed_voucher_count'] === 2
                    && $day['schedule_count'] === 8
            ))
            ->where('calendarDays', fn ($days): bool => collect($days)->contains(
                fn (array $day): bool => $day['date'] === '2026-04-30'
                    && $day['construction_count'] === 1
                    && $day['business_count'] === 1
                    && $day['unconfirmed_voucher_count'] === 1
            ))
            ->where('calendarDays', fn ($days): bool => ! collect($days)->contains(
                fn (array $day): bool => $day['date'] === '2026-06-10'
            ))
            ->where('monthSummary', [
                'construction_count' => 4,
                'business_count' => 6,
                'unconfirmed_voucher_count' => 3,
                'schedule_count' => 10,
            ])
        );
});

test('guests cannot view the schedule overview', function (): void {
    $this->get(route('schedule-overview.index'))
        ->assertRedirect(route('login'));
});
