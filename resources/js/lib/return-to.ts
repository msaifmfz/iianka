import { router } from '@inertiajs/react';
import { consumeScheduleOverviewEditReturn } from '@/lib/schedule-overview-edit-return';

type Visitable = Parameters<typeof router.visit>[0];

/**
 * Navigate back to the `return_to` URL a page arrived with, or to the given
 * fallback route when none was provided (e.g. direct/deep links).
 */
export function visitReturnTo(returnTo: string | null, fallback: Visitable) {
    if (typeof window !== 'undefined' && returnTo !== null) {
        router.visit(returnTo);

        return;
    }

    router.visit(fallback);
}

/**
 * Back-navigation for the schedule forms: prefer real history.back() (so the
 * overview's scroll state survives, see schedule-overview-edit-return), then
 * the explicit return_to URL, then the fallback route.
 */
export function goBackToReturnTo(
    currentUrl: string,
    returnTo: string | null | undefined,
    fallback: Visitable,
) {
    if (returnTo !== null && returnTo !== undefined) {
        if (
            typeof window !== 'undefined' &&
            window.history.length > 1 &&
            consumeScheduleOverviewEditReturn(currentUrl, returnTo)
        ) {
            window.history.back();

            return;
        }

        router.visit(returnTo, { replace: true });

        return;
    }

    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(fallback);
}
