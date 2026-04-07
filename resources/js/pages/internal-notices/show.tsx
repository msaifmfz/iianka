import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Megaphone, Pencil, Users } from 'lucide-react';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { edit as internalNoticeEdit } from '@/actions/App/Http/Controllers/InternalNoticeController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { InternalNotice } from '@/types';

type Props = {
    notice: InternalNotice;
    canManage: boolean;
};

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-neutral-50 p-4 dark:bg-neutral-900">
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="mt-1 font-medium">{value || '未設定'}</div>
        </div>
    );
}

export default function InternalNoticeShow({ notice, canManage }: Props) {
    return (
        <>
            <Head title={`${notice.title} - 業務連絡詳細`} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link
                            href={scheduleIndex({
                                query: {
                                    range: 'today',
                                    date: notice.scheduled_on,
                                    type: 'all',
                                },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            予定表へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={internalNoticeEdit(notice.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="xl:rounded-3xl">
                    <CardHeader className="xl:px-8">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {notice.scheduled_on} / {notice.time}
                                </p>
                                <CardTitle className="mt-2 text-3xl xl:text-4xl">
                                    {notice.title}
                                </CardTitle>
                            </div>
                            <span className="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1 text-sm font-semibold text-sky-900 dark:bg-sky-950 dark:text-sky-200">
                                <Megaphone className="size-4" />
                                業務連絡
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                            <Detail label="場所" value={notice.location} />
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                内容
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {notice.content}
                            </p>
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                メモ
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {notice.memo || '未設定'}
                            </p>
                        </div>

                        {notice.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {notice.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InternalNoticeShow.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
