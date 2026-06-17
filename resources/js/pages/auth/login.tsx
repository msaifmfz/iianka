import { Form, Head } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status }: Props) {
    const {
        verify,
        isLoading: passkeyProcessing,
        error: passkeyError,
        isSupported: passkeySupported,
    } = usePasskeyVerify({
        autofill: true,
        onSuccess: (response) => {
            window.location.assign(response.redirect ?? '/dashboard');
        },
    });

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
                                <Label htmlFor="login_id" required>
                                    ログインID
                                </Label>
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
                                <Label htmlFor="password" required>
                                    パスワード
                                </Label>
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
                                    !passkeySupported || passkeyProcessing
                                }
                                onClick={() => void verify()}
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
                                        <p>
                                            別のパスキーを使うか、ログインIDとパスワードでログインしてください。
                                        </p>
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
