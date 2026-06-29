import { useEffect, useRef } from 'react';
import type { PointerEvent, ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ConstructionUser } from '@/types';

export type ScheduleDetailEventType =
    | 'construction'
    | 'business'
    | 'internal_notice';

export type ScheduleDetailEvent = {
    id: number;
    type: ScheduleDetailEventType;
    schedule_number: number | null;
    title: string;
    location: string | null;
    general_contractor?: string | null;
    content: string | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    assigned_users: ConstructionUser[];
};

const scheduleDetailHoldDelay = 500;
const touchScrollTolerance = 10;

export function eventTypeLabel(type: ScheduleDetailEventType) {
    return {
        construction: '工事',
        business: '業務予定',
        internal_notice: '業務連絡',
    }[type];
}

export function eventNumberLabel(event: ScheduleDetailEvent) {
    return `#${event.schedule_number ?? '?'}`;
}

export function assignedUsersLabel(event: ScheduleDetailEvent) {
    if (event.assigned_users.length === 0) {
        return '担当者未設定';
    }

    return event.assigned_users.map((user) => user.name).join('、');
}

export function scheduleDetailRows(event: ScheduleDetailEvent) {
    return [
        { label: '種別', value: eventTypeLabel(event.type) },
        { label: '番号', value: eventNumberLabel(event) },
        { label: '時間', value: event.time },
        { label: '担当者', value: assignedUsersLabel(event) },
        {
            label: event.type === 'construction' ? '現場名' : '場所',
            value: event.location,
        },
        { label: 'ゼネコン会社', value: event.general_contractor },
        { label: '内容', value: event.content },
        { label: '補足', value: event.time_note },
    ].filter(
        (row) =>
            row.value !== null && row.value !== undefined && row.value !== '',
    );
}

export function useScheduleDetailHold(
    onOpenDetail: (event: ScheduleDetailEvent) => void,
) {
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
        pointerEvent: PointerEvent<HTMLElement>,
        scheduleEvent: ScheduleDetailEvent,
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

    function updateHold(pointerEvent: PointerEvent<HTMLElement>) {
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

type ScheduleDetailDialogProps = {
    event: ScheduleDetailEvent | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    description?: string;
    children?: ReactNode;
};

export function ScheduleDetailDialog({
    event,
    open,
    onOpenChange,
    description,
    children,
}: ScheduleDetailDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                {event !== null && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{event.title}</DialogTitle>
                            {description && (
                                <DialogDescription>
                                    {description}
                                </DialogDescription>
                            )}
                        </DialogHeader>
                        <dl className="grid gap-3 text-sm">
                            {scheduleDetailRows(event).map((row) => (
                                <div
                                    key={row.label}
                                    className="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-3"
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
                        {children}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
