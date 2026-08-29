import { Sunrise } from 'lucide-react';
import type { ReactNode } from 'react';
import type { AttendanceStatus } from '@/types';

type AttendanceStatusDescriptor = {
    label: string;
    /** Cell surface in the month grid; also the selected state in the editor. */
    cellClasses: string;
    /** Filled pill for legend chips and the per-user counters. */
    badgeClasses: string;
    /**
     * Shape hint for the exceptional status, so a dense grid never leans on hue
     * alone. Only 早出 carries one: 出勤 and 休み are the baseline that colour and
     * the cell's own text label already communicate.
     *
     * Held as an element factory rather than a component reference because
     * react-hooks/static-components rejects pulling a component out of a map
     * during render.
     */
    renderIcon?: (className: string) => ReactNode;
};

/**
 * Single source of truth for attendance status presentation, so the month grid,
 * the legend, the per-user counters and the editor cannot label or colour the
 * same status differently. Mirrors constructionScheduleStatusDescriptors in
 * schedule-status.ts.
 *
 * 早出 uses sky rather than amber because the grid already spends amber on
 * "today" (the cell ring and the column header), and emerald/sky separates far
 * better than emerald/amber for red-green colour blindness.
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
    early: {
        label: '早出',
        cellClasses:
            'border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100',
        badgeClasses:
            'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100',
        renderIcon: (className) => (
            <Sunrise className={className} aria-hidden />
        ),
    },
    leave: {
        label: '休み',
        cellClasses:
            'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100',
        badgeClasses:
            'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100',
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
 * written before 早出 existed — or edited straight in the database — can carry a
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

export function attendanceStatusIcon(
    status: AttendanceStatus,
    className: string,
) {
    return attendanceStatusDescriptor(status)?.renderIcon?.(className) ?? null;
}
