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
