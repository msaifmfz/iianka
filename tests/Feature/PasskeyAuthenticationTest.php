<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laragear\WebAuthn\Models\WebAuthnCredential;

test('password login still works with webauthn provider fallback', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login_id' => $user->login_id,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('passkey registration challenge requires authentication', function (): void {
    $this->postJson(route('webauthn.register.challenge'))
        ->assertUnauthorized();
});

test('authenticated users can request a passkey registration challenge', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->postJson(route('webauthn.register.challenge'))
        ->assertOk()
        ->assertJsonPath('authenticatorSelection.userVerification', 'required')
        ->assertJsonPath('authenticatorSelection.residentKey', 'required');
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
            ->where('passkeys.0.alias', 'Work laptop'),
        );
});

test('users can remove only their own passkeys', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $passkey = createPasskeyFor($user, 'Phone');
    $otherPasskey = createPasskeyFor($otherUser, 'Other phone');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->delete(route('passkeys.destroy', $otherPasskey->getKey()))
        ->assertNotFound();

    expect(WebAuthnCredential::query()->whereKey($otherPasskey->getKey())->exists())->toBeTrue();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
        ->delete(route('passkeys.destroy', $passkey->getKey()))
        ->assertRedirect();

    expect(WebAuthnCredential::query()->whereKey($passkey->getKey())->exists())->toBeFalse();
});

function createPasskeyFor(User $user, string $alias): WebAuthnCredential
{
    return $user->webAuthnCredentials()->forceCreate([
        'id' => Str::random(32),
        'user_id' => (string) Str::uuid(),
        'alias' => $alias,
        'counter' => 0,
        'rp_id' => 'localhost',
        'origin' => 'http://localhost',
        'transports' => ['internal'],
        'aaguid' => null,
        'public_key' => 'test-public-key',
        'attestation_format' => 'none',
        'certificates' => [],
    ]);
}
