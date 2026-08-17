import { router } from '@inertiajs/react';
import { consumeScheduleOverviewEditReturn } from '@/lib/schedule-overview-edit-return';

type Visitable = Parameters<typeof router.visit>[0];

/**
 * Back-button wording for the pages a schedule can be reached from, keyed by
 * the URL prefix the server allows in `return_to` (see the
 * HandlesScheduleReturnTo trait). Anything not listed keeps the default
 * schedule wording.
 */
const returnToLabels: ReadonlyArray<[prefix: string, label: string]> = [
    ['/admin/stocks', '在庫管理へ戻る'],
    ['/reception/cases/', '受付案件へ戻る'],
];

export function returnToLabel(returnTo: string | null | undefined) {
    return returnToLabels.find(([prefix]) => returnTo?.startsWith(prefix))?.[1];
}

/**
 * Wayfinder options that carry the current `return_to` onto the next page, so
 * a schedule reached from e.g. stock management keeps its way back through
 * edits and deletes. Omitted entirely when there is nothing to carry.
 */
export function returnToQuery(returnTo: string | null | undefined) {
    return returnTo === null || returnTo === undefined
        ? undefined
        : { query: { return_to: returnTo } };
}

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
