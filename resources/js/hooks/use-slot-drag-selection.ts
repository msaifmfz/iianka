import { useEffect, useRef, useState } from 'react';
import {
    contiguousSelectionHour,
    dragSelectionRange,
    isSameTimelineRow,
    pointerHourFromPosition,
    timelineHour,
    touchScrollTolerance,
    touchSelectionDelay,
} from '@/lib/timeline';
import type {
    DragSelection,
    PendingDragSelection,
    TimelineBounds,
} from '@/lib/timeline';

export type SelectedSlot = {
    date: string;
    hour: number;
    endHour: number;
    rowName: string;
    userId: number | null;
};

/**
 * Pointer-drag selection of free timeline slots. Mouse drags start
 * immediately; touch/pen drags arm after a long-press (so normal scrolling
 * still works) and a plain tap selects a single hour. Selection is clamped to
 * contiguous free hours; releasing calls `onSlotSelected` with the range.
 */
export function useSlotDragSelection({
    date,
    bounds,
    onSlotSelected,
}: {
    date: string;
    bounds: TimelineBounds;
    onSlotSelected: (slot: SelectedSlot) => void;
}) {
    const [dragSelection, setDragSelection] = useState<DragSelection | null>(
        null,
    );
    const dragSelectionRef = useRef<DragSelection | null>(null);
    const pendingDragSelectionRef = useRef<PendingDragSelection | null>(null);

    /*
     * The ref mirrors the state so pointer handlers can read the latest
     * selection synchronously; syncing here keeps state updaters pure.
     */
    useEffect(() => {
        dragSelectionRef.current = dragSelection;
    }, [dragSelection]);

    useEffect(() => {
        return () => {
            const pendingSelection = pendingDragSelectionRef.current;

            if (pendingSelection !== null) {
                window.clearTimeout(pendingSelection.timeoutId);
            }
        };
    }, []);

    const isDraggingSlotSelection = dragSelection !== null;

    useEffect(() => {
        if (!isDraggingSlotSelection) {
            return;
        }

        const preventTouchScroll = (event: TouchEvent) => {
            event.preventDefault();
        };

        window.addEventListener('touchmove', preventTouchScroll, {
            passive: false,
        });

        return () => {
            window.removeEventListener('touchmove', preventTouchScroll);
        };
    }, [isDraggingSlotSelection]);

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
                date,
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
            date,
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
            !isSameTimelineRow(activeSelection, date, rowName, userId)
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
                !isSameTimelineRow(selection, date, rowName, userId)
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

            return {
                ...selection,
                currentHour,
            };
        });
    }

    function finishSlotSelection(event: React.PointerEvent<HTMLDivElement>) {
        const pendingSelection = pendingDragSelectionRef.current;

        if (
            pendingSelection !== null &&
            pendingSelection.pointerId === event.pointerId
        ) {
            clearPendingDragSelection();
            onSlotSelected({
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
        onSlotSelected({
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

    return {
        dragSelection,
        startSlotSelection,
        updateSlotSelection,
        finishSlotSelection,
        cancelSlotSelection,
    };
}
