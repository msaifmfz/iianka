import { Head, Link, router } from '@inertiajs/react';
import { Megaphone, Pencil, Trash2, Users } from 'lucide-react';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    destroy as internalNoticeDestroy,
    edit as internalNoticeEdit,
} from '@/actions/App/Http/Controllers/InternalNoticeController';
import { Detail } from '@/components/detail-item';
import { FloatingBackButton } from '@/components/floating-back-button';
import {
    RecentResourceBadge,
    recentResourceHighlightClass,
} from '@/components/recent-resource-feedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { returnToLabel, returnToQuery, visitReturnTo } from '@/lib/return-to';
import { cn } from '@/lib/utils';
import type { InternalNotice } from '@/types';

type Props = {
    notice: InternalNotice;
    canManage: boolean;
    returnTo: string | null;
};

export default function InternalNoticeShow({
    notice,
    canManage,
    returnTo,
}: Props) {
    const recentResource = useRecentResource();
    const { confirm: confirmDelete, dialog: deleteDialog } = useConfirmDialog();
    const isRecentResource = recentResourceMatches(
        recentResource,
        'internal_notice',
        notice.id,
    );
    const fallbackReturnTo = scheduleIndex({
        query: {
            range: 'today',
            date: notice.scheduled_on,
            type: 'all',
        },
    });

    const returnOptions = returnToQuery(returnTo);

    function handleReturnToIndex() {
        visitReturnTo(returnTo, fallbackReturnTo);
    }

    async function deleteNotice() {
        if (
            !(await confirmDelete({
                title: 'この業務連絡を削除しますか？',
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(internalNoticeDestroy.url(notice.id, returnOptions));
    }

    return (
        <>
            <Head title={`${notice.title} - 業務連絡詳細`} />
            {deleteDialog}
            <FloatingBackButton
                onClick={handleReturnToIndex}
                label={returnToLabel(returnTo)}
            />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 pb-24 md:p-6 md:pb-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link
                                    href={internalNoticeEdit(
                                        notice.id,
                                        returnOptions,
                                    )}
                                >
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void deleteNotice()}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        </div>
                    )}
                </div>

                <Card
                    className={cn(
                        'transition motion-reduce:transition-none xl:rounded-3xl',
                        isRecentResource && recentResourceHighlightClass,
                    )}
                >
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
                            {isRecentResource && recentResource !== null && (
                                <RecentResourceBadge
                                    action={recentResource.action}
                                />
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        {notice.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {notice.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}
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
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

InternalNoticeShow.layout = {
    breadcrumbs: [
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
