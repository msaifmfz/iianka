import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    EyeOff,
    Pencil,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    index as userIndex,
    store as userStore,
    update as userUpdate,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { RequiredBadge } from '@/components/ui/label';

type ManagedUser = {
    id: number;
    name: string;
    login_id: string;
    email: string | null;
    role: UserRole;
    is_hidden_from_workers: boolean;
    is_current_user: boolean;
};

type Props = {
    managedUser: ManagedUser | null;
};

type UserForm = {
    _method: 'put' | '';
    name: string;
    login_id: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: UserRole;
    is_hidden_from_workers: boolean;
};

type UserRole = 'admin' | 'editor' | 'viewer';

const roleOptions: {
    value: UserRole;
    label: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        value: 'admin',
        label: '管理者',
        description: 'すべての操作、ユーザー管理、監査ログを利用できます。',
        icon: ShieldCheck,
    },
    {
        value: 'editor',
        label: '編集者',
        description: '予定、出勤、案内図などを管理できます。',
        icon: Pencil,
    },
    {
        value: 'viewer',
        label: '閲覧者',
        description: 'すべての業務データを閲覧できます。編集はできません。',
        icon: UserRound,
    },
];

export default function AdminUserForm({ managedUser }: Props) {
    const { data, setData, post, processing, errors } = useForm<UserForm>({
        _method: managedUser ? 'put' : '',
        name: managedUser?.name ?? '',
        login_id: managedUser?.login_id ?? '',
        email: managedUser?.email ?? '',
        password: '',
        password_confirmation: '',
        role: managedUser?.role ?? 'viewer',
        is_hidden_from_workers: managedUser?.is_hidden_from_workers ?? false,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(managedUser ? userUpdate.url(managedUser.id) : userStore.url());
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
                            <FormField
                                label="名前"
                                required
                                error={errors.name}
                            >
                                <Input
                                    required
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    autoComplete="name"
                                />
                            </FormField>
                            <FormField
                                label="ログインID"
                                required
                                error={errors.login_id}
                            >
                                <Input
                                    required
                                    value={data.login_id}
                                    onChange={(event) =>
                                        setData(
                                            'login_id',
                                            event.target.value.toLowerCase(),
                                        )
                                    }
                                    autoComplete="username"
                                    placeholder="例）0001"
                                />
                            </FormField>
                            <FormField
                                label="メールアドレス"
                                error={errors.email}
                            >
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    autoComplete="email"
                                    placeholder="未設定でも可"
                                />
                            </FormField>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label={
                                        managedUser
                                            ? '新しいパスワード'
                                            : 'パスワード'
                                    }
                                    required={!managedUser}
                                    error={errors.password}
                                >
                                    <Input
                                        type="password"
                                        required={!managedUser}
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
                                </FormField>
                                <FormField
                                    label="パスワード確認"
                                    required={!managedUser}
                                    error={errors.password_confirmation}
                                >
                                    <Input
                                        type="password"
                                        required={!managedUser}
                                        value={data.password_confirmation}
                                        onChange={(event) =>
                                            setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    権限
                                    <RequiredBadge />
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {roleOptions.map((role) => {
                                    const RoleIcon = role.icon;
                                    const selected = data.role === role.value;
                                    const disabled =
                                        managedUser?.is_current_user === true &&
                                        role.value !== 'admin';

                                    return (
                                        <label
                                            key={role.value}
                                            className={`flex items-start gap-3 rounded-lg border p-4 text-sm transition dark:border-neutral-800 ${selected ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30' : ''} ${disabled ? 'opacity-60' : ''}`}
                                        >
                                            <input
                                                type="radio"
                                                name="role"
                                                value={role.value}
                                                required
                                                checked={selected}
                                                disabled={disabled}
                                                onChange={() =>
                                                    setData('role', role.value)
                                                }
                                                className="mt-1"
                                            />
                                            <span>
                                                <span className="flex items-center gap-2 font-semibold">
                                                    <RoleIcon className="size-4 text-amber-600" />
                                                    {role.label}
                                                </span>
                                                <span className="mt-1 block text-muted-foreground">
                                                    {role.description}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                                {managedUser?.is_current_user && (
                                    <p className="text-xs text-muted-foreground">
                                        自分自身の管理者権限はここでは解除できません。
                                    </p>
                                )}
                                {errors.role && (
                                    <p className="text-xs text-destructive">
                                        {errors.role}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>表示</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <label
                                    className={`flex items-start gap-3 rounded-lg border p-4 text-sm transition dark:border-neutral-800 ${data.is_hidden_from_workers ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={data.is_hidden_from_workers}
                                        onChange={(event) =>
                                            setData(
                                                'is_hidden_from_workers',
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    <span>
                                        <span className="flex items-center gap-2 font-semibold">
                                            <EyeOff className="size-4 text-rose-600" />
                                            作業員向け画面では非表示
                                        </span>
                                        <span className="mt-1 block text-muted-foreground">
                                            予定表と出勤管理の作業員向け表示から除外します。
                                        </span>
                                    </span>
                                </label>
                                {errors.is_hidden_from_workers && (
                                    <p className="text-xs text-destructive">
                                        {errors.is_hidden_from_workers}
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
                                    <Link href={userIndex()}>キャンセル</Link>
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
            title: 'ユーザー管理',
            href: userIndex(),
        },
    ],
};
