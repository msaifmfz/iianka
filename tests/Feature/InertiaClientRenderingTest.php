<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the login page renders its client payload without contacting an SSR server', function (): void {
    config(['inertia.ssr.ensure_bundle_exists' => false]);
    Http::preventStrayRequests();
    Http::fake(['http://127.0.0.1:13714/render' => Http::response([], 503)]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('auth/login'));

    Http::assertNothingSent();
});

test('authenticated pages render their client payload without contacting an SSR server', function (): void {
    $user = User::factory()->create();
    config(['inertia.ssr.ensure_bundle_exists' => false]);
    Http::preventStrayRequests();
    Http::fake(['http://127.0.0.1:13714/render' => Http::response([], 503)]);

    $this->actingAs($user)->get(route('construction-schedules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('construction-schedules/index'));

    Http::assertNothingSent();
});
