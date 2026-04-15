import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Users,
} from 'lucide-react';
import { useState } from 'react';
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
            className={`inline-flex min-h-6 items-center justify-between gap-1 rounded-md px-1 py-0.5 text-[10px] leading-none font-semibold sm:min-h-7 sm:gap-2 sm:px-2.5 sm:py-1 sm:text-xs ${metricTone(tone)} ${className}`}
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
const timelineDefaultEnd = 17 * 60;
const timelineHour = 60;
const timelineSlotWidth = 88;

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
    return event.schedule_number === null
        ? null
        : `番号 ${event.schedule_number}`;
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
        gridTemplateColumns: `10rem 12rem ${bounds.width}px`,
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

function TimelineSlotLink({
    date,
    hour,
    rowName,
    userId,
    returnTo,
}: {
    date: string;
    hour: number;
    rowName: string;
    userId: number | null;
    returnTo: string;
}) {
    const startsAt = minuteInputValue(hour);
    const endsAt = minuteInputValue(hour + timelineHour);
    const assignedUserIds = userId === null ? [] : [userId];

    return (
        <Link
            href={eventCreateRoute('construction', date, {
                startsAt,
                endsAt,
                assignedUserIds,
                returnTo,
            })}
            className="group relative flex items-center justify-center border-l border-neutral-100 text-[10px] font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:z-20 focus-visible:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:border-neutral-900 dark:text-emerald-200 dark:hover:bg-emerald-950/30 dark:focus-visible:bg-emerald-950/30 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
            aria-label={`${rowName} ${startsAt} から工事予定を追加`}
            title={`${rowName} ${startsAt} から工事予定を追加`}
        >
            <span className="inline-flex items-center gap-1 rounded-md border border-dashed border-emerald-300 bg-white/90 px-1.5 py-1 opacity-0 shadow-sm transition group-hover:opacity-100 group-focus-visible:opacity-100 dark:border-emerald-800 dark:bg-neutral-950/90">
                <Plus className="size-3" />
                {startsAt}
            </span>
        </Link>
    );
}

function TimelineEventBlock({
    event,
    bounds,
    isHighlighted,
    onToggleHighlight,
    canManageSchedules,
    returnTo,
}: {
    event: TimelineEvent;
    bounds: ReturnType<typeof timelineBounds>;
    isHighlighted: boolean;
    onToggleHighlight: (event: TimelineEvent) => void;
    canManageSchedules: boolean;
    returnTo: string;
}) {
    const hasOpenEnd = event.starts_at !== null && event.ends_at === null;
    const numberLabel = eventNumberLabel(event);
    const label = [
        eventTypeLabel(event.type),
        numberLabel,
        event.title,
        event.time,
        assignedUsersLabel(event),
    ]
        .filter(Boolean)
        .join(' ');
    const hasMultipleAssignedUsers = event.assigned_users.length > 1;
    const className = `absolute top-2 bottom-2 min-w-24 overflow-hidden rounded-md border text-left shadow-sm transition ${eventTypeClass(event.type)} ${hasOpenEnd ? 'border-dashed' : ''} ${isHighlighted ? 'z-10 ring-2 ring-neutral-950 ring-offset-2 dark:ring-white dark:ring-offset-neutral-950' : ''}`;
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
            <div className="flex items-center gap-1 pr-5 text-[10px] leading-none font-bold">
                {numberLabel && (
                    <span className="rounded bg-white/70 px-1 py-0.5 text-[10px] dark:bg-black/30">
                        {numberLabel}
                    </span>
                )}
                <span>{eventTypeLabel(event.type)}</span>
                {hasMultipleAssignedUsers && (
                    <span className="inline-flex items-center gap-0.5 rounded bg-white/70 px-1 py-0.5 text-[10px] dark:bg-black/30">
                        <Users className="size-3" />
                        {event.assigned_users.length}名
                    </span>
                )}
                {hasOpenEnd && <span>終了未定</span>}
            </div>
            <div className="mt-1 truncate pr-5 text-xs font-semibold">
                {event.title}
            </div>
            <div className="truncate pr-5 text-[10px] leading-tight opacity-80">
                {event.time}
            </div>
            {hasMultipleAssignedUsers && isHighlighted && (
                <div className="mt-1 truncate pr-5 text-[10px] leading-tight font-semibold">
                    {assignedUsersLabel(event)}
                </div>
            )}
        </>
    );

    if (canManageSchedules) {
        return (
            <div className={className} style={style} title={label}>
                <button
                    type="button"
                    className={`h-full w-full px-2 py-2 text-left focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
                    aria-label={label}
                    aria-pressed={
                        hasMultipleAssignedUsers ? isHighlighted : undefined
                    }
                    onClick={() => onToggleHighlight(event)}
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
            className={`${className} px-2 py-2 focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${hasMultipleAssignedUsers ? 'cursor-pointer' : 'cursor-default'}`}
            style={style}
            title={label}
            aria-label={label}
            aria-pressed={hasMultipleAssignedUsers ? isHighlighted : undefined}
            onClick={() => onToggleHighlight(event)}
        >
            {content}
        </button>
    );
}

function UntimedEventChip({
    event,
    isHighlighted,
    onToggleHighlight,
    canManageSchedules,
    returnTo,
}: {
    event: TimelineEvent;
    isHighlighted: boolean;
    onToggleHighlight: (event: TimelineEvent) => void;
    canManageSchedules: boolean;
    returnTo: string;
}) {
    const numberLabel = eventNumberLabel(event);
    const label = [
        eventTypeLabel(event.type),
        numberLabel,
        event.title,
        event.time,
        assignedUsersLabel(event),
    ]
        .filter(Boolean)
        .join(' ');
    const hasMultipleAssignedUsers = event.assigned_users.length > 1;
    const className = `inline-flex max-w-full items-center gap-1 rounded-md border text-left text-xs font-semibold transition ${eventTypeClass(event.type)} ${isHighlighted ? 'ring-2 ring-neutral-950 ring-offset-2 dark:ring-white dark:ring-offset-neutral-950' : ''}`;
    const content = (
        <>
            {numberLabel && (
                <span className="shrink-0 rounded bg-white/70 px-1 text-[10px] dark:bg-black/30">
                    {numberLabel}
                </span>
            )}
            <span className="shrink-0">{eventTypeLabel(event.type)}</span>
            <span className="truncate">{event.title}</span>
            {hasMultipleAssignedUsers && (
                <span className="shrink-0 rounded bg-white/70 px-1 text-[10px] dark:bg-black/30">
                    {event.assigned_users.length}名
                </span>
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
                    onClick={() => onToggleHighlight(event)}
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
            onClick={() => onToggleHighlight(event)}
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
                            ? '複数担当の予定を選択すると同じ予定の担当者を確認できます。編集は鉛筆アイコンから行えます。空き時間をクリックすると、その担当者と時間で工事予定を作成できます。'
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

                            return (
                                <div
                                    key={row.id ?? 'unassigned'}
                                    className="grid min-h-24"
                                    style={timelineRowStyle(bounds)}
                                >
                                    <div className="flex min-w-0 items-center px-3 py-3">
                                        <span
                                            className={`truncate text-sm font-semibold ${row.muted ? 'text-muted-foreground' : ''}`}
                                        >
                                            {row.name}
                                        </span>
                                    </div>
                                    <div className="flex min-w-0 flex-wrap content-center gap-1 px-3 py-2">
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
                                    <div className="relative min-h-24 bg-white dark:bg-neutral-950">
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
                                                className="absolute inset-0 grid"
                                                style={timelineGridStyle(
                                                    bounds,
                                                )}
                                            >
                                                {bounds.hours
                                                    .slice(0, -1)
                                                    .map((hour) =>
                                                        slotOverlapsEvents(
                                                            hour,
                                                            bounds,
                                                            timedEvents,
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
                                                                rowName={
                                                                    row.name
                                                                }
                                                                userId={row.id}
                                                                returnTo={
                                                                    returnTo
                                                                }
                                                            />
                                                        ),
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
                    <header className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-start gap-3">
                                <span className="rounded-lg bg-teal-100 p-3 text-teal-800 dark:bg-teal-950 dark:text-teal-100">
                                    <CalendarDays className="size-6" />
                                </span>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Company Schedule
                                    </p>
                                    <h1 className="text-2xl font-bold tracking-normal">
                                        予定カレンダー
                                    </h1>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-md"
                                >
                                    <Link
                                        href={overviewIndex(
                                            overviewQuery(previousMonthDate),
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
                        </div>
                    </header>

                    <div className="flex flex-col gap-4">
                        <section className="rounded-lg border border-neutral-200 bg-white p-3 md:p-4 dark:border-neutral-800 dark:bg-neutral-950">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h2 className="text-xl font-bold">
                                    {monthTitle(filters.date)}
                                </h2>
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
                                                className={`relative min-h-[5.75rem] rounded-lg p-1 transition sm:min-h-28 sm:p-2 ${dayCellClass(day, busiestScheduleCount)}`}
                                            >
                                                <Link
                                                    href={overviewIndex(
                                                        overviewQuery(day.date),
                                                    )}
                                                    className="absolute inset-0 rounded-lg focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950"
                                                    aria-current={
                                                        day.isSelected
                                                            ? 'date'
                                                            : undefined
                                                    }
                                                    aria-label={`${day.date}: 混雑度${heatLabel(heatLevel(day, busiestScheduleCount))}、工事${day.construction_count}件、業務予定${day.business_count}件、業務連絡${day.internal_notice_count}件、伝票${voucherConfirmationValue(day)}、未確認伝票${day.unconfirmed_voucher_count}件`}
                                                    preserveScroll
                                                />
                                                <span className="pointer-events-none relative flex items-center justify-between gap-1">
                                                    <span
                                                        className={`flex size-6 items-center justify-center rounded-full text-xs font-bold tabular-nums sm:size-7 sm:text-sm ${day.isToday ? 'bg-neutral-800 text-white dark:bg-white dark:text-neutral-950' : ''}`}
                                                    >
                                                        {day.isToday
                                                            ? '今'
                                                            : day.label}
                                                    </span>
                                                </span>

                                                <span className="pointer-events-none relative flex flex-col sm:mt-3">
                                                    <MetricPill
                                                        label="工"
                                                        value={
                                                            day.construction_count
                                                        }
                                                        shortLabel="工"
                                                    />
                                                    <MetricPill
                                                        label="業"
                                                        value={
                                                            day.business_count
                                                        }
                                                        shortLabel="業"
                                                    />
                                                    <MetricPill
                                                        label="連"
                                                        value={
                                                            day.internal_notice_count
                                                        }
                                                        shortLabel="連"
                                                    />
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
