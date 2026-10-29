<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;

mutates(HandleInertiaRequests::class);

test('current asset versions allow Inertia visits while stale versions trigger a full reload', function (): void {
    config(['app.asset_url' => 'https://assets.example.test/release-2']);
    $user = User::factory()->create();
    $url = route('construction-schedules.index');

    $initial = $this->actingAs($user)->get($url)->assertOk();
    $version = $initial->viewData('page')['version'];

    expect($version)->toBeString();
    expect($version)->not->toBeEmpty();

    $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->assertOk()
        ->assertJsonPath('version', $version);

    $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale-version'])
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', $url);
});
