<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Passkey;

test('password login still works', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login_id' => $user->login_id,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('passkey registration options require authentication', function (): void {
    $this->getJson(route('passkey.registration-options'))
        ->assertUnauthorized();
});

test('authenticated users can request passkey registration options', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->getJson(route('passkey.registration-options'))
        ->assertOk();
});

test('security page includes registered passkeys', function (): void {
    $user = User::factory()->create();

    createPasskeyFor($user, 'Work laptop');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/security')
            ->has('passkeys', 1)
            ->where('passkeys.0.name', 'Work laptop'),
        );
});

test('users can remove only their own passkeys', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $passkey = createPasskeyFor($user, 'Phone');
    $otherPasskey = createPasskeyFor($otherUser, 'Other phone');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->deleteJson(route('passkey.destroy', $otherPasskey->getKey()))
        ->assertForbidden();

    expect(Passkey::query()->whereKey($otherPasskey->getKey())->exists())->toBeTrue();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->deleteJson(route('passkey.destroy', $passkey->getKey()));

    expect(Passkey::query()->whereKey($passkey->getKey())->exists())->toBeFalse();
});

function createPasskeyFor(User $user, string $name): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => fake()->unique()->sha256(),
        'credential' => json_encode([
            'type' => 'public-key',
            'id' => base64_encode(random_bytes(32)),
            'rawId' => base64_encode(random_bytes(32)),
            'response' => [
                'clientDataJSON' => base64_encode('{}'),
                'attestationObject' => base64_encode('{}'),
            ],
        ]),
    ]);
}
