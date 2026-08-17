import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Info,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { edit as editConstructionSchedule } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    create as guideCreate,
    destroy as guideDestroy,
    edit as guideEdit,
    index as guideIndex,
    show as guideShow,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import {
    RecentResourceBadge,
    recentResourceHighlightClass,
} from '@/components/recent-resource-feedback';
import { ScheduleDetailDialog } from '@/components/schedule-detail-dialog';
import type { GuideFileSchedulePreview } from '@/components/site-guide-detail-dialog';
import { SiteGuideDetailDialog } from '@/components/site-guide-detail-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { useHoldToPreview } from '@/hooks/use-hold-to-preview';
import { useIndexScrollRestore } from '@/hooks/use-index-scroll-restore';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { useUrlFilters } from '@/hooks/use-url-filters';
import { guideFileTypeLabel } from '@/lib/site-guide';
import { cn } from '@/lib/utils';
import type { SiteGuideFileSummary } from '@/types';
import type { FlashResourceAction } from '@/types/ui';

type UsageSchedules = Record<string, GuideFileSchedulePreview[]>;

type Props = {
    guideFiles: SiteGuideFileSummary[];
    filters: { search: string };
    totalCount: number;
    /** Undefined until the follow-up request resolves; see useUsageSchedules. */
    usageSchedules?: UsageSchedules;
    canManage: boolean;
};

/**
 * The schedules behind each card's usage list. They are an optional prop, so
 * they are fetched the first time a card is opened rather than on every visit —
 * most visits never open one.
 *
 * Driven by an effect rather than the open handler so the request follows the
 * page: a search that lands mid-flight replaces the props with a payload that
 * has no previews in it, and the dialog would otherwise sit on its skeleton
 * with nothing left to re-trigger it. `requestedUrl` only de-dupes the
 * in-flight window — it is released on finish, so a dropped request retries —
 * while `usageSchedules` being set is what stops a refetch after success.
 */
function useUsageSchedules(
    url: string,
    isOpen: boolean,
    usageSchedules?: UsageSchedules,
) {
    const requestedUrl = useRef<string | null>(null);

    useEffect(() => {
        if (
            !isOpen ||
            usageSchedules !== undefined ||
            requestedUrl.current === url
        ) {
            return;
        }

        requestedUrl.current = url;
        router.reload({
            only: ['usageSchedules'],
            onFinish: () => {
                requestedUrl.current = null;
            },
        });
    }, [isOpen, usageSchedules, url]);
}

function GuideFileCard({
    file,
    canManage,
    isRecent,
    recentAction,
    onOpenDetail,
    onDelete,
}: {
    file: SiteGuideFileSummary;
    canManage: boolean;
    isRecent: boolean;
    recentAction: FlashResourceAction | null;
    onOpenDetail: (file: SiteGuideFileSummary) => void;
    onDelete: (file: SiteGuideFileSummary) => void;
}) {
    const detailHold = useHoldToPreview<SiteGuideFileSummary>(onOpenDetail);

    return (
        <Card
            className={cn(
                'rounded-2xl border-neutral-200/80 shadow-sm transition motion-reduce:transition-none dark:border-neutral-800',
                isRecent && recentResourceHighlightClass,
            )}
        >
            <CardContent className="grid gap-4 p-4">
                <div className="flex min-w-0 items-start gap-2">
                    {/*
                     * A heading wrapping a button, not a heading beside one: the
                     * grid is navigable by heading, while the press target stays
                     * the whole title block. A button rather than a link because
                     * long-pressing an anchor opens the browser's own link menu
                     * on touch devices, which would fight the hold-to-preview.
                     */}
                    <h2 className="min-w-0 flex-1">
                        <button
                            type="button"
                            aria-label={`${file.name} の詳細ページを開く`}
                            className="grid w-full min-w-0 touch-manipulation grid-cols-[auto_minmax(0,1fr)] items-start gap-3 rounded-lg p-1 text-left transition select-none hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            onPointerDown={(pointerEvent) =>
                                detailHold.startHold(pointerEvent, file)
                            }
                            onPointerMove={detailHold.updateHold}
                            onPointerUp={detailHold.finishHold}
                            onPointerCancel={detailHold.finishHold}
                            onPointerLeave={detailHold.finishHold}
                            onContextMenu={(event) => event.preventDefault()}
                            onClick={() => {
                                if (detailHold.consumeClickAfterHold()) {
                                    return;
                                }

                                router.visit(guideShow(file.id));
                            }}
                        >
                            <span className="rounded-lg bg-neutral-100 p-2 dark:bg-neutral-900">
                                <FileText className="size-5 text-muted-foreground" />
                            </span>
                            <span className="grid min-w-0 gap-1">
                                <span className="truncate font-semibold">
                                    {file.name}
                                </span>
                                <span className="flex flex-wrap items-center gap-2 text-xs font-normal text-muted-foreground">
                                    <span>{guideFileTypeLabel(file)}</span>
                                    {file.schedules_count > 0 ? (
                                        <span className="rounded-full bg-sky-100 px-2 py-0.5 font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                            使用予定 {file.schedules_count}件
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-muted px-2 py-0.5">
                                            未使用
                                        </span>
                                    )}
                                </span>
                            </span>
                        </button>
                    </h2>
                    {/* Long-press has no keyboard equivalent, so the same
                        dialog gets an explicit control. */}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`${file.name} の詳細を表示`}
                        onClick={() => onOpenDetail(file)}
                    >
                        <Info className="size-4" />
                    </Button>
                </div>

                {isRecent && recentAction !== null && (
                    <RecentResourceBadge action={recentAction} />
                )}

                <div className="flex flex-wrap gap-2">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="flex-1 justify-center"
                    >
                        <a href={file.url} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-4" />
                            確認
                        </a>
                    </Button>
                    {canManage && (
                        <>
                            <Button asChild variant="outline" size="sm">
                                <Link href={guideEdit(file.id)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onDelete(file)}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function ConstructionSitesIndex({
    guideFiles,
    filters,
    totalCount,
    usageSchedules,
    canManage,
}: Props) {
    const { url } = usePage();
    const recentResource = useRecentResource();
    const { confirm: confirmDelete, dialog: deleteDialog } = useConfirmDialog();
    const { filters: localFilters, setFilter } = useUrlFilters(
        filters,
        guideIndex.url(),
    );
    const [detailGuideFile, setDetailGuideFile] =
        useState<SiteGuideFileSummary | null>(null);
    const [detailSchedule, setDetailSchedule] =
        useState<GuideFileSchedulePreview | null>(null);

    useIndexScrollRestore('construction-sites:scroll:', url);
    useUsageSchedules(url, detailGuideFile !== null, usageSchedules);

    async function deleteGuideFile(file: SiteGuideFileSummary) {
        if (
            !(await confirmDelete({
                title: `${file.name} を削除しますか？`,
                // The pivot cascades, so deleting silently detaches the file
                // from every schedule that referenced it.
                description:
                    file.schedules_count > 0
                        ? `${file.schedules_count}件の予定で使用中です。削除すると、それらの予定から案内図が外れます。`
                        : undefined,
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(guideDestroy.url(file.id), {
            preserveScroll: true,
        });
    }

    const isFiltered = filters.search !== '';
    // Undefined keeps the dialog on its loading skeleton until the previews
    // land; once they have, a file with no schedules is an empty list.
    const detailSchedules =
        usageSchedules === undefined || detailGuideFile === null
            ? undefined
            : (usageSchedules[detailGuideFile.id] ?? []);

    return (
        <>
            <Head title="現場案内図" />
            {deleteDialog}
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <section className="rounded-2xl border bg-white p-5 shadow-sm sm:p-6 xl:p-7 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Site guide library
                            </p>
                            <h1 className="text-2xl font-bold tracking-tight xl:text-3xl">
                                現場案内図
                            </h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                ファイル名で判別できるように案内図を管理します。カードを長押しすると、使用している予定を確認できます。
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-2xl border bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/70">
                                <p className="text-xs font-medium text-muted-foreground">
                                    {isFiltered ? '検索結果' : '登録ファイル'}
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {isFiltered
                                        ? `${guideFiles.length} / ${totalCount}`
                                        : totalCount}
                                </p>
                            </div>
                            {canManage && (
                                <Button asChild>
                                    <Link href={guideCreate()}>
                                        <Plus className="size-4" />
                                        案内図を追加
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="mt-5 space-y-2">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                aria-label="案内図を検索"
                                className="pl-9"
                                value={localFilters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                                placeholder="表示名で検索"
                            />
                        </div>
                        {isFiltered && (
                            <Button asChild variant="link" className="px-0">
                                <Link href={guideIndex()}>条件をクリア</Link>
                            </Button>
                        )}
                    </div>
                </section>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {guideFiles.map((file) => (
                        <GuideFileCard
                            key={file.id}
                            file={file}
                            canManage={canManage}
                            isRecent={recentResourceMatches(
                                recentResource,
                                'site_guide_file',
                                file.id,
                            )}
                            recentAction={recentResource?.action ?? null}
                            onOpenDetail={setDetailGuideFile}
                            onDelete={(target) => void deleteGuideFile(target)}
                        />
                    ))}
                    {guideFiles.length === 0 && (
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3 dark:border-neutral-800">
                            {isFiltered
                                ? '一致する案内図はありません。'
                                : '現場案内図はまだ登録されていません。'}
                        </div>
                    )}
                </div>
            </div>

            <SiteGuideDetailDialog
                guideFile={detailGuideFile}
                open={detailGuideFile !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailGuideFile(null);
                    }
                }}
                canManage={canManage}
                schedules={detailSchedules}
                onOpenSchedule={setDetailSchedule}
                returnTo={url}
            />
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

ConstructionSitesIndex.layout = {
    breadcrumbs: [
        {
            title: '現場案内図',
            href: guideIndex(),
        },
    ],
};
