import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    ChevronLeft,
    ChevronRight,
    Megaphone,
    Hammer,
    Pencil,
    Plus,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import {
    create as createBusinessSchedule,
    edit as editBusinessSchedule,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import {
    create as createConstructionSchedule,
    edit as editConstructionSchedule,
    index as scheduleIndex,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { index as voucherIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleVoucherController';
import {
    create as createInternalNotice,
    edit as editInternalNotice,
} from '@/actions/App/Http/Controllers/InternalNoticeController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { businessDateString } from '@/lib/dates';
import { index as overviewIndex } from '@/routes/schedule-overview';
import type { ConstructionUser } from '@/types';

type CalendarDay = {
    date: string;
    construction_count: number;
    business_count: number;
    internal_notice_count: number;
    unconfirmed_voucher_count: number;
    schedule_count: number;
};

type Month = {
    starts_on: string;
    ends_on: string;
    calendar_starts_on: string;
    calendar_ends_on: string;
};

type Props = {
    filters: {
        date: string;
    };
    todayDate: string;
    month: Month;
    calendarDays: CalendarDay[];
    canManageSchedules: boolean;
    selectedDayTimeline: SelectedDayTimeline;
};

type TimelineEventType = 'construction' | 'business' | 'internal_notice';

type TimelineEvent = {
    id: number;
    type: TimelineEventType;
    schedule_number: number | null;
    title: string;
    location: string | null;
    content: string | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    assigned_users: ConstructionUser[];
};

type SelectedDayTimeline = {
    users: ConstructionUser[];
    events: TimelineEvent[];
};

type CreateSlot = {
    date: string;
    hour: number;
    endHour: number;
    rowName: string;
    userId: number | null;
};

type DragSelection = {
    date: string;
    rowName: string;
    userId: number | null;
    anchorHour: number;
    currentHour: number;
    pointerId: number;
};

type PendingDragSelection = {
    date: string;
    rowName: string;
    userId: number | null;
    anchorHour: number;
    pointerId: number;
    startX: number;
    startY: number;
    rowElement: HTMLDivElement;
    timeoutId: number;
};

type CalendarCell = CalendarDay & {
    label: number;
    weekday: number;
    isSelected: boolean;
    isToday: boolean;
    isCurrentMonth: boolean;
};

const weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];

function parseDate(date: string) {
    return new Date(`${date}T00:00:00`);
}

function formatInputDate(date: Date) {
    return businessDateString(date);
}

function adjacentMonthDate(selectedDate: string, offset: number) {
    const date = parseDate(selectedDate);

    return formatInputDate(
        new Date(date.getFullYear(), date.getMonth() + offset, 1),
    );
}

function monthTitle(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
    }).format(parseDate(date));
}

function detailDate(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    }).format(parseDate(date));
}

function calendarCells(
    selectedDate: string,
    todayDate: string,
    month: Month,
    calendarDays: CalendarDay[],
): CalendarCell[] {
    const monthStart = parseDate(month.starts_on);
    const counts = new Map(calendarDays.map((day) => [day.date, day]));
    const cells: CalendarCell[] = [];
    const current = parseDate(month.calendar_starts_on);
    const end = parseDate(month.calendar_ends_on);

    while (current <= end) {
        const date = formatInputDate(current);
        const day = counts.get(date) ?? {
            date,
            construction_count: 0,
            business_count: 0,
            internal_notice_count: 0,
            unconfirmed_voucher_count: 0,
            schedule_count: 0,
        };

        cells.push({
            ...day,
            label: current.getDate(),
            weekday: current.getDay(),
            isSelected: date === selectedDate,
            isToday: date === todayDate,
            isCurrentMonth: current.getMonth() === monthStart.getMonth(),
        });
        current.setDate(current.getDate() + 1);
    }

    return cells;
}

function overviewQuery(date: string) {
    return {
        query: { date },
    };
}

function maxScheduleCount(days: CalendarCell[]) {
    return Math.max(...days.map((day) => day.schedule_count), 0);
}

function heatLevel(day: CalendarCell, maxCount: number) {
    if (day.schedule_count <= 0 || maxCount <= 0) {
        return 0;
    }

    if (maxCount <= 4) {
        return Math.min(day.schedule_count, 4);
    }

    const ratio = day.schedule_count / maxCount;

    if (ratio <= 0.25) {
        return 1;
    }

    if (ratio <= 0.5) {
        return 2;
    }

    if (ratio <= 0.75) {
        return 3;
    }

    return 4;
}

function heatLabel(level: number) {
    switch (level) {
        case 1:
            return '少なめ';
        case 2:
            return '通常';
        case 3:
            return '多め';
        case 4:
            return '非常に多い';
        default:
            return '予定なし';
    }
}

function heatCellClass(level: number) {
    switch (level) {
        case 1:
            return 'border-blue-200 bg-blue-50 text-blue-950 hover:border-blue-300 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-50 dark:hover:border-blue-800 dark:hover:bg-blue-950/50';
        case 2:
            return 'border-blue-300 bg-blue-100 text-blue-950 hover:border-blue-400 hover:bg-blue-200/70 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-50 dark:hover:border-blue-700 dark:hover:bg-blue-900/50';
        case 3:
            return 'border-blue-400 bg-blue-200 text-blue-950 hover:border-blue-500 hover:bg-blue-300/80 dark:border-blue-700 dark:bg-blue-900/60 dark:text-blue-50 dark:hover:border-blue-600 dark:hover:bg-blue-800/60';
        case 4:
            return 'border-blue-600 bg-blue-300 text-blue-950 hover:border-blue-700 hover:bg-blue-400/80 dark:border-blue-500 dark:bg-blue-800/70 dark:text-blue-50 dark:hover:border-blue-400 dark:hover:bg-blue-700/70';
        default:
            return 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950 dark:hover:border-neutral-700 dark:hover:bg-neutral-900';
    }
}

function dayCellClass(day: CalendarCell, maxCount: number) {
    const level = heatLevel(day, maxCount);
    const heatClass = heatCellClass(level);

    if (day.isSelected) {
        return `${heatClass} border-neutral-200 shadow-md ring-1 ring-neutral-200 dark:border-white dark:ring-white`;
    }

    if (!day.isCurrentMonth) {
        if (level > 0) {
            return `${heatClass} opacity-65`;
        }

        return 'border-neutral-200 bg-neutral-50 text-neutral-400 hover:border-neutral-300 hover:bg-white dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-600 dark:hover:border-neutral-700 dark:hover:bg-neutral-950';
    }

    return heatClass;
}

function metricTone(tone: 'neutral' | 'danger' = 'neutral') {
    if (tone === 'danger') {
        return 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100';
    }

    return '';
}

function MetricPill({
    label,
    value,
    tone = 'neutral',
    shortLabel,
    className = '',
}: {
    label: string;
    value: number | string;
    tone?: 'neutral' | 'danger';
    shortLabel?: string;
    className?: string;
}) {
    return (
        <span
            className={`inline-flex min-h-2 items-center gap-1 rounded-md px-1 py-0.5 text-[10px] leading-none font-semibold sm:min-h-2 sm:gap-2 sm:px-2.5 sm:py-1 sm:text-xs ${metricTone(tone)} ${className}`}
        >
            {shortLabel ? (
                <>
                    <span className="hidden sm:inline">{label}</span>
                    <span className="sm:hidden">{shortLabel}</span>
                </>
            ) : (
                <span>{label}</span>
            )}
            <span className="tabular-nums">{value}</span>
        </span>
    );
}

function confirmedVoucherCount(day: CalendarDay) {
    return Math.max(day.construction_count - day.unconfirmed_voucher_count, 0);
}

function voucherConfirmationValue(day: CalendarDay) {
    return `${confirmedVoucherCount(day)}/${day.construction_count}`;
}

const timelineDefaultStart = 8 * 60;
const timelineDefaultEnd = 20 * 60;
const timelineHour = 60;
const timelineSlotWidth = 47;
const touchSelectionDelay = 260;
const scheduleDetailHoldDelay = 500;
const touchScrollTolerance = 10;

function timeToMinutes(time: string | null) {
    if (time === null) {
        return null;
    }

    const [hours, minutes] = time.slice(0, 5).split(':').map(Number);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    return hours * 60 + minutes;
}

function timelineBounds(events: TimelineEvent[]) {
    const timedMinutes = events.flatMap((event) => {
        const startsAt = timeToMinutes(event.starts_at);
        const endsAt = timeToMinutes(event.ends_at);

        if (startsAt === null) {
            return [];
        }

        return [startsAt, endsAt ?? timelineDefaultEnd];
    });
    const minMinutes = Math.min(timelineDefaultStart, ...timedMinutes);
    const maxMinutes = Math.max(timelineDefaultEnd, ...timedMinutes);
    const startsAt = Math.floor(minMinutes / timelineHour) * timelineHour;
    const endsAt = Math.ceil(maxMinutes / timelineHour) * timelineHour;
    const hours: number[] = [];

    for (let minute = startsAt; minute <= endsAt; minute += timelineHour) {
        hours.push(minute);
    }

    return {
        startsAt,
        endsAt,
        hours,
        duration: Math.max(endsAt - startsAt, timelineHour),
        slotCount: Math.max(hours.length - 1, 1),
        width: Math.max(hours.length - 1, 1) * timelineSlotWidth,
    };
}

function hourLabel(minutes: number) {
    return `${String(Math.floor(minutes / timelineHour)).padStart(2, '0')}:00`;
}

function eventTypeLabel(type: TimelineEventType) {
    return {
        construction: '工事',
        business: '業務予定',
        internal_notice: '業務連絡',
    }[type];
}

function eventTypeClass(type: TimelineEventType) {
    return {
        construction:
            'border-orange-300 bg-orange-100 text-orange-950 dark:border-orange-800 dark:bg-orange-950/80 dark:text-orange-100',
        business:
            'border-violet-300 bg-violet-100 text-violet-950 dark:border-violet-800 dark:bg-violet-950/80 dark:text-violet-100',
        internal_notice:
            'border-sky-300 bg-sky-100 text-sky-950 dark:border-sky-800 dark:bg-sky-950/80 dark:text-sky-100',
    }[type];
}

function eventKey(event: TimelineEvent) {
    return `${event.type}-${event.id}`;
}

function eventEditRoute(event: TimelineEvent, returnTo?: string) {
    const options =
        returnTo === undefined ? undefined : { query: { return_to: returnTo } };

    switch (event.type) {
        case 'construction':
            return editConstructionSchedule(event.id, options);
        case 'business':
            return editBusinessSchedule(event.id, options);
        case 'internal_notice':
            return editInternalNotice(event.id, options);
    }
}

function minuteInputValue(minutes: number) {
    const clampedMinutes = Math.min(Math.max(minutes, 0), 23 * 60 + 59);

    return `${String(Math.floor(clampedMinutes / timelineHour)).padStart(2, '0')}:${String(clampedMinutes % timelineHour).padStart(2, '0')}`;
}

function eventCreateRoute(
    type: TimelineEventType,
    date: string,
    prefill: {
        startsAt?: string;
        endsAt?: string;
        assignedUserIds?: number[];
        returnTo?: string;
    } = {},
) {
    const options = {
        query: {
            scheduled_on: date,
            starts_at: prefill.startsAt,
            ends_at: prefill.endsAt,
            assigned_user_ids: prefill.assignedUserIds,
            return_to: prefill.returnTo,
        },
    };

    switch (type) {
        case 'construction':
            return createConstructionSchedule(options);
        case 'business':
            return createBusinessSchedule(options);
        case 'internal_notice':
            return createInternalNotice(options);
    }
}

function eventNumberLabel(event: TimelineEvent) {
    return `#${event.schedule_number ?? '?'}`;
}

function multipleAssignedUsersCountLabel(event: TimelineEvent) {
    if (event.assigned_users.length <= 1) {
        return null;
    }

    return `${event.assigned_users.length}名`;
}

function timelineGridStyle(
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    return {
        gridTemplateColumns: `repeat(${bounds.slotCount}, minmax(0, 1fr))`,
    };
}

function timelineRowStyle(
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    return {
        gridTemplateColumns: `7rem 7rem ${bounds.width}px`,
    };
}

function eventPositionStyle(
    event: TimelineEvent,
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    const startsAt = timeToMinutes(event.starts_at) ?? bounds.startsAt;
    const endsAt = timeToMinutes(event.ends_at) ?? bounds.endsAt;
    const left = ((startsAt - bounds.startsAt) / bounds.duration) * 100;
    const width =
        ((Math.max(endsAt, startsAt + 15) - startsAt) / bounds.duration) * 100;

    return {
        left: `${Math.max(left, 0)}%`,
        width: `${Math.min(Math.max(width, 2), 100 - Math.max(left, 0))}%`,
    };
}

function selectionPositionStyle(
    selection: DragSelection,
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    const range = dragSelectionRange(selection);
    const left = ((range.startsAt - bounds.startsAt) / bounds.duration) * 100;
    const width = ((range.endsAt - range.startsAt) / bounds.duration) * 100;

    return {
        left: `${Math.max(left, 0)}%`,
        width: `${Math.min(Math.max(width, 2), 100 - Math.max(left, 0))}%`,
    };
}

function eventsForUser(events: TimelineEvent[], userId: number | null) {
    return events.filter((event) => {
        if (userId === null) {
            return event.assigned_users.length === 0;
        }

        return event.assigned_users.some((user) => user.id === userId);
    });
}

function assignedUsersLabel(event: TimelineEvent) {
    if (event.assigned_users.length === 0) {
        return '担当者未設定';
    }

    return event.assigned_users.map((user) => user.name).join('、');
}

function scheduleDetailRows(event: TimelineEvent) {
    return [
        { label: '種別', value: eventTypeLabel(event.type) },
        { label: '番号', value: eventNumberLabel(event) },
        { label: '時間', value: event.time },
        { label: '担当者', value: assignedUsersLabel(event) },
        { label: '場所', value: event.location },
        { label: '内容', value: event.content },
        { label: '補足', value: event.time_note },
    ].filter((row) => row.value !== null && row.value !== '');
}

function useScheduleDetailHold(onOpenDetail: (event: TimelineEvent) => void) {
    const holdTimeoutRef = useRef<number | null>(null);
    const holdStartedAtRef = useRef<{ x: number; y: number } | null>(null);
    const didOpenDetailRef = useRef(false);

    function clearHoldTimeout() {
        if (holdTimeoutRef.current === null) {
            return;
        }

        window.clearTimeout(holdTimeoutRef.current);
        holdTimeoutRef.current = null;
    }

    useEffect(() => {
        return () => {
            if (holdTimeoutRef.current !== null) {
                window.clearTimeout(holdTimeoutRef.current);
            }
        };
    }, []);

    function startHold(
        pointerEvent: React.PointerEvent<HTMLElement>,
        scheduleEvent: TimelineEvent,
    ) {
        if (pointerEvent.button !== 0) {
            return;
        }

        clearHoldTimeout();
        didOpenDetailRef.current = false;
        holdStartedAtRef.current = {
            x: pointerEvent.clientX,
            y: pointerEvent.clientY,
        };
        holdTimeoutRef.current = window.setTimeout(() => {
            didOpenDetailRef.current = true;
            holdTimeoutRef.current = null;
            onOpenDetail(scheduleEvent);
        }, scheduleDetailHoldDelay);
    }

    function updateHold(pointerEvent: React.PointerEvent<HTMLElement>) {
        const start = holdStartedAtRef.current;

        if (start === null || holdTimeoutRef.current === null) {
            return;
        }

        const movedX = Math.abs(pointerEvent.clientX - start.x);
        const movedY = Math.abs(pointerEvent.clientY - start.y);

        if (movedX > touchScrollTolerance || movedY > touchScrollTolerance) {
            clearHoldTimeout();
        }
    }

    function finishHold() {
        clearHoldTimeout();
        holdStartedAtRef.current = null;
    }

    function consumeClickAfterHold() {
        if (!didOpenDetailRef.current) {
            return false;
        }

        didOpenDetailRef.current = false;

        return true;
    }

    return {
        startHold,
        updateHold,
        finishHold,
        consumeClickAfterHold,
    };
}

function slotOverlapsEvents(
    startsAt: number,
    bounds: ReturnType<typeof timelineBounds>,
    events: TimelineEvent[],
) {
    const endsAt = startsAt + timelineHour;

    return events.some((event) => {
        const eventStartsAt = timeToMinutes(event.starts_at);

        if (eventStartsAt === null) {
            return false;
        }

        const eventEndsAt = timeToMinutes(event.ends_at) ?? bounds.endsAt;

        return startsAt < eventEndsAt && endsAt > eventStartsAt;
    });
}

function dragSelectionRange(selection: DragSelection) {
    const startsAt = Math.min(selection.anchorHour, selection.currentHour);
    const endsAt =
        Math.max(selection.anchorHour, selection.currentHour) + timelineHour;

    return { startsAt, endsAt };
}

function isSameTimelineRow(
    selection: DragSelection,
    date: string,
    rowName: string,
    userId: number | null,
) {
    return (
        selection.date === date &&
        selection.rowName === rowName &&
        selection.userId === userId
    );
}

function slotIsSelected(
    selection: DragSelection | null,
    date: string,
    rowName: string,
    userId: number | null,
    hour: number,
) {
    if (
        selection === null ||
        !isSameTimelineRow(selection, date, rowName, userId)
    ) {
        return false;
    }

    const range = dragSelectionRange(selection);

    return hour >= range.startsAt && hour < range.endsAt;
}

function pointerHourFromPosition(
    clientX: number,
    element: HTMLElement,
    bounds: ReturnType<typeof timelineBounds>,
) {
    const rect = element.getBoundingClientRect();
    const rawRatio = (clientX - rect.left) / rect.width;
    const ratio = Math.min(Math.max(rawRatio, 0), 0.999999);
    const slotIndex = Math.min(
        Math.max(Math.floor(ratio * bounds.slotCount), 0),
        bounds.slotCount - 1,
    );

    return bounds.startsAt + slotIndex * timelineHour;
}

function contiguousSelectionHour(
    anchorHour: number,
    targetHour: number,
    availableHours: Set<number>,
) {
    const direction = targetHour >= anchorHour ? timelineHour : -timelineHour;
    let selectedHour = anchorHour;

    for (
        let hour = anchorHour;
        direction > 0 ? hour <= targetHour : hour >= targetHour;
        hour += direction
    ) {
        if (!availableHours.has(hour)) {
            break;
        }

        selectedHour = hour;
    }

    return selectedHour;
}

function TimelineSlotLink({
    date,
    hour,
    endHour,
    rowName,
    userId,
    isSelected,
    onOpenCreateTypeDialog,
}: {
    date: string;
    hour: number;
    endHour: number;
    rowName: string;
    userId: number | null;
    isSelected: boolean;
    onOpenCreateTypeDialog: (slot: CreateSlot) => void;
}) {
    const startsAt = minuteInputValue(hour);
    const endsAt = minuteInputValue(endHour);

    return (
        <button
            type="button"
            data-timeline-slot-hour={hour}
            className={`group relative flex touch-auto items-center justify-center border-l border-neutral-100 text-[10px] font-semibold text-emerald-800 transition focus-visible:z-20 focus-visible:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-neutral-900 dark:text-emerald-200 dark:focus-visible:bg-emerald-950/30 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${isSelected ? 'bg-emerald-100 text-emerald-950 ring-1 ring-emerald-500 ring-inset dark:bg-emerald-950/60 dark:text-emerald-50 dark:ring-emerald-500' : 'hover:bg-emerald-50 dark:hover:bg-emerald-950/30'}`}
            aria-label={`${rowName} ${startsAt} から ${endsAt} まで予定を追加`}
            title={`${rowName} ${startsAt} - ${endsAt} の予定を追加`}
            onClick={(event) => {
                if (event.detail !== 0) {
                    return;
                }

                onOpenCreateTypeDialog({
                    date,
                    hour,
                    endHour,
                    rowName,
                    userId,
                });
            }}
        >
            <span
                className={`inline-flex items-center gap-1 rounded-md border border-dashed border-emerald-300 bg-white/90 px-1.5 py-1 shadow-sm transition group-hover:opacity-100 group-focus-visible:opacity-100 dark:border-emerald-800 dark:bg-neutral-950/90 ${isSelected ? 'opacity-100' : 'opacity-0'}`}
            >
                <Plus className="size-3" />
                {startsAt}
            </span>
        </button>
    );
}

function TimelineEventBlock({
    event,
    bounds,
    isHighlighted,
    onToggleHighlight,
    onOpenDetail,
    canManageSchedules,
    returnTo,
}: {
    event: TimelineEvent;
    bounds: ReturnType<typeof timelineBounds>;
    isHighlighted: boolean;
    onToggleHighlight: (event: TimelineEvent) => void;
    onOpenDetail: (event: TimelineEvent) => void;
    canManageSchedules: boolean;
    returnTo: string;
}) {
    const detailHold = useScheduleDetailHold(onOpenDetail);
    const hasOpenEnd = event.starts_at !== null && event.ends_at === null;
    const numberLabel = eventNumberLabel(event);
    const multipleAssignedUsersCount = multipleAssignedUsersCountLabel(event);
    const label = [
        eventTypeLabel(event.type),
        numberLabel,
        event.title,
        event.time,
        multipleAssignedUsersCount,
        assignedUsersLabel(event),
    ]
        .filter(Boolean)
        .join(' ');
    const hasMultipleAssignedUsers = event.assigned_users.length > 1;
    const className = `absolute top-1 bottom-1 min-w-12 overflow-hidden rounded-md border text-left shadow-sm transition ${eventTypeClass(event.type)} ${hasOpenEnd ? 'border-dashed' : ''} ${isHighlighted ? 'z-10 ring-2 ring-neutral-950 ring-offset-2 dark:ring-white dark:ring-offset-neutral-950' : ''}`;
    const style = {
        ...eventPositionStyle(event, bounds),
        ...(hasOpenEnd
            ? {
                  backgroundImage:
                      'repeating-linear-gradient(135deg, rgb(255 255 255 / 0.28) 0, rgb(255 255 255 / 0.28) 6px, transparent 6px, transparent 12px)',
              }
            : {}),
    };
    const content = (
        <>
            <div className="flex min-w-0 items-center gap-1 truncate pr-5 text-[11px] leading-tight font-semibold">
                <span className="shrink-0 rounded-full bg-gray-50 p-1">
                    {numberLabel}
                </span>
                <span className="min-w-0 truncate">{event.title}</span>
                {multipleAssignedUsersCount && (
                    <span className="shrink-0">
                        {multipleAssignedUsersCount}
                    </span>
                )}
            </div>
        </>
    );

    if (canManageSchedules) {
        return (
            <div className={className} style={style} title={label}>
                <button
                    type="button"
                    className={`h-full w-full px-1.5 py-1 text-left focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
                    aria-label={label}
                    aria-pressed={
                        hasMultipleAssignedUsers ? isHighlighted : undefined
                    }
                    onPointerDown={(pointerEvent) =>
                        detailHold.startHold(pointerEvent, event)
                    }
                    onPointerMove={detailHold.updateHold}
                    onPointerUp={detailHold.finishHold}
                    onPointerCancel={detailHold.finishHold}
                    onPointerLeave={detailHold.finishHold}
                    onClick={() => {
                        if (detailHold.consumeClickAfterHold()) {
                            return;
                        }

                        onToggleHighlight(event);
                    }}
                >
                    {content}
                </button>
                <Link
                    href={eventEditRoute(event, returnTo)}
                    className="absolute top-1.5 right-1.5 inline-flex size-6 items-center justify-center rounded-md bg-white/80 shadow-sm transition hover:bg-white focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-black/40 dark:hover:bg-black/60 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                    title={`${label} を編集`}
                    aria-label={`${label} を編集`}
                >
                    <Pencil className="size-3.5" />
                </Link>
            </div>
        );
    }

    return (
        <button
            type="button"
            className={`${className} px-1.5 py-1 focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
            style={style}
            title={label}
            aria-label={label}
            aria-pressed={hasMultipleAssignedUsers ? isHighlighted : undefined}
            onPointerDown={(pointerEvent) =>
                detailHold.startHold(pointerEvent, event)
            }
            onPointerMove={detailHold.updateHold}
            onPointerUp={detailHold.finishHold}
            onPointerCancel={detailHold.finishHold}
            onPointerLeave={detailHold.finishHold}
            onClick={() => {
                if (detailHold.consumeClickAfterHold()) {
                    return;
                }

                onToggleHighlight(event);
            }}
        >
            {content}
        </button>
    );
}

function UntimedEventChip({
    event,
    isHighlighted,
    onToggleHighlight,
    onOpenDetail,
    canManageSchedules,
    returnTo,
}: {
    event: TimelineEvent;
    isHighlighted: boolean;
    onToggleHighlight: (event: TimelineEvent) => void;
    onOpenDetail: (event: TimelineEvent) => void;
    canManageSchedules: boolean;
    returnTo: string;
}) {
    const detailHold = useScheduleDetailHold(onOpenDetail);
    const numberLabel = eventNumberLabel(event);
    const multipleAssignedUsersCount = multipleAssignedUsersCountLabel(event);
    const label = [
        eventTypeLabel(event.type),
        numberLabel,
        event.title,
        event.time,
        multipleAssignedUsersCount,
        assignedUsersLabel(event),
    ]
        .filter(Boolean)
        .join(' ');
    const hasMultipleAssignedUsers = event.assigned_users.length > 1;
    const className = `inline-flex max-w-full items-center gap-1 rounded-md border text-left text-xs font-semibold transition ${eventTypeClass(event.type)} ${isHighlighted ? 'ring-2 ring-neutral-950 ring-offset-2 dark:ring-white dark:ring-offset-neutral-950' : ''}`;
    const content = (
        <>
            {/* <span className="shrink-0 rounded-full bg-gray-50 p-1">{numberLabel}</span> */}
            <span className="min-w-0 truncate">{event.title}</span>
            {multipleAssignedUsersCount && (
                <span className="shrink-0">{multipleAssignedUsersCount}</span>
            )}
        </>
    );

    if (canManageSchedules) {
        return (
            <span className={`${className} overflow-hidden`} title={label}>
                <button
                    type="button"
                    className={`inline-flex min-w-0 items-center gap-1 px-2 py-1 text-left focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
                    aria-label={label}
                    aria-pressed={
                        hasMultipleAssignedUsers ? isHighlighted : undefined
                    }
                    onPointerDown={(pointerEvent) =>
                        detailHold.startHold(pointerEvent, event)
                    }
                    onPointerMove={detailHold.updateHold}
                    onPointerUp={detailHold.finishHold}
                    onPointerCancel={detailHold.finishHold}
                    onPointerLeave={detailHold.finishHold}
                    onClick={() => {
                        if (detailHold.consumeClickAfterHold()) {
                            return;
                        }

                        onToggleHighlight(event);
                    }}
                >
                    {content}
                </button>
                <Link
                    href={eventEditRoute(event, returnTo)}
                    className="inline-flex size-7 shrink-0 items-center justify-center border-l border-current/20 bg-white/50 transition hover:bg-white focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-black/20 dark:hover:bg-black/40 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                    title={`${label} を編集`}
                    aria-label={`${label} を編集`}
                >
                    <Pencil className="size-3.5" />
                </Link>
            </span>
        );
    }

    return (
        <button
            type="button"
            className={`${className} px-2 py-1 focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
            title={label}
            aria-pressed={hasMultipleAssignedUsers ? isHighlighted : undefined}
            onPointerDown={(pointerEvent) =>
                detailHold.startHold(pointerEvent, event)
            }
            onPointerMove={detailHold.updateHold}
            onPointerUp={detailHold.finishHold}
            onPointerCancel={detailHold.finishHold}
            onPointerLeave={detailHold.finishHold}
            onClick={() => {
                if (detailHold.consumeClickAfterHold()) {
                    return;
                }

                onToggleHighlight(event);
            }}
        >
            {content}
        </button>
    );
}

function DayTimeline({
    selectedDetail,
    selectedDayTimeline,
    canManageSchedules,
    returnTo,
}: {
    selectedDetail: CalendarDay;
    selectedDayTimeline: SelectedDayTimeline;
    canManageSchedules: boolean;
    returnTo: string;
}) {
    const bounds = timelineBounds(selectedDayTimeline.events);
    const [highlightedEventKey, setHighlightedEventKey] = useState<
        string | null
    >(null);
    const [createSlot, setCreateSlot] = useState<{
        date: string;
        hour: number;
        endHour: number;
        rowName: string;
        userId: number | null;
    } | null>(null);
    const [detailEvent, setDetailEvent] = useState<TimelineEvent | null>(null);
    const [dragSelection, setDragSelection] = useState<DragSelection | null>(
        null,
    );
    const dragSelectionRef = useRef<DragSelection | null>(null);
    const pendingDragSelectionRef = useRef<PendingDragSelection | null>(null);

    useEffect(() => {
        return () => {
            const pendingSelection = pendingDragSelectionRef.current;

            if (pendingSelection !== null) {
                window.clearTimeout(pendingSelection.timeoutId);
            }
        };
    }, []);
    const hasUnassignedEvents = selectedDayTimeline.events.some(
        (event) => event.assigned_users.length === 0,
    );
    const rows: { id: number | null; name: string; muted?: boolean }[] = [
        ...selectedDayTimeline.users.map((user) => ({
            id: user.id,
            name: user.name,
        })),
        ...(hasUnassignedEvents
            ? [{ id: null, name: '担当者未設定', muted: true }]
            : []),
    ];
    const timelineWidth = `calc(22rem + ${bounds.width}px)`;

    function toggleHighlightedEvent(event: TimelineEvent) {
        if (event.assigned_users.length <= 1) {
            return;
        }

        const key = eventKey(event);
        setHighlightedEventKey((currentKey) =>
            currentKey === key ? null : key,
        );
    }

    const slotStartsAt =
        createSlot === null ? null : minuteInputValue(createSlot.hour);
    const slotEndsAt =
        createSlot === null ? null : minuteInputValue(createSlot.endHour);

    function commitDragSelection(selection: DragSelection | null) {
        dragSelectionRef.current = selection;
        setDragSelection(selection);
    }

    function clearPendingDragSelection() {
        const pendingSelection = pendingDragSelectionRef.current;

        if (pendingSelection === null) {
            return;
        }

        window.clearTimeout(pendingSelection.timeoutId);
        pendingDragSelectionRef.current = null;
    }

    function safelyCapturePointer(element: HTMLElement, pointerId: number) {
        try {
            element.setPointerCapture(pointerId);
        } catch {
            return false;
        }

        return true;
    }

    function safelyReleasePointer(element: HTMLElement, pointerId: number) {
        if (!element.hasPointerCapture(pointerId)) {
            return;
        }

        element.releasePointerCapture(pointerId);
    }

    function activatePendingDragSelection(pointerId: number) {
        const pendingSelection = pendingDragSelectionRef.current;

        if (
            pendingSelection === null ||
            pendingSelection.pointerId !== pointerId
        ) {
            return;
        }

        pendingDragSelectionRef.current = null;

        if (
            !safelyCapturePointer(
                pendingSelection.rowElement,
                pendingSelection.pointerId,
            )
        ) {
            return;
        }

        commitDragSelection({
            date: pendingSelection.date,
            rowName: pendingSelection.rowName,
            userId: pendingSelection.userId,
            anchorHour: pendingSelection.anchorHour,
            currentHour: pendingSelection.anchorHour,
            pointerId: pendingSelection.pointerId,
        });
    }

    function startSlotSelection(
        event: React.PointerEvent<HTMLDivElement>,
        rowName: string,
        userId: number | null,
        availableHours: Set<number>,
    ) {
        if (event.button !== 0) {
            return;
        }

        const slotButton = (event.target as HTMLElement).closest<HTMLElement>(
            '[data-timeline-slot-hour]',
        );

        if (slotButton === null) {
            return;
        }

        const hour = Number(slotButton.dataset.timelineSlotHour);

        if (!availableHours.has(hour)) {
            return;
        }

        const rowElement = event.currentTarget;

        clearPendingDragSelection();

        if (event.pointerType === 'touch' || event.pointerType === 'pen') {
            const pointerId = event.pointerId;

            pendingDragSelectionRef.current = {
                date: selectedDetail.date,
                rowName,
                userId,
                anchorHour: hour,
                pointerId,
                startX: event.clientX,
                startY: event.clientY,
                rowElement,
                timeoutId: window.setTimeout(() => {
                    activatePendingDragSelection(pointerId);
                }, touchSelectionDelay),
            };

            return;
        }

        safelyCapturePointer(rowElement, event.pointerId);
        event.preventDefault();

        commitDragSelection({
            date: selectedDetail.date,
            rowName,
            userId,
            anchorHour: hour,
            currentHour: hour,
            pointerId: event.pointerId,
        });
    }

    function updateSlotSelection(
        event: React.PointerEvent<HTMLDivElement>,
        rowName: string,
        userId: number | null,
        availableHours: Set<number>,
    ) {
        const pendingSelection = pendingDragSelectionRef.current;

        if (
            pendingSelection !== null &&
            pendingSelection.pointerId === event.pointerId
        ) {
            const movedX = Math.abs(event.clientX - pendingSelection.startX);
            const movedY = Math.abs(event.clientY - pendingSelection.startY);

            if (
                movedX > touchScrollTolerance ||
                movedY > touchScrollTolerance
            ) {
                clearPendingDragSelection();
            }

            return;
        }

        const activeSelection = dragSelectionRef.current;

        if (
            activeSelection === null ||
            activeSelection.pointerId !== event.pointerId ||
            !isSameTimelineRow(
                activeSelection,
                selectedDetail.date,
                rowName,
                userId,
            )
        ) {
            return;
        }

        event.preventDefault();

        const rowElement = event.currentTarget;
        const clientX = event.clientX;

        setDragSelection((selection) => {
            if (
                selection === null ||
                selection.pointerId !== event.pointerId ||
                !isSameTimelineRow(
                    selection,
                    selectedDetail.date,
                    rowName,
                    userId,
                )
            ) {
                return selection;
            }

            const targetHour = pointerHourFromPosition(
                clientX,
                rowElement,
                bounds,
            );
            const currentHour = contiguousSelectionHour(
                selection.anchorHour,
                targetHour,
                availableHours,
            );
            const nextSelection = {
                ...selection,
                currentHour,
            };

            dragSelectionRef.current = nextSelection;

            return nextSelection;
        });
    }

    function finishSlotSelection(event: React.PointerEvent<HTMLDivElement>) {
        const pendingSelection = pendingDragSelectionRef.current;

        if (
            pendingSelection !== null &&
            pendingSelection.pointerId === event.pointerId
        ) {
            clearPendingDragSelection();
            setCreateSlot({
                date: pendingSelection.date,
                hour: pendingSelection.anchorHour,
                endHour: pendingSelection.anchorHour + timelineHour,
                rowName: pendingSelection.rowName,
                userId: pendingSelection.userId,
            });

            return;
        }

        const selection = dragSelectionRef.current;

        if (selection === null || selection.pointerId !== event.pointerId) {
            return;
        }

        safelyReleasePointer(event.currentTarget, event.pointerId);

        event.preventDefault();

        const range = dragSelectionRange(selection);

        commitDragSelection(null);
        setCreateSlot({
            date: selection.date,
            hour: range.startsAt,
            endHour: range.endsAt,
            rowName: selection.rowName,
            userId: selection.userId,
        });
    }

    function cancelSlotSelection(event: React.PointerEvent<HTMLDivElement>) {
        clearPendingDragSelection();

        if (dragSelectionRef.current !== null) {
            safelyReleasePointer(
                event.currentTarget,
                dragSelectionRef.current.pointerId,
            );
        }

        commitDragSelection(null);
    }

    function createFromSlot(type: TimelineEventType) {
        if (
            createSlot === null ||
            slotStartsAt === null ||
            slotEndsAt === null
        ) {
            return;
        }

        const assignedUserIds =
            createSlot.userId === null ? [] : [createSlot.userId];

        router.visit(
            eventCreateRoute(type, createSlot.date, {
                startsAt: slotStartsAt,
                endsAt: slotEndsAt,
                assignedUserIds,
                returnTo,
            }),
        );
    }

    return (
        <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">選択日</p>
                    <h2 className="mt-1 text-2xl font-bold">
                        {detailDate(selectedDetail.date)}
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        {canManageSchedules
                            ? '複数担当の予定を選択すると同じ予定の担当者を確認できます。予定を長押しすると詳細を確認できます。編集は鉛筆アイコンまたは詳細から行えます。空き時間はクリックで1時間、押したまま左右にドラッグで連続した空き時間をまとめて作成できます。'
                            : '空き時間は白い余白、時間未定は左側の列に表示されます。'}
                    </p>
                </div>
                <div className="flex flex-col gap-3 lg:items-end">
                    <Button
                        asChild
                        className="w-full rounded-md lg:w-auto"
                        variant="outline"
                    >
                        <Link
                            href={scheduleIndex({
                                query: {
                                    range: 'today',
                                    date: selectedDetail.date,
                                    type: [
                                        'construction',
                                        'business',
                                        'internal_notice',
                                    ],
                                },
                            })}
                        >
                            予定一覧で確認
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-5 flex flex-wrap gap-2 text-xs font-semibold">
                {(['construction', 'business', 'internal_notice'] as const).map(
                    (type) => (
                        <span
                            key={type}
                            className={`rounded-md border px-2 py-1 ${eventTypeClass(type)}`}
                        >
                            {eventTypeLabel(type)}
                        </span>
                    ),
                )}
                <span className="rounded-md border border-neutral-300 px-2 py-1 text-neutral-700 dark:border-neutral-700 dark:text-neutral-300">
                    斜線: 終了未定
                </span>
            </div>

            <div className="mt-4 overflow-x-auto">
                <div className="min-w-full" style={{ width: timelineWidth }}>
                    <div
                        className="grid border-b border-neutral-200 text-xs font-semibold text-muted-foreground dark:border-neutral-800"
                        style={timelineRowStyle(bounds)}
                    >
                        <div className="px-3 py-2">担当者</div>
                        <div className="px-3 py-2">時間未定</div>
                        <div className="grid" style={timelineGridStyle(bounds)}>
                            {bounds.hours.slice(0, -1).map((hour) => (
                                <span
                                    key={hour}
                                    className="border-l border-neutral-200 px-2 py-2 dark:border-neutral-800"
                                >
                                    {hourLabel(hour)}
                                </span>
                            ))}
                        </div>
                    </div>

                    <div className="divide-y divide-neutral-200 dark:divide-neutral-800">
                        {rows.map((row) => {
                            const rowEvents = eventsForUser(
                                selectedDayTimeline.events,
                                row.id,
                            );
                            const untimedEvents = rowEvents.filter(
                                (event) => event.starts_at === null,
                            );
                            const timedEvents = rowEvents.filter(
                                (event) => event.starts_at !== null,
                            );
                            const availableHours = bounds.hours
                                .slice(0, -1)
                                .filter(
                                    (hour) =>
                                        !slotOverlapsEvents(
                                            hour,
                                            bounds,
                                            timedEvents,
                                        ),
                                );
                            const availableHourSet = new Set(availableHours);
                            const rowDragSelection =
                                dragSelection !== null &&
                                isSameTimelineRow(
                                    dragSelection,
                                    selectedDetail.date,
                                    row.name,
                                    row.id,
                                )
                                    ? dragSelection
                                    : null;

                            return (
                                <div
                                    key={row.id ?? 'unassigned'}
                                    className="grid min-h-12"
                                    style={timelineRowStyle(bounds)}
                                >
                                    <div className="flex min-w-0 items-center px-3 py-1.5">
                                        <span
                                            className={`truncate text-sm font-semibold ${row.muted ? 'text-muted-foreground' : ''}`}
                                        >
                                            {row.name}
                                        </span>
                                    </div>
                                    <div className="flex min-w-0 flex-wrap content-center gap-1 px-3 py-1">
                                        {untimedEvents.length > 0 ? (
                                            untimedEvents.map((event) => (
                                                <UntimedEventChip
                                                    key={`${event.type}-${event.id}`}
                                                    event={event}
                                                    isHighlighted={
                                                        highlightedEventKey ===
                                                        eventKey(event)
                                                    }
                                                    onToggleHighlight={
                                                        toggleHighlightedEvent
                                                    }
                                                    onOpenDetail={
                                                        setDetailEvent
                                                    }
                                                    canManageSchedules={
                                                        canManageSchedules
                                                    }
                                                    returnTo={returnTo}
                                                />
                                            ))
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                -
                                            </span>
                                        )}
                                    </div>
                                    <div className="relative min-h-12 bg-white dark:bg-neutral-950">
                                        <div
                                            className="absolute inset-0 grid"
                                            style={timelineGridStyle(bounds)}
                                            aria-hidden="true"
                                        >
                                            {bounds.hours
                                                .slice(0, -1)
                                                .map((hour) => (
                                                    <span
                                                        key={hour}
                                                        className="border-l border-neutral-100 dark:border-neutral-900"
                                                    />
                                                ))}
                                        </div>
                                        {canManageSchedules && (
                                            <div
                                                className="absolute inset-0 grid select-none"
                                                style={timelineGridStyle(
                                                    bounds,
                                                )}
                                                onPointerDown={(event) =>
                                                    startSlotSelection(
                                                        event,
                                                        row.name,
                                                        row.id,
                                                        availableHourSet,
                                                    )
                                                }
                                                onPointerMove={(event) =>
                                                    updateSlotSelection(
                                                        event,
                                                        row.name,
                                                        row.id,
                                                        availableHourSet,
                                                    )
                                                }
                                                onPointerUp={
                                                    finishSlotSelection
                                                }
                                                onPointerCancel={
                                                    cancelSlotSelection
                                                }
                                            >
                                                {bounds.hours
                                                    .slice(0, -1)
                                                    .map((hour) =>
                                                        !availableHourSet.has(
                                                            hour,
                                                        ) ? (
                                                            <span
                                                                key={hour}
                                                                className="border-l border-transparent"
                                                                aria-hidden="true"
                                                            />
                                                        ) : (
                                                            <TimelineSlotLink
                                                                key={hour}
                                                                date={
                                                                    selectedDetail.date
                                                                }
                                                                hour={hour}
                                                                endHour={
                                                                    hour +
                                                                    timelineHour
                                                                }
                                                                rowName={
                                                                    row.name
                                                                }
                                                                userId={row.id}
                                                                isSelected={slotIsSelected(
                                                                    dragSelection,
                                                                    selectedDetail.date,
                                                                    row.name,
                                                                    row.id,
                                                                    hour,
                                                                )}
                                                                onOpenCreateTypeDialog={
                                                                    setCreateSlot
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                {rowDragSelection !== null && (
                                                    <div
                                                        className="pointer-events-none absolute top-1 bottom-1 z-10 flex items-center justify-center rounded-md border border-emerald-600 bg-emerald-500/15 px-2 text-[11px] font-bold text-emerald-950 shadow-sm ring-1 ring-white/80 dark:border-emerald-300 dark:bg-emerald-400/20 dark:text-emerald-50 dark:ring-neutral-950/80"
                                                        style={selectionPositionStyle(
                                                            rowDragSelection,
                                                            bounds,
                                                        )}
                                                    >
                                                        <span className="truncate rounded-md bg-white/90 px-1.5 py-0.5 shadow-sm dark:bg-neutral-950/90">
                                                            {minuteInputValue(
                                                                dragSelectionRange(
                                                                    rowDragSelection,
                                                                ).startsAt,
                                                            )}
                                                            {' - '}
                                                            {minuteInputValue(
                                                                dragSelectionRange(
                                                                    rowDragSelection,
                                                                ).endsAt,
                                                            )}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                        {timedEvents.length > 0 ? (
                                            timedEvents.map((event) => (
                                                <TimelineEventBlock
                                                    key={`${event.type}-${event.id}`}
                                                    event={event}
                                                    bounds={bounds}
                                                    isHighlighted={
                                                        highlightedEventKey ===
                                                        eventKey(event)
                                                    }
                                                    onToggleHighlight={
                                                        toggleHighlightedEvent
                                                    }
                                                    onOpenDetail={
                                                        setDetailEvent
                                                    }
                                                    canManageSchedules={
                                                        canManageSchedules
                                                    }
                                                    returnTo={returnTo}
                                                />
                                            ))
                                        ) : (
                                            <div className="absolute top-1/2 left-3 -translate-y-1/2">
                                                {!canManageSchedules && (
                                                    <span className="text-xs text-emerald-700 dark:text-emerald-300">
                                                        空き
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
            <Dialog
                open={createSlot !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreateSlot(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>新規作成</DialogTitle>
                        <DialogDescription>
                            {createSlot === null
                                ? '追加したい内容を選択してください。'
                                : `${createSlot.rowName} / ${slotStartsAt} - ${slotEndsAt} の予定種別を選択してください。`}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-auto justify-start gap-3 rounded-xl px-4 py-3"
                            onClick={() => createFromSlot('construction')}
                        >
                            <Hammer className="size-4" />
                            工事予定を作成
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-auto justify-start gap-3 rounded-xl px-4 py-3"
                            onClick={() => createFromSlot('business')}
                        >
                            <BriefcaseBusiness className="size-4" />
                            業務予定を作成
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-auto justify-start gap-3 rounded-xl px-4 py-3"
                            onClick={() => createFromSlot('internal_notice')}
                        >
                            <Megaphone className="size-4" />
                            業務連絡を作成
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog
                open={detailEvent !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailEvent(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    {detailEvent !== null && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{detailEvent.title}</DialogTitle>
                                <DialogDescription>
                                    内容を確認して、必要に応じて編集できます。
                                </DialogDescription>
                            </DialogHeader>
                            <dl className="grid gap-3 text-sm">
                                {scheduleDetailRows(detailEvent).map((row) => (
                                    <div
                                        key={row.label}
                                        className="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-3"
                                    >
                                        <dt className="text-muted-foreground">
                                            {row.label}
                                        </dt>
                                        <dd className="min-w-0 font-medium break-words whitespace-pre-wrap">
                                            {row.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {canManageSchedules && (
                                <Button asChild className="w-full rounded-md">
                                    <Link
                                        href={eventEditRoute(
                                            detailEvent,
                                            returnTo,
                                        )}
                                    >
                                        <Pencil className="size-4" />
                                        編集ページへ
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </section>
    );
}

export default function ScheduleOverviewIndex({
    filters,
    todayDate,
    month,
    calendarDays,
    canManageSchedules,
    selectedDayTimeline,
}: Props) {
    const { url } = usePage();
    const selectedDay =
        calendarDays.find((day) => day.date === filters.date) ?? null;
    const cells = calendarCells(filters.date, todayDate, month, calendarDays);
    const busiestScheduleCount = maxScheduleCount(cells);
    const previousMonthDate = adjacentMonthDate(filters.date, -1);
    const nextMonthDate = adjacentMonthDate(filters.date, 1);
    const selectedDetail = selectedDay ?? {
        date: filters.date,
        construction_count: 0,
        business_count: 0,
        internal_notice_count: 0,
        unconfirmed_voucher_count: 0,
        schedule_count: 0,
    };

    return (
        <>
            <Head title="予定カレンダー" />
            <main className="min-h-screen bg-neutral-100 p-3 text-neutral-950 md:p-6 dark:bg-neutral-950 dark:text-neutral-50">
                <div className="mx-auto flex max-w-7xl flex-col gap-4">
                    {/* <header className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950"> */}
                    {/*     <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"> */}
                    {/*         <div className="flex items-start gap-3"> */}
                    {/*             <span className="rounded-lg bg-teal-100 p-3 text-teal-800 dark:bg-teal-950 dark:text-teal-100"> */}
                    {/*                 <CalendarDays className="size-6" /> */}
                    {/*             </span> */}
                    {/*             <div> */}
                    {/*                 <p className="text-sm text-muted-foreground"> */}
                    {/*                     Company Schedule */}
                    {/*                 </p> */}
                    {/*                 <h1 className="text-2xl font-bold tracking-normal"> */}
                    {/*                     予定カレンダー */}
                    {/*                 </h1> */}
                    {/*             </div> */}
                    {/*         </div> */}
                    {/*     </div> */}
                    {/* </header> */}

                    <div className="flex flex-col gap-4">
                        <section className="rounded-lg border border-neutral-200 bg-white p-3 md:p-4 dark:border-neutral-800 dark:bg-neutral-950">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h2 className="text-xl font-bold">
                                    {monthTitle(filters.date)}
                                </h2>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-md"
                                    >
                                        <Link
                                            href={overviewIndex(
                                                overviewQuery(
                                                    previousMonthDate,
                                                ),
                                            )}
                                        >
                                            <ChevronLeft className="size-4" />
                                            前月
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="secondary"
                                        className="rounded-md"
                                    >
                                        <Link
                                            href={overviewIndex(
                                                overviewQuery(todayDate),
                                            )}
                                        >
                                            今日
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-md"
                                    >
                                        <Link
                                            href={overviewIndex(
                                                overviewQuery(nextMonthDate),
                                            )}
                                        >
                                            翌月
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                                <div
                                    className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                    aria-label="予定の多さ"
                                >
                                    予定の多さ:
                                    <span>少</span>
                                    {[0, 1, 2, 3, 4].map((level) => (
                                        <span
                                            key={level}
                                            className={`size-5 rounded-md border ${heatCellClass(level)}`}
                                            aria-label={heatLabel(level)}
                                        />
                                    ))}
                                    <span>多</span>
                                </div>
                            </div>

                            <div className="mt-4">
                                <div className="w-full">
                                    <div className="grid grid-cols-7 gap-0.5 text-center text-xs font-semibold text-muted-foreground sm:gap-1">
                                        {weekdayLabels.map((weekday, index) => (
                                            <span
                                                key={weekday}
                                                className={
                                                    index === 0
                                                        ? 'text-red-600 dark:text-red-300'
                                                        : undefined
                                                }
                                            >
                                                {weekday}
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-2 grid grid-cols-7 gap-0.5 sm:gap-1">
                                        {cells.map((day) => (
                                            <div
                                                key={day.date}
                                                className={`relative min-h-[5.75rem] rounded-lg border p-1 transition sm:min-h-28 sm:p-2 ${dayCellClass(day, busiestScheduleCount)}`}
                                            >
                                                <span className="relative flex items-center justify-between gap-1">
                                                    <Link
                                                        href={overviewIndex(
                                                            overviewQuery(
                                                                day.date,
                                                            ),
                                                        )}
                                                        className={`relative z-10 flex size-6 items-center justify-center rounded-full text-lg font-bold tabular-nums focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none sm:size-7 sm:text-lg dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${day.isToday ? 'bg-neutral-800 text-white dark:bg-white dark:text-neutral-950' : ''}`}
                                                        aria-current={
                                                            day.isSelected
                                                                ? 'date'
                                                                : undefined
                                                        }
                                                        aria-label={`${day.date}: 混雑度${heatLabel(heatLevel(day, busiestScheduleCount))}、工事${day.construction_count}件、業務予定${day.business_count}件、業務連絡${day.internal_notice_count}件、伝票${voucherConfirmationValue(day)}、未確認伝票${day.unconfirmed_voucher_count}件`}
                                                        preserveScroll
                                                    >
                                                        {day.isToday
                                                            ? '今'
                                                            : day.label}
                                                    </Link>
                                                </span>
                                                <hr />
                                                <span className="relative flex flex-col gap-0.5">
                                                    <Link
                                                        href={scheduleIndex({
                                                            query: {
                                                                range: 'today',
                                                                date: day.date,
                                                                type: [
                                                                    'construction',
                                                                    'business',
                                                                ],
                                                            },
                                                        })}
                                                        className="pointer-events-auto relative z-10 flex rounded-md focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                                                        aria-label={`${day.date} の工事予定を確認`}
                                                    >
                                                        <MetricPill
                                                            label="工"
                                                            value={
                                                                day.construction_count
                                                            }
                                                            shortLabel="工"
                                                        />
                                                    </Link>
                                                    <Link
                                                        href={scheduleIndex({
                                                            query: {
                                                                range: 'today',
                                                                date: day.date,
                                                                type: [
                                                                    'construction',
                                                                    'business',
                                                                ],
                                                            },
                                                        })}
                                                        className="pointer-events-auto relative z-10 flex rounded-md focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                                                        aria-label={`${day.date} の業務予定を確認`}
                                                    >
                                                        <MetricPill
                                                            label="業"
                                                            value={
                                                                day.business_count
                                                            }
                                                            shortLabel="業"
                                                        />
                                                    </Link>
                                                    <Link
                                                        href={scheduleIndex({
                                                            query: {
                                                                range: 'today',
                                                                date: day.date,
                                                                type: [
                                                                    'construction',
                                                                    'business',
                                                                ],
                                                            },
                                                        })}
                                                        className="pointer-events-auto relative z-10 flex rounded-md focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                                                        aria-label={`${day.date} の業務連絡を確認`}
                                                    >
                                                        <MetricPill
                                                            label="連"
                                                            value={
                                                                day.internal_notice_count
                                                            }
                                                            shortLabel="連"
                                                        />
                                                    </Link>
                                                    <hr />
                                                    <Link
                                                        href={voucherIndex({
                                                            query: {
                                                                date: day.date,
                                                                day: day.date,
                                                                checked:
                                                                    'unchecked',
                                                            },
                                                        })}
                                                        className="pointer-events-auto relative z-10 flex items-center gap-1 rounded-md focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                                                        aria-label={`${day.date} の未確認伝票を確認`}
                                                    >
                                                        <MetricPill
                                                            label="伝"
                                                            value={voucherConfirmationValue(
                                                                day,
                                                            )}
                                                            tone={
                                                                day.unconfirmed_voucher_count >
                                                                0
                                                                    ? 'danger'
                                                                    : 'neutral'
                                                            }
                                                            shortLabel="伝"
                                                            className="min-w-0 flex-1"
                                                        />
                                                    </Link>
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <DayTimeline
                            selectedDetail={selectedDetail}
                            selectedDayTimeline={selectedDayTimeline}
                            canManageSchedules={canManageSchedules}
                            returnTo={url}
                        />
                    </div>
                </div>
            </main>
        </>
    );
}

ScheduleOverviewIndex.layout = {
    breadcrumbs: [
        {
            title: '予定カレンダー',
            href: overviewIndex(),
        },
    ],
};
