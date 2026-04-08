import { Transition } from '@headlessui/react';
import { Form, Head, router } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import {
    createPasskey,
    isPasskeySupported,
    passkeyErrorMessage,
} from '@/lib/passkeys';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Passkey = {
    id: string;
    alias: string | null;
    origin: string;
    created_at: string | null;
    disabled_at: string | null;
};

type Props = {
    canManageTwoFactor?: boolean;
    passkeys: Passkey[];
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function Security({
    canManageTwoFactor = false,
    passkeys,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const [passkeyAlias, setPasskeyAlias] = useState<string>('');
    const [passkeySupported, setPasskeySupported] = useState<boolean | null>(
        null,
    );
    const [passkeyProcessing, setPasskeyProcessing] = useState<boolean>(false);
    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const [passkeySaved, setPasskeySaved] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        setPasskeySupported(isPasskeySupported());
    }, []);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    const addPasskey = async (): Promise<void> => {
        setPasskeyError(null);
        setPasskeySaved(false);
        setPasskeyProcessing(true);

        try {
            await createPasskey(passkeyAlias.trim() || undefined);
            setPasskeyAlias('');
            setPasskeySaved(true);
            router.reload({ only: ['passkeys'] });
        } catch (error) {
            setPasskeyError(passkeyErrorMessage(error));
        } finally {
            setPasskeyProcessing(false);
        }
    };

    const formattedDate = (value: string | null): string =>
        value
            ? new Intl.DateTimeFormat(undefined, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value))
            : '日付不明';

    return (
        <>
            <Head title="セキュリティ設定" />

            <h1 className="sr-only">セキュリティ設定</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="パスワードを更新"
                    description="アカウントを安全に保つため、長くランダムなパスワードを使用してください"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    現在のパスワード
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="現在のパスワード"
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    新しいパスワード
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="新しいパスワード"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    パスワードの確認
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="パスワードの確認"
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    パスワードを保存
                                </Button>

                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-neutral-600">
                                        保存しました
                                    </p>
                                </Transition>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="パスキー"
                    description="対応デバイスでは、Touch ID、Face ID、Windows Hello、またはセキュリティキーでサインインできます"
                />

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="passkey_alias">
                            パスキー名（任意）
                        </Label>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="passkey_alias"
                                value={passkeyAlias}
                                onChange={(event) =>
                                    setPasskeyAlias(event.target.value)
                                }
                                placeholder="仕事用ノートパソコン、スマートフォン、またはセキュリティキー"
                                disabled={passkeyProcessing}
                            />
                            <Button
                                type="button"
                                className="sm:w-auto"
                                disabled={
                                    passkeySupported !== true ||
                                    passkeyProcessing
                                }
                                onClick={() => void addPasskey()}
                            >
                                {passkeyProcessing ? <Spinner /> : <KeyRound />}
                                パスキーを追加
                            </Button>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            パスワードでのログインは利用可能な状態にしておいてください。信頼でき、自分でロック解除できるデバイスにのみパスキーを追加してください。
                        </p>
                        {passkeySupported === false && (
                            <p className="text-sm text-muted-foreground">
                                このブラウザまたはデバイスは、ユーザー確認付きのパスキーに対応していません。
                            </p>
                        )}
                        {passkeySaved && (
                            <p className="text-sm text-neutral-600">
                                パスキーを保存しました。
                            </p>
                        )}
                        <InputError message={passkeyError ?? undefined} />
                    </div>

                    {passkeys.length > 0 ? (
                        <div className="divide-y rounded-lg border">
                            {passkeys.map((passkey) => (
                                <div
                                    key={passkey.id}
                                    className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {passkey.alias ||
                                                '名前のないパスキー'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            追加日:{' '}
                                            {formattedDate(passkey.created_at)}{' '}
                                            送信元: {passkey.origin}
                                        </p>
                                    </div>
                                    <Form
                                        {...SecurityController.destroyPasskey.form(
                                            passkey.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                削除
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            パスキーはまだ登録されていません。
                        </p>
                    )}
                </div>
            </div>

            {canManageTwoFactor && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="二要素認証"
                        description="二要素認証の設定を管理します"
                    />
                    {twoFactorEnabled ? (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                ログイン時に安全なランダム暗証番号の入力を求められます。この暗証番号はスマートフォンのTOTP対応アプリから取得できます。
                            </p>

                            <div className="relative inline">
                                <Form {...disable.form()}>
                                    {({ processing }) => (
                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            二要素認証を無効化
                                        </Button>
                                    )}
                                </Form>
                            </div>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <p className="text-sm text-muted-foreground">
                                二要素認証を有効にすると、ログイン時に安全な暗証番号の入力を求められます。この暗証番号はスマートフォンのTOTP対応アプリから取得できます。
                            </p>

                            <div>
                                {hasSetupData ? (
                                    <Button
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck />
                                        設定を続行
                                    </Button>
                                ) : (
                                    <Form
                                        {...enable.form()}
                                        onSuccess={() =>
                                            setShowSetupModal(true)
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                二要素認証を有効化
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={errors}
                    />
                </div>
            )}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'セキュリティ設定',
            href: edit(),
        },
    ],
};
