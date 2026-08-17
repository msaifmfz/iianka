import { Head, Link, router } from '@inertiajs/react';
import { BriefcaseBusiness, Pencil, Trash2, Users } from 'lucide-react';
import {
    destroy as businessScheduleDestroy,
    edit as businessScheduleEdit,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Detail } from '@/components/detail-item';
import { FloatingBackButton } from '@/components/floating-back-button';
import {
    RecentResourceBadge,
    recentResourceHighlightClass,
} from '@/components/recent-resource-feedback';
import { ReceptionScheduleSourceBanner } from '@/components/reception-schedule-source-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { returnToLabel, returnToQuery, visitReturnTo } from '@/lib/return-to';
import { cn } from '@/lib/utils';
import type { BusinessSchedule, ReceptionScheduleSource } from '@/types';

type Props = {
    schedule: BusinessSchedule;
    sourceReceptionCase: ReceptionScheduleSource | null;
    canManage: boolean;
    returnTo: string | null;
};

export default function BusinessScheduleShow({
    schedule,
    sourceReceptionCase,
    canManage,
    returnTo,
}: Props) {
    const recentResource = useRecentResource();
    const { confirm: confirmDelete, dialog: deleteDialog } = useConfirmDialog();
    const isRecentResource = recentResourceMatches(
        recentResource,
        'business_schedule',
        schedule.id,
    );
    const fallbackReturnTo = scheduleIndex({
        query: {
            range: 'today',
            date: schedule.scheduled_on,
            type: 'all',
        },
    });

    const returnOptions = returnToQuery(returnTo);

    function handleReturnToIndex() {
        visitReturnTo(returnTo, fallbackReturnTo);
    }

    async function deleteSchedule() {
        if (
            !(await confirmDelete({
                title: 'この業務予定を削除しますか？',
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(businessScheduleDestroy.url(schedule.id, returnOptions));
    }

    return (
        <>
            <Head title={`${schedule.location} - 業務予定詳細`} />
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
                                    href={businessScheduleEdit(
                                        schedule.id,
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
                                onClick={() => void deleteSchedule()}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        </div>
                    )}
                </div>

                {sourceReceptionCase && (
                    <ReceptionScheduleSourceBanner
                        source={sourceReceptionCase}
                    />
                )}

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
                                    {schedule.scheduled_on} / {schedule.time}
                                </p>
                                <CardTitle className="mt-2 text-3xl xl:text-4xl">
                                    {schedule.location}
                                </CardTitle>
                            </div>
                            <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-3 py-1 text-sm font-semibold text-violet-900 dark:bg-violet-950 dark:text-violet-200">
                                <BriefcaseBusiness className="size-4" />
                                業務
                            </span>
                            {isRecentResource && recentResource !== null && (
                                <RecentResourceBadge
                                    action={recentResource.action}
                                />
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        {schedule.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {schedule.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                            <Detail label="人員" value={schedule.personnel} />
                            <Detail
                                label="ゼネコン会社"
                                value={schedule.general_contractor}
                            />
                            <Detail
                                label="担当"
                                value={schedule.person_in_charge}
                            />
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                内容
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {schedule.content}
                            </p>
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                メモ
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {schedule.memo || '未設定'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BusinessScheduleShow.layout = {
    breadcrumbs: [
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
