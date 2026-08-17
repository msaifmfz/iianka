import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, FileText, Pencil } from 'lucide-react';
import { useState } from 'react';
import { edit as editConstructionSchedule } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    edit as guideEdit,
    index as guideIndex,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import {
    RecentResourceBadge,
    recentResourceHighlightClass,
} from '@/components/recent-resource-feedback';
import { ScheduleDetailDialog } from '@/components/schedule-detail-dialog';
import type { GuideFileSchedulePreview } from '@/components/site-guide-detail-dialog';
import { SiteGuideScheduleList } from '@/components/site-guide-detail-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { guideFileTypeLabel, isPreviewableImage } from '@/lib/site-guide';
import { cn } from '@/lib/utils';
import type { SiteGuideFileSummary } from '@/types';

type Props = {
    guideFile: SiteGuideFileSummary;
    usageSchedules: GuideFileSchedulePreview[];
    canManage: boolean;
};

export default function ConstructionSiteShow({
    guideFile,
    usageSchedules,
    canManage,
}: Props) {
    const { url } = usePage();
    const recentResource = useRecentResource();
    const [detailSchedule, setDetailSchedule] =
        useState<GuideFileSchedulePreview | null>(null);
    const isRecentResource = recentResourceMatches(
        recentResource,
        'site_guide_file',
        guideFile.id,
    );

    return (
        <>
            <Head title={`${guideFile.name} - 現場案内図`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link href={guideIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={guideEdit(guideFile.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>

                <Card
                    className={cn(
                        'rounded-2xl border-neutral-200/80 shadow-sm transition motion-reduce:transition-none dark:border-neutral-800',
                        isRecentResource && recentResourceHighlightClass,
                    )}
                >
                    <CardHeader className="space-y-3 xl:px-8 xl:pt-8">
                        <p className="text-sm text-muted-foreground">
                            Site guide library
                        </p>
                        <CardTitle className="flex min-w-0 items-center gap-3 text-2xl xl:text-3xl">
                            <FileText className="size-6 shrink-0 text-muted-foreground" />
                            <span className="truncate">{guideFile.name}</span>
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {guideFileTypeLabel(guideFile)}
                        </p>
                        {isRecentResource && recentResource !== null && (
                            <RecentResourceBadge
                                action={recentResource.action}
                            />
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4 xl:px-8 xl:pb-8">
                        {isPreviewableImage(guideFile) && (
                            <img
                                src={guideFile.url}
                                alt={`${guideFile.name} のプレビュー`}
                                className="max-h-96 w-full rounded-xl border bg-neutral-50 object-contain dark:border-neutral-800 dark:bg-neutral-900"
                            />
                        )}
                        <Button asChild className="min-h-11">
                            <a
                                href={guideFile.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                ファイルを開く
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <Card className="rounded-2xl border-neutral-200/80 shadow-sm dark:border-neutral-800">
                    <CardHeader className="xl:px-8 xl:pt-8">
                        {/* A real heading rather than CardTitle, which renders
                            a div: this section is navigable landmark content. */}
                        <h2 className="text-lg leading-none font-semibold">
                            使用している予定
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {guideFile.schedules_count}件
                            </span>
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            予定を長押しすると詳細を確認できます。
                        </p>
                    </CardHeader>
                    <CardContent className="xl:px-8 xl:pb-8">
                        <SiteGuideScheduleList
                            schedules={usageSchedules}
                            returnTo={url}
                            onOpenDetail={setDetailSchedule}
                            visibleCount={10}
                        />
                    </CardContent>
                </Card>
            </div>

            <ScheduleDetailDialog
                event={detailSchedule}
                open={detailSchedule !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailSchedule(null);
                    }
                }}
                description="内容を確認して、必要に応じて編集できます。"
            >
                {detailSchedule !== null && canManage && (
                    <Button asChild className="w-full rounded-md">
                        <Link
                            href={editConstructionSchedule(detailSchedule.id, {
                                query: { return_to: url },
                            })}
                        >
                            <Pencil className="size-4" />
                            編集ページへ
                        </Link>
                    </Button>
                )}
            </ScheduleDetailDialog>
        </>
    );
}

ConstructionSiteShow.layout = {
    breadcrumbs: [
        {
            title: '現場案内図',
            href: guideIndex(),
        },
    ],
};
