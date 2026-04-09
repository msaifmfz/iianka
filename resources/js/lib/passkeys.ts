import type { Webpass, WebpassStatic } from '@laragear/webpass/dist/types';
import { login, register } from '@/routes/webauthn';
import { challenge as loginChallenge } from '@/routes/webauthn/login';
import { challenge as registerChallenge } from '@/routes/webauthn/register';

type PasskeyLoginResponse = {
    redirect?: string;
};

type PasskeyResult<TData> = {
    data?: TData;
    success: boolean;
    error?: unknown;
};

type WebpassInstance = Webpass & {
    assert: (
        options?: {
            useAutofill?: boolean;
        },
        request?: {
            body?: {
                remember?: boolean;
            };
        },
    ) => Promise<PasskeyResult<PasskeyLoginResponse>>;
};

let passkeysPromise: Promise<WebpassInstance> | undefined;

async function loadWebpass(): Promise<{
    default: WebpassStatic;
}> {
    return import('@laragear/webpass/dist/webpass');
}

async function getPasskeys(): Promise<WebpassInstance> {
    if (typeof window === 'undefined') {
        throw new Error(
            'Passkey authentication is only available in browsers.',
        );
    }

    passkeysPromise ??= loadWebpass().then(
        ({ default: Webpass }) =>
            Webpass.create({
                findCsrfToken: true,
                routes: {
                    attestOptions: registerChallenge.url(),
                    attest: register.url(),
                    assertOptions: loginChallenge.url(),
                    assert: login.url(),
                },
            }) as WebpassInstance,
    );

    return passkeysPromise;
}

export function isPasskeySupported(): boolean {
    return typeof window !== 'undefined' && 'PublicKeyCredential' in window;
}

export async function isPasskeyAutofillable(): Promise<boolean> {
    if (typeof window === 'undefined') {
        return false;
    }

    const { default: Webpass } = await loadWebpass();

    return Webpass.isAutofillable();
}

export async function loginWithPasskey(options?: {
    remember?: boolean;
    useAutofill?: boolean;
}): Promise<PasskeyLoginResponse> {
    const passkeys = await getPasskeys();
    const result = await passkeys.assert(
        {
            useAutofill: options?.useAutofill,
        },
        {
            body: {
                remember: options?.remember === true,
            },
        },
    );

    if (!result.success) {
        throw result.error ?? new Error('Passkey authentication failed.');
    }

    return (result.data ?? {}) as PasskeyLoginResponse;
}

export async function createPasskey(alias?: string): Promise<void> {
    const passkeys = await getPasskeys();
    const result = await passkeys.attest(undefined, {
        body: {
            alias,
        },
    });

    if (!result.success) {
        throw result.error ?? new Error('Passkey registration failed.');
    }
}

type PasskeyErrorPayload = {
    message?: string;
};

type PasskeyErrorWithResponse = Error & {
    status?: number;
    data?: PasskeyErrorPayload;
    response?: {
        status?: number;
        _data?: PasskeyErrorPayload;
    };
};

export function passkeyErrorMessage(error: unknown): string {
    if (error instanceof Error && error.name === 'NotAllowedError') {
        return 'パスキー認証がキャンセルされたか、時間切れになりました。もう一度お試しください。';
    }

    if (error instanceof Error && error.name === 'AbortError') {
        return 'パスキー認証を完了できませんでした。もう一度お試しください。';
    }

    if (error instanceof Error && error.name === 'SecurityError') {
        return 'この環境ではパスキー認証を利用できません。ログインIDとパスワードでログインしてください。';
    }

    const errorWithResponse = error as PasskeyErrorWithResponse;
    const responseMessage =
        errorWithResponse.data?.message ??
        errorWithResponse.response?._data?.message;

    if (responseMessage) {
        return responseMessage;
    }

    const responseStatus =
        errorWithResponse.status ?? errorWithResponse.response?.status;

    if (responseStatus === 422) {
        return 'このパスキーではログインできませんでした。別のパスキーを使うか、ログインIDとパスワードでログインしてください。';
    }

    if (error instanceof Error && error.message) {
        return error.message.includes('[POST]')
            ? 'パスキーでログインできませんでした。ログインIDとパスワードでもログインできます。'
            : error.message;
    }

    return 'パスキーでログインできませんでした。ログインIDとパスワードでもログインできます。';
}
