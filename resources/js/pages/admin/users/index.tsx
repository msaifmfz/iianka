import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    KeyRound,
    Pencil,
    Plus,
    Search,
    Trash2,
    EyeOff,
} from 'lucide-react';
import { useState } from 'react';
import {
    create as userCreate,
    destroy as userDestroy,
    edit as userEdit,
    index as userIndex,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ManagedUser = {
    id: number;
    name: string;
    login_id: string;
    email: string | null;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    role: UserRole;
    role_label: string;
    is_hidden_from_workers: boolean;
    created_at: string | null;
    updated_at: string | null;
    is_current_user: boolean;
    can_delete: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedUsers = {
    data: ManagedUser[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

type Props = {
    users: PaginatedUsers;
    filters: {
        search: string;
        role: string;
    };
    stats: {
        total: number;
        admins: number;
        editors: number;
        viewers: number;
        secured: number;
    };
};

type UserRole = 'admin' | 'editor' | 'viewer';

const roleFilters = [
    { label: 'すべて', value: 'all' },
    { label: '管理者', value: 'admin' },
    { label: '編集者', value: 'editor' },
    { label: '閲覧者', value: 'viewer' },
];

function formatDate(value: string | null) {
    if (!value) {
        return '未設定';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
}

function paginationLabel(label: string) {
    return label.replace('&laquo;', '前へ').replace('&raquo;', '次へ');
}

function RoleFilter({
    label,
    value,
    active,
    search,
}: {
    label: string;
    value: string;
    active: boolean;
    search: string;
}) {
    return (
        <Link
            href={userIndex({
                query: {
                    role: value,
                    search,
                },
            })}
            className={`rounded-full px-4 py-2 text-sm font-semibold transition ${active ? 'bg-neutral-950 text-white dark:bg-white dark:text-neutral-950' : 'bg-white text-neutral-700 ring-1 ring-neutral-200 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-800'}`}
            preserveScroll
        >
            {label}
        </Link>
    );
}

function UserAvatar({ name }: { name: string }) {
    return (
        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-sm font-bold text-white dark:bg-white dark:text-neutral-950">
            {name.slice(0, 1).toUpperCase()}
        </div>
    );
}

function UserIdentity({ user }: { user: ManagedUser }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <UserAvatar name={user.name} />
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-semibold">{user.name}</p>
                    {user.is_current_user && (
                        <Badge variant="secondary">自分</Badge>
                    )}
                    {user.is_hidden_from_workers && (
                        <Badge variant="outline">
                            <EyeOff className="size-3" />
                            作業員非表示
                        </Badge>
                    )}
                </div>
                <p className="truncate text-sm text-muted-foreground">
                    {user.login_id}
                </p>
                <p className="truncate text-muted-foreground">
                    {user.email ?? 'メール未登録'}
                </p>
            </div>
        </div>
    );
}

function UserRoleBadge({ user }: { user: ManagedUser }) {
    const variant = user.role === 'admin' ? 'default' : 'outline';

    return <Badge variant={variant}>{user.role_label}</Badge>;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function UserSecurityBadges({ user }: { user: ManagedUser }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Badge
                variant={
                    user.email === null
                        ? 'outline'
                        : user.email_verified_at
                          ? 'secondary'
                          : 'outline'
                }
            >
                <BadgeCheck className="size-3" />
                {user.email === null
                    ? 'メール未登録'
                    : user.email_verified_at
                      ? 'メール認証済み'
                      : '未認証'}
            </Badge>
            <Badge
                variant={user.two_factor_confirmed_at ? 'secondary' : 'outline'}
            >
                <KeyRound className="size-3" />
                {user.two_factor_confirmed_at ? '2FA 有効' : '2FA 未設定'}
            </Badge>
        </div>
    );
}

function UserActions({ user }: { user: ManagedUser }) {
    return (
        <div className="flex gap-2 sm:justify-end">
            <Button
                asChild
                variant="outline"
                size="sm"
                className="flex-1 sm:flex-none"
            >
                <Link href={userEdit(user.id)}>
                    <Pencil className="size-4" />
                    編集
                </Link>
            </Button>
            <Button
                asChild
                variant="destructive"
                size="sm"
                disabled={!user.can_delete}
                className="flex-1 sm:flex-none"
            >
                <Link
                    href={userDestroy(user.id)}
                    method="delete"
                    as="button"
                    onBefore={() => confirm(`${user.name} を削除しますか？`)}
                >
                    <Trash2 className="size-4" />
                    削除
                </Link>
            </Button>
        </div>
    );
}

export default function AdminUsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            userIndex.url(),
            {
                search,
                role: filters.role,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title="ユーザー管理" />
            <div className="mx-auto max-w-7xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Admin User Management
                        </p>
                        <h1 className="text-2xl font-bold">ユーザー管理</h1>
                    </div>
                    <Button asChild>
                        <Link href={userCreate()}>
                            <Plus className="size-4" />
                            ユーザーを追加
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="gap-4 border-b dark:border-neutral-800">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <CardTitle>アカウント一覧</CardTitle>
                            </div>
                            <form
                                onSubmit={submit}
                                className="flex w-full gap-2 sm:w-auto"
                            >
                                <div className="relative flex-1 sm:w-72">
                                    <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="名前・ログインID・メールで検索"
                                        className="pl-9"
                                    />
                                </div>
                                <Button type="submit">検索</Button>
                            </form>
                        </div>
                        <div className="flex flex-wrap gap-2 py-3">
                            {roleFilters.map((role) => (
                                <RoleFilter
                                    key={role.value}
                                    label={role.label}
                                    value={role.value}
                                    active={filters.role === role.value}
                                    search={filters.search}
                                />
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="grid gap-3 p-3 md:hidden">
                            {users.data.map((user) => (
                                <article
                                    key={user.id}
                                    className="rounded-2xl border bg-card p-4 shadow-xs transition hover:bg-neutral-50/80 dark:border-neutral-800 dark:hover:bg-neutral-900/60"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <UserIdentity user={user} />
                                        <UserRoleBadge user={user} />
                                    </div>

                                    <dl className="mt-4 grid gap-3 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <dt className="text-muted-foreground">
                                                作成日
                                            </dt>
                                            <dd className="font-medium">
                                                {formatDate(user.created_at)}
                                            </dd>
                                        </div>
                                        {/* <div className="grid gap-2"> */}
                                        {/*     <dt className="text-muted-foreground"> */}
                                        {/*         セキュリティ */}
                                        {/*     </dt> */}
                                        {/*     <dd> */}
                                        {/*         <UserSecurityBadges */}
                                        {/*             user={user} */}
                                        {/*         /> */}
                                        {/*     </dd> */}
                                        {/* </div> */}
                                    </dl>

                                    <div className="mt-4 border-t pt-4 dark:border-neutral-800">
                                        <UserActions user={user} />
                                    </div>
                                </article>
                            ))}
                        </div>

                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-xs font-semibold text-muted-foreground uppercase dark:bg-neutral-950">
                                    <tr>
                                        <th className="px-5 py-3">ユーザー</th>
                                        <th className="px-5 py-3">権限</th>
                                        {/* <th className="px-5 py-3"> */}
                                        {/*     セキュリティ */}
                                        {/* </th> */}
                                        <th className="px-5 py-3">作成日</th>
                                        <th className="px-5 py-3 text-right">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y dark:divide-neutral-800">
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="transition hover:bg-neutral-50/80 dark:hover:bg-neutral-900/60"
                                        >
                                            <td className="px-5 py-4">
                                                <UserIdentity user={user} />
                                            </td>
                                            <td className="px-5 py-4">
                                                <UserRoleBadge user={user} />
                                            </td>
                                            {/* <td className="px-5 py-4"> */}
                                            {/*     <UserSecurityBadges */}
                                            {/*         user={user} */}
                                            {/*     /> */}
                                            {/* </td> */}
                                            <td className="px-5 py-4 text-muted-foreground">
                                                {formatDate(user.created_at)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <UserActions user={user} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {users.data.length === 0 && (
                            <div className="p-10 text-center text-sm text-muted-foreground">
                                条件に一致するユーザーはいません。
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <p>
                        {users.total > 0
                            ? `${users.from} - ${users.to} / ${users.total} 件`
                            : '0 件'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {users.links.map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                asChild={link.url !== null}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                            >
                                {link.url ? (
                                    <Link href={link.url} preserveScroll>
                                        {paginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span>{paginationLabel(link.label)}</span>
                                )}
                            </Button>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
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
