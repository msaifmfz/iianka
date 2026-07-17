import { useEffect, useState } from 'react';
import type { RefObject } from 'react';

/**
 * Tracks (on scroll/resize, rAF-debounced) whether the floating action
 * button's center sits above the calendar area, so the button can fade while
 * overlapping it.
 */
export function useFloatingActionAboveCalendar(
    enabled: boolean,
    calendarAreaRef: RefObject<HTMLElement | null>,
    floatingActionRef: RefObject<HTMLElement | null>,
) {
    const [isAboveCalendar, setIsAboveCalendar] = useState(false);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let frameId = 0;

        const updateFloatingActionState = () => {
            const calendarArea = calendarAreaRef.current;
            const floatingAction = floatingActionRef.current;

            if (calendarArea === null || floatingAction === null) {
                return;
            }

            const calendarRect = calendarArea.getBoundingClientRect();
            const floatingActionRect = floatingAction.getBoundingClientRect();
            const floatingActionCenterY =
                floatingActionRect.top + floatingActionRect.height / 2;

            setIsAboveCalendar(floatingActionCenterY < calendarRect.top);
        };

        const requestUpdate = () => {
            window.cancelAnimationFrame(frameId);
            frameId = window.requestAnimationFrame(updateFloatingActionState);
        };

        requestUpdate();

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);

        return () => {
            window.cancelAnimationFrame(frameId);
            window.removeEventListener('scroll', requestUpdate);
            window.removeEventListener('resize', requestUpdate);
        };
    }, [enabled, calendarAreaRef, floatingActionRef]);

    return isAboveCalendar;
}
