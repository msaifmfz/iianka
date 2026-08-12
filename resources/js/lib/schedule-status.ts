import type { ConstructionScheduleStatus } from '@/types';

type ConstructionScheduleStatusDescriptor = {
    label: string;
    /** Filled pill on schedule list cards. */
    badgeClasses: string;
};

/**
 * Single source of truth for construction schedule status presentation, so the
 * list, detail, form and stock management pages cannot label the same status
 * differently. Mirrors scheduleTypeDescriptors in schedule-types.ts.
 */
export const constructionScheduleStatusDescriptors: Record<
    ConstructionScheduleStatus,
    ConstructionScheduleStatusDescriptor
> = {
    scheduled: {
        label: '予定',
        badgeClasses:
            'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    },
    confirmed: {
        label: '確定',
        badgeClasses:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
    postponed: {
        label: '延期',
        badgeClasses:
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    },
    canceled: {
        label: '中止',
        badgeClasses:
            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
    },
};

export const constructionScheduleStatuses = Object.keys(
    constructionScheduleStatusDescriptors,
) as ConstructionScheduleStatus[];

export function constructionScheduleStatusLabel(
    status: ConstructionScheduleStatus,
) {
    return constructionScheduleStatusDescriptors[status].label;
}

export function constructionScheduleStatusBadgeClasses(
    status: ConstructionScheduleStatus,
) {
    return constructionScheduleStatusDescriptors[status].badgeClasses;
}
