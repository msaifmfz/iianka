import { Head, Link, router } from '@inertiajs/react';
import { FileSearch, Search } from 'lucide-react';
import { useState } from 'react';
import { index as auditLogIndex } from '@/actions/App/Http/Controllers/Admin/AuditLogController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type AuditActor = {
    id: number;
    name: string;
    login_id: string | null;
    email: string | null;
};

type AuditLog = {
    id: number;
    event: string;
    event_label: string;
    outcome: string;
    outcome_label: string;
    description: string | null;
    actor: AuditActor | null;
    actor_type: string | null;
    subject_type: string | null;
    subject_type_label: string;
    subject_id: string | null;
    subject_label: string | null;
    ip_address: string | null;
    method: string | null;
    url: string | null;
    route_name: string | null;
    request_id: string | null;
    metadata: Record<string, unknown>;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedLogs = {
    data: AuditLog[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Filters = {
    search: string;
    event: string;
    outcome: string;
    actor_user_id: string;
    subject_type: string;
    ip_address: string;
    date_from: string;
    date_to: string;
};

type Props = {
    logs: PaginatedLogs;
    filters: Filters;
    options: {
        events: FilterOption[];
        outcomes: FilterOption[];
        subjectTypes: FilterOption[];
    };
};

type FilterOption = {
    value: string;
    label: string;
};

function formatDateTime(value: string | null) {
    if (!value) {
        return '未記録';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}

function shortClassName(value: string | null) {
    if (!value) {
        return 'なし';
    }

    return value.split('\\').pop() ?? value;
}

function paginationLabel(label: string) {
    return label.replace('&laquo;', '前へ').replace('&raquo;', '次へ');
}

function outcomeVariant(outcome: string) {
    if (outcome === 'success') {
        return 'secondary' as const;
    }

    if (outcome === 'failure') {
        return 'destructive' as const;
    }

    return 'outline' as const;
}

function metadataPreview(metadata: Record<string, unknown>) {
    const keys = Object.keys(metadata);

    if (keys.length === 0) {
        return '追加情報なし';
    }

    return JSON.stringify(metadata, null, 2);
}

export default function AuditLogsIndex({ logs, filters, options }: Props) {
    const [form, setForm] = useState(filters);

    function updateFilter(key: keyof Filters, value: string) {
        setForm((current) => ({
            ...current,
            [key]: value,
        }));
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            auditLogIndex.url(),
            Object.fromEntries(
                Object.entries(form).filter(([, value]) => value !== ''),
            ),
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    return (
        <>
            <Head title="監査ログ" />
            <div className="mx-auto max-w-7xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            監査ログ
                        </p>
                        <h1 className="text-2xl font-bold">監査ログ</h1>
                    </div>
                </div>

                <Card>
                    <CardHeader className="gap-4 border-b dark:border-neutral-800">
                        <div className="flex items-center gap-2">
                            <FileSearch className="size-5 text-muted-foreground" />
                            <CardTitle>操作履歴</CardTitle>
                        </div>
                        <form onSubmit={submit} className="grid gap-3">
                            <div className="grid gap-3 md:grid-cols-4">
                                <div className="relative md:col-span-2">
                                    <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={form.search}
                                        onChange={(event) =>
                                            updateFilter(
                                                'search',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="イベント・対象・IP・リクエストIDで検索"
                                        className="pl-9"
                                    />
                                </div>
                                <Input
                                    value={form.ip_address}
                                    onChange={(event) =>
                                        updateFilter(
                                            'ip_address',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="IPアドレス"
                                />
                                <Input
                                    value={form.actor_user_id}
                                    onChange={(event) =>
                                        updateFilter(
                                            'actor_user_id',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="実行ユーザーID"
                                />
                            </div>

                            <div className="grid gap-3 md:grid-cols-5">
                                <select
                                    value={form.event}
                                    onChange={(event) =>
                                        updateFilter(
                                            'event',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="">すべての操作</option>
                                    {options.events.map((event) => (
                                        <option
                                            key={event.value}
                                            value={event.value}
                                        >
                                            {event.label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={form.outcome}
                                    onChange={(event) =>
                                        updateFilter(
                                            'outcome',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="">すべての結果</option>
                                    {options.outcomes.map((outcome) => (
                                        <option
                                            key={outcome.value}
                                            value={outcome.value}
                                        >
                                            {outcome.label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    value={form.subject_type}
                                    onChange={(event) =>
                                        updateFilter(
                                            'subject_type',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="">すべての対象</option>
                                    {options.subjectTypes.map((subjectType) => (
                                        <option
                                            key={subjectType.value}
                                            value={subjectType.value}
                                        >
                                            {subjectType.label}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    type="datetime-local"
                                    step="1"
                                    value={form.date_from}
                                    onChange={(event) =>
                                        updateFilter(
                                            'date_from',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    type="datetime-local"
                                    step="1"
                                    value={form.date_to}
                                    onChange={(event) =>
                                        updateFilter(
                                            'date_to',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex flex-wrap gap-2 py-2">
                                <Button type="submit">検索</Button>
                                <Button asChild variant="outline">
                                    <Link href={auditLogIndex()}>
                                        フィルター解除
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="hidden overflow-x-auto lg:block">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-xs font-semibold text-muted-foreground uppercase dark:bg-neutral-950">
                                    <tr>
                                        <th className="px-5 py-3">日時</th>
                                        <th className="px-5 py-3">イベント</th>
                                        <th className="px-5 py-3">実行者</th>
                                        <th className="px-5 py-3">対象</th>
                                        <th className="px-5 py-3">
                                            リクエスト
                                        </th>
                                        <th className="px-5 py-3">詳細</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y dark:divide-neutral-800">
                                    {logs.data.map((log) => (
                                        <tr
                                            key={log.id}
                                            className="align-top transition hover:bg-neutral-50/80 dark:hover:bg-neutral-900/60"
                                        >
                                            <td className="px-5 py-4 text-muted-foreground">
                                                {formatDateTime(log.created_at)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="grid gap-2">
                                                    <Badge
                                                        variant={outcomeVariant(
                                                            log.outcome,
                                                        )}
                                                        className="w-fit"
                                                    >
                                                        {log.outcome_label}
                                                    </Badge>
                                                    <span className="font-medium">
                                                        {log.event_label}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                {log.actor
                                                    ? `${log.actor.name} (${log.actor.login_id})`
                                                    : shortClassName(
                                                          log.actor_type,
                                                      )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="grid gap-1">
                                                    <span>
                                                        {log.subject_label ??
                                                            log.subject_type_label}
                                                    </span>
                                                    {log.subject_id && (
                                                        <span className="text-xs text-muted-foreground">
                                                            ID: {log.subject_id}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-muted-foreground">
                                                <div className="grid gap-1">
                                                    <span>
                                                        {log.method}{' '}
                                                        {log.route_name ??
                                                            log.url}
                                                    </span>
                                                    <span>
                                                        {log.ip_address ??
                                                            'IPなし'}
                                                    </span>
                                                    <span className="text-xs break-all">
                                                        {log.request_id}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <pre className="max-w-80 overflow-x-auto rounded-md bg-neutral-100 p-3 text-xs whitespace-pre-wrap dark:bg-neutral-900">
                                                    {metadataPreview(
                                                        log.metadata,
                                                    )}
                                                </pre>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-3 p-3 lg:hidden">
                            {logs.data.map((log) => (
                                <article
                                    key={log.id}
                                    className="rounded-md border bg-card p-4 shadow-xs dark:border-neutral-800"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {log.event_label}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatDateTime(log.created_at)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={outcomeVariant(
                                                log.outcome,
                                            )}
                                        >
                                            {log.outcome_label}
                                        </Badge>
                                    </div>
                                    <dl className="mt-4 grid gap-3 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                実行者
                                            </dt>
                                            <dd>
                                                {log.actor
                                                    ? log.actor.name
                                                    : shortClassName(
                                                          log.actor_type,
                                                      )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                対象
                                            </dt>
                                            <dd>
                                                {log.subject_label ??
                                                    log.subject_type_label}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                リクエスト
                                            </dt>
                                            <dd className="break-all">
                                                {log.method} {log.route_name}
                                            </dd>
                                            <dd className="text-muted-foreground">
                                                {log.ip_address}
                                            </dd>
                                        </div>
                                    </dl>
                                </article>
                            ))}
                        </div>

                        {logs.data.length === 0 && (
                            <div className="p-10 text-center text-sm text-muted-foreground">
                                条件に一致する監査ログはありません。
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <p>
                        {logs.total > 0
                            ? `${logs.from} - ${logs.to} / ${logs.total} 件`
                            : '0 件'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {logs.links.map((link, index) => (
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

AuditLogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'メニュー',
            href: dashboard(),
        },
        {
            title: '監査ログ',
            href: auditLogIndex(),
        },
    ],
};
