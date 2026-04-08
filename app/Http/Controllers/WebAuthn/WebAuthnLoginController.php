<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebAuthn;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function response;

class WebAuthnLoginController extends Controller
{
    /**
     * Returns the challenge to assertion.
     */
    public function createChallenge(AssertionRequest $request): Responsable
    {
        $request->validate(['email' => ['sometimes', 'email', 'string']]);

        return $request->secureLogin()->toVerify(null);
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): JsonResponse|Response
    {
        $user = $request->login();

        return $user instanceof WebAuthnAuthenticatable
            ? response()->json(['redirect' => route('dashboard', absolute: false)])
            : response()->json([
                'message' => 'このパスキーではログインできませんでした。別のパスキーを使うか、メールアドレスとパスワードでログインしてください。',
            ], 422);
    }
}
