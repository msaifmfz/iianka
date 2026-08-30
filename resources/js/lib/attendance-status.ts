import type { AttendanceStatus } from '@/types';

type AttendanceStatusDescriptor = {
    label: string;
    /** Cell surface in the month grid; also the selected state in the editor. */
    cellClasses: string;
    /** Filled pill for legend chips and the per-user counters. */
    badgeClasses: string;
    /**
     * Surface for the "today" callout panels above the calendar. Optional
     * because a panel only exists for the statuses worth interrupting the page
     * over: 出勤 is the expected state and never earns one.
     */
    panelClasses?: string;
};

/**
 * Single source of truth for attendance status presentation, so the month grid,
 * the legend, the per-user counters, the today panels and the editor cannot
 * label or colour the same status differently. Mirrors
 * constructionScheduleStatusDescriptors in schedule-status.ts.
 *
 * 早退 is orange. That does sit beside the amber the grid spends on "today"
 * (the cell's ring and the column header), but the two never compete: "today"
 * is an outline held off the cell by ring-offset-2, while a status is the
 * filled surface inside it.
 *
 * The closer call is orange 早退 against rose 休み — both warm, the pair a
 * red-green colour blind operator is most likely to confuse at cell size. What
 * settles it is text, not hue: every cell renders its status label beside the
 * day number and repeats it in the cell's aria-label, so the two are legible
 * with hue discarded entirely.
 */
export const attendanceStatusDescriptors: Record<
    AttendanceStatus,
    AttendanceStatusDescriptor
> = {
    working: {
        label: '出勤',
        cellClasses:
            'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100',
        badgeClasses:
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100',
    },
    early_out: {
        label: '早退',
        cellClasses:
            'border-orange-200 bg-orange-50 text-orange-800 hover:bg-orange-100 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-100',
        badgeClasses:
            'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-100',
        panelClasses:
            'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-100',
    },
    leave: {
        label: '休み',
        cellClasses:
            'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100',
        badgeClasses:
            'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100',
        panelClasses:
            'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100',
    },
};

export const attendanceStatuses = Object.keys(
    attendanceStatusDescriptors,
) as AttendanceStatus[];

/**
 * An unmarked day is the absence of a record rather than a status the server can
 * send, so its appearance lives outside the descriptor map: adding an 'unset'
 * key would weaken the exhaustiveness the map exists to provide.
 */
export const unmarkedAttendanceLabel = '未';

export const unmarkedAttendanceCellClasses =
    'border-neutral-200 bg-white text-muted-foreground hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950 dark:hover:bg-neutral-900';

export const unmarkedAttendanceBadgeClasses =
    'border-neutral-200 bg-white text-muted-foreground dark:border-neutral-800 dark:bg-neutral-950';

/**
 * `status` is an unconstrained varchar the server hands over as JSON, so a row
 * written before 早退 existed — or edited straight in the database — can carry a
 * value the union type says is impossible. Indexing the map directly would
 * throw on `.label` and blank the whole month, so every accessor goes through
 * this lookup: the map stays exhaustive for `attendanceStatuses`, while a
 * surprise value degrades to the unmarked styling instead of taking the page
 * down with it.
 */
function attendanceStatusDescriptor(
    status: AttendanceStatus,
): AttendanceStatusDescriptor | undefined {
    return Object.hasOwn(attendanceStatusDescriptors, status)
        ? attendanceStatusDescriptors[status]
        : undefined;
}

/**
 * Unknown statuses fall back to the raw stored value rather than to 未: the cell
 * is showing a record that exists, and an operator who sees the offending string
 * can report it, where '未' would quietly disguise it as an unset day.
 */
export function attendanceStatusLabel(status: AttendanceStatus) {
    return attendanceStatusDescriptor(status)?.label ?? status;
}

export function attendanceStatusCellClasses(status?: AttendanceStatus) {
    if (!status) {
        return unmarkedAttendanceCellClasses;
    }

    return (
        attendanceStatusDescriptor(status)?.cellClasses ??
        unmarkedAttendanceCellClasses
    );
}

export function attendanceStatusBadgeClasses(status: AttendanceStatus) {
    return (
        attendanceStatusDescriptor(status)?.badgeClasses ??
        unmarkedAttendanceBadgeClasses
    );
}

/**
 * Falls back to the badge surface rather than to nothing: an empty class string
 * would render the panel unstyled and transparent, which reads as a layout bug
 * rather than as a status the map has not described.
 */
export function attendanceStatusPanelClasses(status: AttendanceStatus) {
    const descriptor = attendanceStatusDescriptor(status);

    return (
        descriptor?.panelClasses ??
        descriptor?.badgeClasses ??
        unmarkedAttendanceBadgeClasses
    );
}
