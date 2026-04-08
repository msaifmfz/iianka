import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    isPasskeyAutofillable,
    isPasskeySupported,
    loginWithPasskey,
    passkeyErrorMessage,
} from '@/lib/passkeys';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status }: Props) {
    const rememberInput = useRef<HTMLButtonElement>(null);
    const attemptedAutofill = useRef(false);
    const [passkeySupported, setPasskeySupported] = useState<boolean | null>(
        null,
    );
    const [passkeyProcessing, setPasskeyProcessing] = useState<boolean>(false);
    const [passkeyError, setPasskeyError] = useState<string | null>(null);

    const signInWithPasskey = useCallback(
        async (useAutofill = false): Promise<void> => {
            setPasskeyError(null);
            setPasskeyProcessing(true);

            try {
                const response = await loginWithPasskey({
                    remember:
                        rememberInput.current?.getAttribute('aria-checked') ===
                        'true',
                    useAutofill,
                });

                window.location.assign(response.redirect ?? '/dashboard');
            } catch (error) {
                if (!useAutofill) {
                    setPasskeyError(passkeyErrorMessage(error));
                }
            } finally {
                setPasskeyProcessing(false);
            }
        },
        [],
    );

    useEffect(() => {
        const supported = isPasskeySupported();
        setPasskeySupported(supported);

        if (!supported || attemptedAutofill.current) {
            return;
        }

        attemptedAutofill.current = true;

        void isPasskeyAutofillable().then((autofillable) => {
            if (autofillable) {
                void signInWithPasskey(true);
            }
        });
    }, [signInWithPasskey]);

    return (
        <>
            <Head title="ログイン" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="login_id">ログインID</Label>
                                <Input
                                    id="login_id"
                                    type="text"
                                    name="login_id"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username webauthn"
                                    placeholder="例）0001"
                                />
                                <InputError message={errors.login_id} />
                            </div>

                            <div className="grid gap-2">
                                {/* <div className="flex items-center"> */}
                                {/*     <Label htmlFor="password">パスワード</Label> */}
                                {/*     {canResetPassword && ( */}
                                {/*         <TextLink */}
                                {/*             href={request()} */}
                                {/*             className="ml-auto text-sm" */}
                                {/*             tabIndex={5} */}
                                {/*         > */}
                                {/*             パスワードをお忘れですか？ */}
                                {/*         </TextLink> */}
                                {/*     )} */}
                                {/* </div> */}
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="パスワード"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    ref={rememberInput}
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">
                                    ログイン状態を保持
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                ログイン
                            </Button>

                            <div className="relative">
                                <div className="absolute inset-0 flex items-center">
                                    <span className="w-full border-t" />
                                </div>
                                <div className="relative flex justify-center text-xs uppercase">
                                    <span className="bg-background px-2 text-muted-foreground">
                                        または
                                    </span>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                tabIndex={5}
                                disabled={
                                    passkeySupported !== true ||
                                    passkeyProcessing
                                }
                                onClick={() => void signInWithPasskey()}
                            >
                                {passkeyProcessing ? <Spinner /> : <KeyRound />}
                                パスキーでサインイン
                            </Button>

                            <p className="text-center text-xs text-muted-foreground">
                                このデバイスがパスキーに対応している場合は、Touch
                                ID、Face ID、Windows
                                Hello、またはセキュリティキーを使用できます。
                            </p>

                            {passkeySupported === false && (
                                <p className="text-center text-xs text-muted-foreground">
                                    このブラウザまたはデバイスではパスキーを利用できません。ログインIDとパスワードでログインできます。
                                </p>
                            )}

                            {passkeyError && (
                                <Alert
                                    variant="destructive"
                                    className="border-destructive/40 bg-destructive/5"
                                >
                                    <KeyRound className="size-4" />
                                    <AlertTitle>
                                        パスキーでサインインできませんでした
                                    </AlertTitle>
                                    <AlertDescription>
                                        <p>{passkeyError}</p>
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            アカウント作成は管理者のみ行えます。ログイン情報が必要な場合は管理者へお問い合わせください。
                        </p>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'アカウントにログイン',
    description: 'ログインIDとパスワードを入力してログインしてください',
};
