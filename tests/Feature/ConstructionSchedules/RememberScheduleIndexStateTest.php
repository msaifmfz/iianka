<?php

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('passes the requested schedule index url to the construction schedule detail page', function (): void {
    $user = User::factory()->create();
    $schedule = ConstructionSchedule::factory()->create();

    $this->actingAs($user)
        ->get("/construction-schedules/{$schedule->id}?return_to=%2Fconstruction-schedules%3Frange%3Dmonth%26date%3D2026-04-01%26type%255B0%255D%3Dconstruction%26type%255B1%255D%3Dbusiness")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/show')
            ->where('returnTo', '/construction-schedules?range=month&date=2026-04-01&type%5B0%5D=construction&type%5B1%5D=business'));
});

it('passes the requested schedule index url to the business schedule detail page', function (): void {
    $user = User::factory()->create();
    $schedule = BusinessSchedule::factory()->create();

    $this->actingAs($user)
        ->get("/business-schedules/{$schedule->id}?return_to=%2Fconstruction-schedules%3Frange%3Dweek%26date%3D2026-04-07%26type%255B0%255D%3Dbusiness")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/show')
            ->where('returnTo', '/construction-schedules?range=week&date=2026-04-07&type%5B0%5D=business'));
});

it('passes the requested schedule index url to the internal notice detail page', function (): void {
    $user = User::factory()->create();
    $notice = InternalNotice::factory()->create();

    $this->actingAs($user)
        ->get("/internal-notices/{$notice->id}?return_to=%2Fconstruction-schedules%3Frange%3Dtoday%26date%3D2026-04-10%26type%255B0%255D%3Dinternal_notice%26user_ids%255B0%255D%3D1")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('internal-notices/show')
            ->where('returnTo', '/construction-schedules?range=today&date=2026-04-10&type%5B0%5D=internal_notice&user_ids%5B0%5D=1'));
});

it('ignores non schedule return urls', function (): void {
    $user = User::factory()->create();
    $schedule = ConstructionSchedule::factory()->create();

    $this->actingAs($user)
        ->get("/construction-schedules/{$schedule->id}?return_to=%2Fadmin%2Fusers")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/show')
            ->where('returnTo', null));
});

it('passes the requested schedule overview url to schedule form pages', function (): void {
    $admin = User::factory()->admin()->create();
    $constructionSchedule = ConstructionSchedule::factory()->create();
    $businessSchedule = BusinessSchedule::factory()->create();
    $notice = InternalNotice::factory()->create();
    $returnTo = '/schedule-overview?date=2026-05-13';

    $this->actingAs($admin)
        ->get(route('construction-schedules.create', ['return_to' => $returnTo]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->get(route('construction-schedules.edit', [
            'construction_schedule' => $constructionSchedule,
            'return_to' => $returnTo,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->get(route('business-schedules.create', ['return_to' => $returnTo]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->get(route('business-schedules.edit', [
            'business_schedule' => $businessSchedule,
            'return_to' => $returnTo,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->get(route('internal-notices.create', ['return_to' => $returnTo]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('internal-notices/form')
            ->where('returnTo', $returnTo));

    $this->actingAs($admin)
        ->get(route('internal-notices.edit', [
            'internal_notice' => $notice,
            'return_to' => $returnTo,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('internal-notices/form')
            ->where('returnTo', $returnTo));
});

it('ignores non schedule return urls on form pages', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('construction-schedules.create', ['return_to' => '/admin/users']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('returnTo', null));
});

it('redirects construction schedule saves back to schedule overview when requested', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'location' => '更新前の現場',
    ]);
    $returnTo = '/schedule-overview?date=2026-05-13';

    $this->actingAs($admin)
        ->post(route('construction-schedules.store', ['return_to' => $returnTo]), [
            'scheduled_on' => '2026-05-13',
            'status' => 'scheduled',
            'location' => '新規現場',
        ])
        ->assertRedirect($returnTo);

    $this->actingAs($admin)
        ->put(route('construction-schedules.update', [
            'construction_schedule' => $schedule,
            'return_to' => $returnTo,
        ]), [
            'scheduled_on' => '2026-05-13',
            'status' => 'scheduled',
            'location' => '更新後の現場',
        ])
        ->assertRedirect($returnTo);
});

it('redirects business schedule saves back to schedule overview when requested', function (): void {
    $admin = User::factory()->admin()->create();
    $schedule = BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'location' => '更新前の会議室',
        'content' => '更新前の内容',
    ]);
    $returnTo = '/schedule-overview?date=2026-05-13';

    $this->actingAs($admin)
        ->post(route('business-schedules.store', ['return_to' => $returnTo]), [
            'scheduled_on' => '2026-05-13',
            'location' => '新規会議室',
            'content' => '新規内容',
        ])
        ->assertRedirect($returnTo);

    $this->actingAs($admin)
        ->put(route('business-schedules.update', [
            'business_schedule' => $schedule,
            'return_to' => $returnTo,
        ]), [
            'scheduled_on' => '2026-05-13',
            'location' => '更新後の会議室',
            'content' => '更新後の内容',
        ])
        ->assertRedirect($returnTo);
});

it('redirects internal notice saves back to schedule overview when requested', function (): void {
    $admin = User::factory()->admin()->create();
    $notice = InternalNotice::factory()->create([
        'scheduled_on' => '2026-05-13',
        'title' => '更新前の連絡',
        'content' => '更新前の内容',
    ]);
    $returnTo = '/schedule-overview?date=2026-05-13';

    $this->actingAs($admin)
        ->post(route('internal-notices.store', ['return_to' => $returnTo]), [
            'scheduled_on' => '2026-05-13',
            'title' => '新規連絡',
            'content' => '新規内容',
        ])
        ->assertRedirect($returnTo);

    $this->actingAs($admin)
        ->put(route('internal-notices.update', [
            'internal_notice' => $notice,
            'return_to' => $returnTo,
        ]), [
            'scheduled_on' => '2026-05-13',
            'title' => '更新後の連絡',
            'content' => '更新後の内容',
        ])
        ->assertRedirect($returnTo);
});
