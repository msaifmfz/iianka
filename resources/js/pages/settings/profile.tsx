import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="プロフィール設定" />

            <h1 className="sr-only">プロフィール設定</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="プロフィール情報"
                    description="名前を更新できます。メールアドレスは任意です。"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    名前
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="氏名"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">メールアドレス</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    autoComplete="username"
                                    placeholder="メールアドレス（任意）"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />

                                <p className="text-sm text-muted-foreground">
                                    メールアドレスがない場合、パスワード再設定は管理者対応になります。
                                </p>
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            メールアドレスが未認証です。{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                認証メールを再送信するにはこちらをクリックしてください。
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                新しい認証リンクをメールアドレスへ送信しました。
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div>
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {processing
                                        ? 'プロフィールを保存中...'
                                        : 'プロフィールを保存'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {/* <DeleteUser /> */}
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'プロフィール設定',
            href: edit(),
        },
    ],
};
