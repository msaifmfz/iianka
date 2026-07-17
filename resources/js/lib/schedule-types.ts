import {
    BriefcaseBusiness,
    ClipboardList,
    Hammer,
    Megaphone,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { FlashResourceType } from '@/types/ui';

export type ScheduleType =
    | 'construction'
    | 'business'
    | 'internal_notice'
    | 'cleaning_duty';

type ScheduleTypeDescriptor = {
    label: string;
    icon: LucideIcon;
    resourceType: FlashResourceType;
    /** Filled pill on schedule list cards. */
    badgeClasses: string;
    /** Bordered block on the overview timeline. */
    timelineClasses: string;
    /** Bordered chip on search results. */
    chipClasses: string;
};

/**
 * Single source of truth for per-schedule-type presentation. Adding a new
 * schedule type means adding one entry here instead of touching each page's
 * parallel label/icon/color maps.
 */
export const scheduleTypeDescriptors: Record<
    ScheduleType,
    ScheduleTypeDescriptor
> = {
    construction: {
        label: '工事',
        icon: Hammer,
        resourceType: 'construction_schedule',
        badgeClasses:
            'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
        timelineClasses:
            'border-orange-300 bg-orange-100 text-orange-950 dark:border-orange-300/40 dark:bg-orange-500/20 dark:text-orange-50 dark:shadow-orange-950/20',
        chipClasses:
            'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-300/30 dark:bg-orange-500/15 dark:text-orange-100',
    },
    business: {
        label: '業務予定',
        icon: BriefcaseBusiness,
        resourceType: 'business_schedule',
        badgeClasses:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
        timelineClasses:
            'border-violet-300 bg-violet-100 text-violet-950 dark:border-violet-300/40 dark:bg-violet-500/20 dark:text-violet-50 dark:shadow-violet-950/20',
        chipClasses:
            'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-300/30 dark:bg-violet-500/15 dark:text-violet-100',
    },
    internal_notice: {
        label: '業務連絡',
        icon: Megaphone,
        resourceType: 'internal_notice',
        badgeClasses:
            'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
        timelineClasses:
            'border-sky-300 bg-sky-100 text-sky-950 dark:border-sky-300/40 dark:bg-sky-500/20 dark:text-sky-50 dark:shadow-sky-950/20',
        chipClasses:
            'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-300/30 dark:bg-sky-500/15 dark:text-sky-100',
    },
    cleaning_duty: {
        label: '掃除当番',
        icon: ClipboardList,
        resourceType: 'cleaning_duty_rule',
        badgeClasses:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        timelineClasses:
            'border-emerald-300 bg-emerald-100 text-emerald-950 dark:border-emerald-300/40 dark:bg-emerald-500/20 dark:text-emerald-50 dark:shadow-emerald-950/20',
        chipClasses:
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-300/30 dark:bg-emerald-500/15 dark:text-emerald-100',
    },
};

export function scheduleTypeLabel(type: ScheduleType) {
    return scheduleTypeDescriptors[type].label;
}
