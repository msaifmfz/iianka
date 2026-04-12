<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Laravel\Fortify\Features;

class SecurityController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? [new Middleware('password.confirm', only: ['edit'])]
                : [];
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => false, // Features::canManageTwoFactorAuthentication(),
            'passkeys' => $this->passkeys($request),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        $this->auditSuccess('settings.password.updated', 'A user updated their password.', $request->user());

        return back();
    }

    /**
     * Remove one of the user's passkeys.
     */
    public function destroyPasskey(Request $request, string $passkey): RedirectResponse
    {
        $credential = $request->user()->webAuthnCredentials()->whereKey($passkey)->firstOrFail();

        $this->auditSuccess('settings.passkeys.deleted', 'A user removed a passkey.', null, [
            'passkey_id' => $credential->getKey(),
            'alias' => $credential->alias,
            'origin' => $credential->origin,
        ]);

        $credential->delete();

        return back();
    }

    /**
     * @return Collection<int, array{id: string, alias: string|null, origin: string, created_at: string|null, disabled_at: string|null}>
     */
    private function passkeys(Request $request): Collection
    {
        return $request->user()
            ->webAuthnCredentials()
            ->latest()
            ->get()
            ->map(fn (WebAuthnCredential $credential): array => [
                'id' => $credential->getKey(),
                'alias' => $credential->alias,
                'origin' => $credential->origin,
                'created_at' => $credential->created_at?->toISOString(),
                'disabled_at' => $credential->disabled_at?->toISOString(),
            ]);
    }
}
