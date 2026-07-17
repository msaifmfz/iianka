import { usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { parseBusinessDate } from '@/lib/dates';
import type {
    ReceptionCasePriority,
    ReceptionCaseStatus,
    ReceptionMeta,
} from '@/types';

/**
 * Status/priority labels and the priority option list come from the backend
 * enums (shared as Inertia props), so they have a single source of truth. Only
 * the presentational colors below live in the frontend.
 */
export function useReceptionMeta(): ReceptionMeta {
    return usePage().props.reception;
}

const receptionStatusClasses: Record<ReceptionCaseStatus, string> = {
    draft: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200',
    received:
        'border-sky-200 bg-sky-100 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
    in_progress:
        'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    handover:
        'border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
    completed:
        'border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
};

const receptionPriorityClasses: Record<
    Exclude<ReceptionCasePriority, 'normal'>,
    string
> = {
    middle: 'border-orange-200 bg-orange-100 text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
    high: 'border-red-200 bg-red-100 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
};

export function ReceptionStatusBadge({
    status,
}: {
    status: ReceptionCaseStatus;
}) {
    const { statusLabels } = useReceptionMeta();

    return (
        <Badge variant="outline" className={receptionStatusClasses[status]}>
            {statusLabels[status]}
        </Badge>
    );
}

export function ReceptionPriorityBadge({
    priority,
}: {
    priority: ReceptionCasePriority;
}) {
    const { priorityLabels } = useReceptionMeta();

    if (priority === 'normal') {
        return null;
    }

    return (
        <Badge variant="outline" className={receptionPriorityClasses[priority]}>
            {priorityLabels[priority]}
        </Badge>
    );
}

const receptionDateFormatter = new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    timeZone: 'Asia/Tokyo',
});

export function formatReceptionDate(value: string | null): string {
    if (!value) {
        return '未設定';
    }

    return receptionDateFormatter.format(parseBusinessDate(value));
}

export function formatReceptionDateTime(value: string | null): string {
    if (!value) {
        return '未設定';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
