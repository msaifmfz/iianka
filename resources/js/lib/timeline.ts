import type { CSSProperties } from 'react';
import type { ConstructionUser } from '@/types';

export type TimelineEventType = 'construction' | 'business' | 'internal_notice';

export type TimelineEvent = {
    id: number;
    type: TimelineEventType;
    schedule_number: number | null;
    title: string;
    location: string | null;
    content: string | null;
    carry_out_note?: string | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    assigned_users: ConstructionUser[];
};

export type DragSelection = {
    date: string;
    rowName: string;
    userId: number | null;
    anchorHour: number;
    currentHour: number;
    pointerId: number;
};

export type PendingDragSelection = {
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

export const timelineDefaultStart = 8 * 60;
export const timelineDefaultEnd = 20 * 60;
export const timelineHour = 60;
export const timelineSlotWidth = 47;
export const timelineAssigneeColumnWidth = '7rem';
export const timelineUntimedColumnWidth = '9rem';
export const timelineRowMinHeight = 48;
export const timelineEventLaneHeight = 44;
export const timelineEventBlockHeight = 36;
export const timelineEventBlockInset = 4;
export const touchSelectionDelay = 260;
export const touchScrollTolerance = 10;

export function timeToMinutes(time: string | null) {
    if (time === null) {
        return null;
    }

    const [hours, minutes] = time.slice(0, 5).split(':').map(Number);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    return hours * 60 + minutes;
}

export type TimelineBounds = ReturnType<typeof timelineBounds>;

export function timelineBounds(events: TimelineEvent[]) {
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

export function hourLabel(minutes: number) {
    return `${String(Math.floor(minutes / timelineHour)).padStart(2, '0')}:00`;
}

export function eventKey(event: TimelineEvent) {
    return `${event.type}-${event.id}`;
}

export function eventRange(
    event: TimelineEvent,
    bounds: ReturnType<typeof timelineBounds>,
) {
    const startsAt = timeToMinutes(event.starts_at) ?? bounds.startsAt;
    const endsAt = timeToMinutes(event.ends_at) ?? bounds.endsAt;

    return {
        startsAt,
        endsAt: Math.max(endsAt, startsAt + 15),
    };
}

export function layoutTimelineEvents(
    events: TimelineEvent[],
    bounds: ReturnType<typeof timelineBounds>,
) {
    const laneEnds: number[] = [];

    return [...events]
        .sort((firstEvent, secondEvent) => {
            const firstRange = eventRange(firstEvent, bounds);
            const secondRange = eventRange(secondEvent, bounds);

            if (firstRange.startsAt !== secondRange.startsAt) {
                return firstRange.startsAt - secondRange.startsAt;
            }

            if (firstRange.endsAt !== secondRange.endsAt) {
                return firstRange.endsAt - secondRange.endsAt;
            }

            return eventKey(firstEvent).localeCompare(eventKey(secondEvent));
        })
        .map((event) => {
            const range = eventRange(event, bounds);
            const laneIndex = laneEnds.findIndex(
                (laneEnd) => laneEnd <= range.startsAt,
            );
            const lane = laneIndex === -1 ? laneEnds.length : laneIndex;

            laneEnds[lane] = range.endsAt;

            return {
                event,
                lane,
            };
        });
}

export function timelineRowHeight(laneCount: number) {
    if (laneCount <= 0) {
        return timelineRowMinHeight;
    }

    return Math.max(
        timelineRowMinHeight,
        timelineEventBlockInset * 2 + laneCount * timelineEventLaneHeight,
    );
}

export function minuteInputValue(minutes: number) {
    const clampedMinutes = Math.min(Math.max(minutes, 0), 23 * 60 + 59);

    return `${String(Math.floor(clampedMinutes / timelineHour)).padStart(2, '0')}:${String(clampedMinutes % timelineHour).padStart(2, '0')}`;
}

export function timelineGridStyle(
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    return {
        gridTemplateColumns: `repeat(${bounds.slotCount}, minmax(0, 1fr))`,
    };
}

export function timelineRowStyle(
    bounds: ReturnType<typeof timelineBounds>,
): CSSProperties {
    return {
        gridTemplateColumns: `${timelineAssigneeColumnWidth} ${timelineUntimedColumnWidth} ${bounds.width}px`,
    };
}

export function eventPositionStyle(
    event: TimelineEvent,
    bounds: ReturnType<typeof timelineBounds>,
    lane = 0,
): CSSProperties {
    const { startsAt, endsAt } = eventRange(event, bounds);
    const left = ((startsAt - bounds.startsAt) / bounds.duration) * 100;
    const width = ((endsAt - startsAt) / bounds.duration) * 100;

    return {
        left: `${Math.max(left, 0)}%`,
        width: `${Math.min(Math.max(width, 2), 100 - Math.max(left, 0))}%`,
        top: `${timelineEventBlockInset + lane * timelineEventLaneHeight}px`,
        height: `${timelineEventBlockHeight}px`,
    };
}

export function selectionPositionStyle(
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

export function eventsForUser(events: TimelineEvent[], userId: number | null) {
    return events.filter((event) => {
        if (userId === null) {
            return event.assigned_users.length === 0;
        }

        return event.assigned_users.some((user) => user.id === userId);
    });
}

export function slotOverlapsEvents(
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

export function dragSelectionRange(selection: DragSelection) {
    const startsAt = Math.min(selection.anchorHour, selection.currentHour);
    const endsAt =
        Math.max(selection.anchorHour, selection.currentHour) + timelineHour;

    return { startsAt, endsAt };
}

export function isSameTimelineRow(
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

export function slotIsSelected(
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

export function pointerHourFromPosition(
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

export function contiguousSelectionHour(
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
