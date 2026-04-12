<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebAuthn;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

use function response;

class WebAuthnRegisterController extends Controller
{
    /**
     * Returns a challenge to be verified by the user device.
     */
    public function createChallenge(AttestationRequest $request): Responsable
    {
        return $request
            ->secureRegistration()
            ->userless()
            ->toCreate();
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response
    {
        $validated = $request->validate([
            'alias' => ['nullable', 'string', 'max:255'],
        ]);

        $request->save($validated);

        $this->auditSuccess('settings.passkeys.created', 'A user registered a passkey.', null, [
            'alias' => $validated['alias'] ?? null,
        ]);

        return response()->noContent();
    }
}
