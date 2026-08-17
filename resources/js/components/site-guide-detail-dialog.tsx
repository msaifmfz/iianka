import { Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    ExternalLink,
    FileText,
    History,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { show as constructionScheduleShow } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { edit as guideEdit } from '@/actions/App/Http/Controllers/ConstructionSiteController';
import type { ScheduleDetailEvent } from '@/components/schedule-detail-dialog';
import { useScheduleDetailHold } from '@/components/schedule-detail-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { businessDateString } from '@/lib/dates';
import {
    constructionScheduleStatusBadgeClasses,
    constructionScheduleStatusLabel,
} from '@/lib/schedule-status';
import { guideFileTypeLabel, isPreviewableImage } from '@/lib/site-guide';
import { cn } from '@/lib/utils';
import type { ConstructionScheduleStatus, SiteGuideFileSummary } from '@/types';

/**
 * A construction schedule as it appears in a guide file's usage list. The
 * intersection keeps it feedable to ScheduleDetailDialog while carrying the
 * two extra fields the row itself renders.
 */
export type GuideFileSchedulePreview = ScheduleDetailEvent & {
    type: 'construction';
    scheduled_on: string;
    status: ConstructionScheduleStatus;
};

const defaultVisibleCount = 5;

function usageDateLabel(isoDate: string) {
    const [year, month, day] = isoDate.split('-');

    return `${year}/${Number(month)}/${Number(day)}`;
}

function UsageScheduleRow({
    schedule,
    returnTo,
    onOpenDetail,
}: {
    schedule: GuideFileSchedulePreview;
    returnTo: string;
    onOpenDetail: (schedule: GuideFileSchedulePreview) => void;
}) {
    const detailHold = useScheduleDetailHold(onOpenDetail);

    // A button rather than a link: long-pressing an anchor opens the browser's
    // own link menu on touch devices, which would fight the hold-to-preview.
    return (
        <li>
            <button
                type="button"
                aria-label={`${schedule.title}の予定ページを開く`}
                className="grid w-full min-w-0 touch-manipulation gap-1 rounded-xl border bg-card px-3 py-2 text-left transition select-none hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:border-neutral-800"
                onPointerDown={(pointerEvent) =>
                    detailHold.startHold(pointerEvent, schedule)
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

                    router.visit(
                        constructionScheduleShow(schedule.id, {
                            query: { return_to: returnTo },
                        }),
                    );
                }}
            >
                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {usageDateLabel(schedule.scheduled_on)}
                    </span>
                    <span>{schedule.time}</span>
                    <span>#{schedule.schedule_number ?? '?'}</span>
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 font-medium',
                            constructionScheduleStatusBadgeClasses(
                                schedule.status,
                            ),
                        )}
                    >
                        {constructionScheduleStatusLabel(schedule.status)}
                    </span>
                </span>
                <span className="truncate font-semibold">{schedule.title}</span>
                {schedule.general_contractor && (
                    <span className="truncate text-xs text-muted-foreground">
                        {schedule.general_contractor}
                    </span>
                )}
            </button>
        </li>
    );
}

function UsageScheduleGroup({
    label,
    icon,
    schedules,
    returnTo,
    onOpenDetail,
}: {
    label: string;
    icon: React.ReactNode;
    schedules: GuideFileSchedulePreview[];
    returnTo: string;
    onOpenDetail: (schedule: GuideFileSchedulePreview) => void;
}) {
    if (schedules.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                {icon}
                {label}
            </p>
            <ul className="grid gap-2">
                {schedules.map((schedule) => (
                    <UsageScheduleRow
                        key={schedule.id}
                        schedule={schedule}
                        returnTo={returnTo}
                        onOpenDetail={onOpenDetail}
                    />
                ))}
            </ul>
        </div>
    );
}

type SiteGuideScheduleListProps = {
    /** Undefined while the previews are still being fetched. */
    schedules: GuideFileSchedulePreview[] | undefined;
    returnTo: string;
    onOpenDetail: (schedule: GuideFileSchedulePreview) => void;
    visibleCount?: number;
};

/**
 * The schedules using one guide file, with the ones still ahead separated out —
 * that split is the reason to look at all: a guide file about to be used reads
 * differently from one only ever used in the past.
 *
 * The two groups run in opposite directions on purpose. Upcoming is
 * soonest-first, so the next use leads; past is newest-first, so the most
 * recent use leads. Both put the schedule nearest to today at the top.
 */
export function SiteGuideScheduleList({
    schedules,
    returnTo,
    onOpenDetail,
    visibleCount = defaultVisibleCount,
}: SiteGuideScheduleListProps) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (schedules === undefined) {
        return (
            <div className="grid gap-2" aria-busy="true">
                {[0, 1, 2].map((row) => (
                    <Skeleton key={row} className="h-16 rounded-xl" />
                ))}
            </div>
        );
    }

    if (schedules.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground dark:border-neutral-800">
                この案内図を使用している予定はありません。
            </p>
        );
    }

    const today = businessDateString();
    // The server sends newest first, which is what the past group wants but the
    // reverse of what the upcoming one does: the next use of a guide file is
    // the one a rename or a delete disrupts, so it has to be the row that
    // survives the visibleCount slice below.
    const upcoming = schedules
        .filter((schedule) => schedule.scheduled_on >= today)
        .reverse();
    const past = schedules.filter((schedule) => schedule.scheduled_on < today);
    const ordered = [...upcoming, ...past];
    const visible = isExpanded ? ordered : ordered.slice(0, visibleCount);
    const hiddenCount = ordered.length - visible.length;

    return (
        <div className="space-y-3">
            <UsageScheduleGroup
                label="今後の予定"
                icon={<CalendarClock className="size-3.5" />}
                schedules={visible.filter(
                    (schedule) => schedule.scheduled_on >= today,
                )}
                returnTo={returnTo}
                onOpenDetail={onOpenDetail}
            />
            <UsageScheduleGroup
                label="過去の予定"
                icon={<History className="size-3.5" />}
                schedules={visible.filter(
                    (schedule) => schedule.scheduled_on < today,
                )}
                returnTo={returnTo}
                onOpenDetail={onOpenDetail}
            />
            {hiddenCount > 0 && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => setIsExpanded(true)}
                >
                    すべて表示（残り{hiddenCount}件）
                </Button>
            )}
        </div>
    );
}

type SiteGuideDetailDialogProps = {
    guideFile: SiteGuideFileSummary | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canManage: boolean;
    /** Undefined while the previews are still being fetched. */
    schedules: GuideFileSchedulePreview[] | undefined;
    onOpenSchedule: (schedule: GuideFileSchedulePreview) => void;
    returnTo: string;
};

export function SiteGuideDetailDialog({
    guideFile,
    open,
    onOpenChange,
    canManage,
    schedules,
    onOpenSchedule,
    returnTo,
}: SiteGuideDetailDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85svh] overflow-y-auto sm:max-w-md">
                {guideFile !== null && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="break-words">
                                {guideFile.name}
                            </DialogTitle>
                            <DialogDescription>
                                {guideFileTypeLabel(guideFile)}・使用予定
                                {guideFile.schedules_count}件
                            </DialogDescription>
                        </DialogHeader>

                        {isPreviewableImage(guideFile) ? (
                            <img
                                src={guideFile.url}
                                alt={`${guideFile.name} のプレビュー`}
                                className="max-h-56 w-full rounded-xl border bg-neutral-50 object-contain dark:border-neutral-800 dark:bg-neutral-900"
                            />
                        ) : (
                            <div className="flex items-center justify-center gap-2 rounded-xl border bg-neutral-50 p-6 text-sm text-muted-foreground dark:border-neutral-800 dark:bg-neutral-900">
                                <FileText className="size-5" />
                                {guideFileTypeLabel(guideFile)}
                            </div>
                        )}

                        <section className="space-y-2">
                            <h3 className="text-sm font-semibold">
                                使用している予定
                            </h3>
                            <SiteGuideScheduleList
                                schedules={schedules}
                                returnTo={returnTo}
                                onOpenDetail={onOpenSchedule}
                            />
                        </section>

                        <div className="grid gap-2 sm:grid-cols-2">
                            <Button asChild variant="outline">
                                <a
                                    href={guideFile.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                    ファイルを開く
                                </a>
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
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
