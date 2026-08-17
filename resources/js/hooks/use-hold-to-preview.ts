import { useEffect, useRef } from 'react';
import type { PointerEvent } from 'react';

export const holdToPreviewDelay = 500;

/**
 * A press has to stay within this many pixels to count as a hold — past it the
 * gesture is a scroll, and cancelling lets the list move under the finger.
 */
const touchScrollTolerance = 10;

export type HoldToPreviewHandlers<T> = {
    startHold: (pointerEvent: PointerEvent<HTMLElement>, item: T) => void;
    updateHold: (pointerEvent: PointerEvent<HTMLElement>) => void;
    finishHold: () => void;
    consumeClickAfterHold: () => boolean;
};

/**
 * Hold-to-preview for any list row. Timers and refs only, so holding does not
 * re-render the row it started on.
 *
 * `consumeClickAfterHold` exists because a hold still fires the click that
 * follows it: callers check it first and bail, so holding a row previews it
 * instead of also running the row's tap action.
 */
export function useHoldToPreview<T>(
    onHold: (item: T) => void,
): HoldToPreviewHandlers<T> {
    const holdTimeoutRef = useRef<number | null>(null);
    const holdStartedAtRef = useRef<{ x: number; y: number } | null>(null);
    const didPreviewRef = useRef(false);

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

    function startHold(pointerEvent: PointerEvent<HTMLElement>, item: T) {
        if (pointerEvent.button !== 0) {
            return;
        }

        clearHoldTimeout();
        didPreviewRef.current = false;
        holdStartedAtRef.current = {
            x: pointerEvent.clientX,
            y: pointerEvent.clientY,
        };
        holdTimeoutRef.current = window.setTimeout(() => {
            didPreviewRef.current = true;
            holdTimeoutRef.current = null;
            onHold(item);
        }, holdToPreviewDelay);
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
        if (!didPreviewRef.current) {
            return false;
        }

        didPreviewRef.current = false;

        return true;
    }

    return {
        startHold,
        updateHold,
        finishHold,
        consumeClickAfterHold,
    };
}
