import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck, UserRound } from 'lucide-react';
import {
    index as userIndex,
    store as userStore,
    update as userUpdate,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_current_user: boolean;
};

type Props = {
    managedUser: ManagedUser | null;
};

type UserForm = {
    _method: 'put' | '';
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    is_admin: boolean;
};

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            <span>{label}</span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </label>
    );
}

export default function AdminUserForm({ managedUser }: Props) {
    const { data, setData, post, processing, errors } = useForm<UserForm>({
        _method: managedUser ? 'put' : '',
        name: managedUser?.name ?? '',
        email: managedUser?.email ?? '',
        password: '',
        password_confirmation: '',
        is_admin: managedUser?.is_admin ?? false,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(
            managedUser ? userUpdate.url(managedUser.id) : userStore.url(),
        );
    }

    return (
        <>
            <Head title={managedUser ? 'ユーザー編集' : 'ユーザー追加'} />
            <div className="mx-auto max-w-4xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Admin User Management
                        </p>
                        <h1 className="text-2xl font-bold">
                            {managedUser ? 'ユーザー編集' : 'ユーザー追加'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={userIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>基本情報</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <Field label="名前" error={errors.name}>
                                <Input
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    autoComplete="name"
                                />
                            </Field>
                            <Field label="メールアドレス" error={errors.email}>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    autoComplete="email"
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={
                                        managedUser
                                            ? '新しいパスワード'
                                            : 'パスワード'
                                    }
                                    error={errors.password}
                                >
                                    <Input
                                        type="password"
                                        value={data.password}
                                        onChange={(event) =>
                                            setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                        placeholder={
                                            managedUser
                                                ? '変更する場合のみ入力'
                                                : ''
                                        }
                                    />
                                </Field>
                                <Field
                                    label="パスワード確認"
                                    error={errors.password_confirmation}
                                >
                                    <Input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(event) =>
                                            setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>権限</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <label
                                    className={`flex items-start gap-3 rounded-2xl border p-4 text-sm transition dark:border-neutral-800 ${data.is_admin ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={data.is_admin}
                                        disabled={
                                            managedUser?.is_current_user ===
                                            true
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'is_admin',
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    <span>
                                        <span className="flex items-center gap-2 font-semibold">
                                            {data.is_admin ? (
                                                <ShieldCheck className="size-4 text-amber-600" />
                                            ) : (
                                                <UserRound className="size-4" />
                                            )}
                                            管理者として設定
                                        </span>
                                        <span className="mt-1 block text-muted-foreground">
                                            予定、案内図、ユーザーを管理できます。
                                        </span>
                                    </span>
                                </label>
                                {managedUser?.is_current_user && (
                                    <p className="text-xs text-muted-foreground">
                                        自分自身の管理者権限はここでは解除できません。
                                    </p>
                                )}
                                {errors.is_admin && (
                                    <p className="text-xs text-destructive">
                                        {errors.is_admin}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="space-y-3 p-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing ? '保存中...' : '保存'}
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={userIndex()}>
                                        キャンセル
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminUserForm.layout = {
    breadcrumbs: [
        {
            title: 'メニュー',
            href: dashboard(),
        },
        {
            title: 'ユーザー管理',
            href: userIndex(),
        },
    ],
};
