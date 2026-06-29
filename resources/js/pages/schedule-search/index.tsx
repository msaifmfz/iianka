import { Head, InfiniteScroll, router, usePage } from '@inertiajs/react';
import {
    ArrowDownAZ,
    ArrowUpAZ,
    BriefcaseBusiness,
    CalendarDays,
    Hammer,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    ScheduleDetailDialog,
    useScheduleDetailHold,
} from '@/components/schedule-detail-dialog';
import type { ScheduleDetailEvent } from '@/components/schedule-detail-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as overviewIndex } from '@/routes/schedule-overview';
import { index as searchIndex } from '@/routes/schedule-search';
import type { ConstructionUser } from '@/types';

type SearchDirection = 'asc' | 'desc';
type SearchScheduleType = 'construction' | 'business';

type Filters = {
    location: string | null;
    general_contractor: string | null;
    direction: SearchDirection;
};

type SelectedSchedule = {
    type: SearchScheduleType | null;
    id: number | null;
};

type SearchScrollAnchor = {
    key: string;
    viewportTop: number;
};

type SearchSchedule = {
    id: number;
    type: SearchScheduleType;
    scheduled_on: string;
    schedule_number: number | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    location: string;
    general_contractor: string | null;
    content: string | null;
    assigned_users: ConstructionUser[];
};

type ScrollResults<T> = {
    data: T[];
};

type Props = {
    filters: Filters;
    selected: SelectedSchedule;
    results: ScrollResults<SearchSchedule>;
};

type SearchForm = {
    location: string;
    general_contractor: string;
    direction: SearchDirection;
};

const autoSearchDelay = 300;
const scrollRestoreTolerance = 8;
const scrollRestoreDeadline = 1000;

function formatDate(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    }).format(new Date(`${date}T00:00:00`));
}

function scheduleKey(schedule: Pick<SearchSchedule, 'type' | 'id'>) {
    return `${schedule.type}-${schedule.id}`;
}

function selectedKey(selected: SelectedSchedule) {
    if (selected.type === null || selected.id === null) {
        return null;
    }

    return `${selected.type}-${selected.id}`;
}

function queryFromFilters(
    filters: Filters,
    selected?: SelectedSchedule | null,
) {
    return {
        location: filters.location || undefined,
        general_contractor: filters.general_contractor || undefined,
        direction: filters.direction,
        selected_type: selected?.type ?? undefined,
        selected_id: selected?.id ?? undefined,
    };
}

function normalizedSearchStateUrl(url: string, keepPage = true) {
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);

    params.delete('selected_type');
    params.delete('selected_id');

    if (!keepPage) {
        params.delete('page');
    }

    const normalizedQuery = params.toString();

    return `${path}${normalizedQuery ? `?${normalizedQuery}` : ''}`;
}

function normalizedSearchStateUrls(url: string) {
    return Array.from(
        new Set([
            normalizedSearchStateUrl(url),
            normalizedSearchStateUrl(url, false),
        ]),
    );
}

function searchSelectionStorageKey(url: string) {
    return `schedule-search:selected:${url}`;
}

function searchScrollStorageKey(url: string) {
    return `schedule-search:scroll:${url}`;
}

function searchAnchorStorageKey(url: string) {
    return `schedule-search:anchor:${url}`;
}

function searchReturnStorageKey(url: string) {
    return `schedule-search:return:${url}`;
}

function clearStoredSearchState(url: string) {
    normalizedSearchStateUrls(url).forEach((normalizedUrl) => {
        window.sessionStorage.removeItem(
            searchSelectionStorageKey(normalizedUrl),
        );
        window.sessionStorage.removeItem(searchScrollStorageKey(normalizedUrl));
        window.sessionStorage.removeItem(searchAnchorStorageKey(normalizedUrl));
    });
}

function storeSearchReturnState(
    url: string,
    returnTo: string,
    selected: SelectedSchedule,
    anchor: SearchScrollAnchor | null,
) {
    const selectedJson = JSON.stringify(selected);
    const scrollTop = String(window.scrollY);
    const anchorJson = anchor ? JSON.stringify(anchor) : null;

    Array.from(
        new Set([
            ...normalizedSearchStateUrls(url),
            ...normalizedSearchStateUrls(returnTo),
        ]),
    ).forEach((normalizedUrl) => {
        window.sessionStorage.setItem(
            searchSelectionStorageKey(normalizedUrl),
            selectedJson,
        );
        window.sessionStorage.setItem(
            searchScrollStorageKey(normalizedUrl),
            scrollTop,
        );

        if (anchorJson !== null) {
            window.sessionStorage.setItem(
                searchAnchorStorageKey(normalizedUrl),
                anchorJson,
            );
        }
    });
    window.sessionStorage.setItem(searchReturnStorageKey(returnTo), 'true');
}

function storedSearchScrollPosition(url: string): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = normalizedSearchStateUrls(url)
        .map((normalizedUrl) =>
            window.sessionStorage.getItem(
                searchScrollStorageKey(normalizedUrl),
            ),
        )
        .find((value) => value !== null);

    if (stored == null) {
        return null;
    }

    const scrollTop = Number(stored);

    return Number.isFinite(scrollTop) && scrollTop >= 0 ? scrollTop : null;
}

function storedSearchScrollAnchor(url: string): SearchScrollAnchor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = normalizedSearchStateUrls(url)
        .map((normalizedUrl) =>
            window.sessionStorage.getItem(
                searchAnchorStorageKey(normalizedUrl),
            ),
        )
        .find((value) => value !== null);

    if (stored == null) {
        return null;
    }

    try {
        const parsed = JSON.parse(stored) as Partial<SearchScrollAnchor>;
        const viewportTop = parsed.viewportTop;

        if (
            typeof parsed.key === 'string' &&
            typeof viewportTop === 'number' &&
            Number.isFinite(viewportTop)
        ) {
            return {
                key: parsed.key,
                viewportTop,
            };
        }
    } catch {
        return null;
    }

    return null;
}

function maxDocumentScrollTop() {
    return Math.max(
        0,
        Math.max(
            document.documentElement.scrollHeight,
            document.body.scrollHeight,
        ) - window.innerHeight,
    );
}

function currentBrowserSearchUrl(fallbackUrl: string) {
    if (typeof window === 'undefined') {
        return fallbackUrl;
    }

    return `${window.location.pathname}${window.location.search}`;
}

function selectedSearchUrl(url: string, schedule: SearchSchedule) {
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);

    params.set('selected_type', schedule.type);
    params.set('selected_id', String(schedule.id));

    return `${path}?${params.toString()}`;
}

function resultPageSearchUrl(url: string, element: HTMLElement) {
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    const resultPage = element.dataset.infiniteScrollPage;

    if (resultPage && resultPage !== '1') {
        params.set('page', resultPage);
    } else {
        params.delete('page');
    }

    const normalizedQuery = params.toString();

    return `${path}${normalizedQuery ? `?${normalizedQuery}` : ''}`;
}

function searchResultElement(key: string) {
    return document.querySelector<HTMLElement>(
        `[data-search-result-key="${key}"]`,
    );
}

function storedSelectedSchedule(url: string): SelectedSchedule | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const stored = normalizedSearchStateUrls(url)
        .map((normalizedUrl) =>
            window.sessionStorage.getItem(
                searchSelectionStorageKey(normalizedUrl),
            ),
        )
        .find((value) => value !== null);

    if (stored == null) {
        return null;
    }

    try {
        const parsed = JSON.parse(stored) as Partial<SelectedSchedule>;

        if (
            (parsed.type === 'construction' || parsed.type === 'business') &&
            typeof parsed.id === 'number'
        ) {
            return {
                type: parsed.type,
                id: parsed.id,
            };
        }
    } catch {
        return null;
    }

    return null;
}

function initialSelectedSchedule(
    selected: SelectedSchedule,
    url: string,
): SelectedSchedule {
    if (selected.type !== null && selected.id !== null) {
        return selected;
    }

    return storedSelectedSchedule(url) ?? selected;
}

function scheduleDetailEvent(schedule: SearchSchedule): ScheduleDetailEvent {
    return {
        id: schedule.id,
        type: schedule.type,
        schedule_number: schedule.schedule_number,
        title: schedule.location,
        location: schedule.location,
        general_contractor: schedule.general_contractor,
        content: schedule.content,
        time: schedule.time,
        starts_at: schedule.starts_at,
        ends_at: schedule.ends_at,
        time_note: schedule.time_note,
        assigned_users: schedule.assigned_users,
    };
}

function typeLabel(type: SearchScheduleType) {
    return type === 'construction' ? '工事' : '業務予定';
}

function typeClasses(type: SearchScheduleType) {
    return type === 'construction'
        ? 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-300/30 dark:bg-orange-500/15 dark:text-orange-100'
        : 'border-violet-200 bg-violet-50 text-violet-900 dark:border-violet-300/30 dark:bg-violet-500/15 dark:text-violet-100';
}

function filtersFromForm(form: SearchForm): Filters {
    return {
        location: form.location.trim() || null,
        general_contractor: form.general_contractor.trim() || null,
        direction: form.direction,
    };
}

function SearchResultItem({
    schedule,
    isSelected,
    onOpenDetail,
    onNavigate,
}: {
    schedule: SearchSchedule;
    isSelected: boolean;
    onOpenDetail: (event: ScheduleDetailEvent) => void;
    onNavigate: (schedule: SearchSchedule, element: HTMLElement) => void;
}) {
    const detailHold = useScheduleDetailHold(onOpenDetail);
    const TypeIcon =
        schedule.type === 'construction' ? Hammer : BriefcaseBusiness;

    return (
        <button
            type="button"
            data-search-result-key={scheduleKey(schedule)}
            data-search-selected={isSelected ? 'true' : undefined}
            onPointerDown={(event) =>
                detailHold.startHold(event, scheduleDetailEvent(schedule))
            }
            onPointerMove={detailHold.updateHold}
            onPointerUp={detailHold.finishHold}
            onPointerCancel={detailHold.finishHold}
            onPointerLeave={detailHold.finishHold}
            onClick={(event) => {
                if (detailHold.consumeClickAfterHold()) {
                    return;
                }

                onNavigate(schedule, event.currentTarget);
            }}
            className={`group w-full rounded-lg border bg-white p-4 text-left shadow-sm transition hover:border-neutral-300 hover:shadow-md focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-neutral-950/85 dark:hover:border-neutral-700 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${
                isSelected
                    ? 'border-red-500 ring-4 ring-red-500/80 ring-offset-2 ring-offset-neutral-100 dark:border-red-400 dark:ring-red-400/85 dark:ring-offset-neutral-950'
                    : 'border-neutral-200 dark:border-neutral-800'
            }`}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-1 font-semibold text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                            <CalendarDays className="size-3.5" />
                            {formatDate(schedule.scheduled_on)}
                        </span>
                        <span
                            className={`inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-semibold ${typeClasses(schedule.type)}`}
                        >
                            <TypeIcon className="size-3.5" />
                            {typeLabel(schedule.type)}
                        </span>
                    </div>

                    <div className="space-y-1">
                        <h2 className="text-lg leading-tight font-semibold text-neutral-950 dark:text-neutral-50">
                            {schedule.general_contractor || ''}
                        </h2>
                        <span className="text-md text-muted-foreground">
                            {schedule.location}
                        </span>
                    </div>
                </div>
            </div>
        </button>
    );
}

export default function ScheduleSearchIndex({
    filters,
    selected,
    results,
}: Props) {
    const { url } = usePage();
    const currentUrlRef = useRef(url);
    const restoredScrollUrlRef = useRef<string | null>(null);
    const [form, setForm] = useState<SearchForm>({
        location: filters.location ?? '',
        general_contractor: filters.general_contractor ?? '',
        direction: filters.direction,
    });
    const [detailEvent, setDetailEvent] = useState<ScheduleDetailEvent | null>(
        null,
    );
    // Hide the results until the scroll position is restored so returning from
    // the overview never flashes the top of the list before snapping into place.
    const [restoringScroll, setRestoringScroll] = useState(
        () =>
            typeof window !== 'undefined' &&
            (storedSearchScrollPosition(url) !== null ||
                storedSearchScrollAnchor(url) !== null),
    );
    // Inertia keeps this page cached across the overview round-trip, so a back
    // navigation re-shows it without remounting (no effect re-runs). Bump this
    // from the router 'navigate' event to re-arm scroll restoration on return.
    const [restoreNonce, setRestoreNonce] = useState(0);
    const highlightedSchedule = useMemo(
        () => initialSelectedSchedule(selected, url),
        [selected, url],
    );
    const activeSelectedKey = selectedKey(highlightedSchedule);

    useEffect(() => {
        currentUrlRef.current = url;
    }, [url]);

    useEffect(() => {
        const stopListening = router.on('navigate', (event) => {
            const nextUrl =
                event.detail.page.url ||
                `${window.location.pathname}${window.location.search}`;

            restoredScrollUrlRef.current = null;

            // Hide before the restore loop runs so the saved-position scroll is
            // never seen mid-adjustment (set from this subscription callback, not
            // synchronously in an effect body).
            if (
                storedSearchScrollPosition(nextUrl) !== null ||
                storedSearchScrollAnchor(nextUrl) !== null
            ) {
                setRestoringScroll(true);
            }

            setRestoreNonce((nonce) => nonce + 1);
        });

        return stopListening;
    }, []);

    function visitSearch(nextFilters: Filters) {
        if (typeof window !== 'undefined') {
            clearStoredSearchState(
                currentBrowserSearchUrl(currentUrlRef.current),
            );
        }

        router.visit(searchIndex({ query: queryFromFilters(nextFilters) }), {
            preserveState: true,
            preserveScroll: false,
            only: ['filters', 'results', 'selected'],
            replace: true,
            reset: ['results'],
        });
    }

    useEffect(() => {
        const nextFilters = filtersFromForm(form);

        // Only search once the form diverges from the filters already applied by
        // the server. Comparing values (instead of a "did mount" ref) keeps this
        // safe under React StrictMode's double-invoke and across remounts, so
        // returning from the overview never fires a spurious reset-and-reload.
        if (
            nextFilters.location === filters.location &&
            nextFilters.general_contractor === filters.general_contractor &&
            nextFilters.direction === filters.direction
        ) {
            return;
        }

        const timeout = window.setTimeout(() => {
            visitSearch(nextFilters);
        }, autoSearchDelay);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [form, filters]);

    useEffect(() => {
        if (
            typeof window === 'undefined' ||
            restoredScrollUrlRef.current === url
        ) {
            return;
        }

        const storedScrollTop = storedSearchScrollPosition(url);
        const storedAnchor = storedSearchScrollAnchor(url);

        if (storedScrollTop === null && storedAnchor === null) {
            return;
        }

        // History only restores the first page of the infinite-scroll list, so
        // the list stays hidden (see restoringScroll) while we scroll toward the
        // saved position each frame. That pulls the bottom trigger into view to
        // lazy-load deeper pages until the anchor renders; only then do we reveal
        // it, already aligned. The effect re-runs as pages arrive
        // (results.data.length), refreshing the deadline to keep progressing.
        let frame = 0;
        let settled = false;
        const deadline = performance.now() + scrollRestoreDeadline;

        const settle = () => {
            settled = true;
            restoredScrollUrlRef.current = url;
            // Reveal on the next frame (outside the synchronous effect body) so
            // the aligned position is painted in the same step it becomes visible.
            window.requestAnimationFrame(() => setRestoringScroll(false));
        };

        const restoreScroll = () => {
            if (storedAnchor !== null) {
                const anchorElement = searchResultElement(storedAnchor.key);

                if (anchorElement) {
                    const viewportOffset =
                        anchorElement.getBoundingClientRect().top -
                        storedAnchor.viewportTop;

                    if (Math.abs(viewportOffset) > scrollRestoreTolerance) {
                        window.scrollBy(0, viewportOffset);
                    }

                    settle();

                    return;
                }
            }

            if (storedScrollTop !== null) {
                const maxScrollTop = maxDocumentScrollTop();

                window.scrollTo(0, Math.min(storedScrollTop, maxScrollTop));
            }

            if (performance.now() >= deadline) {
                document
                    .querySelector('[data-search-selected="true"]')
                    ?.scrollIntoView({ block: 'center' });
                settle();

                return;
            }

            frame = window.requestAnimationFrame(restoreScroll);
        };

        // Run immediately (not deferred) so the scroll lands before any dep
        // change re-runs the effect and cancels a pending frame; each re-run
        // retries until the anchor is present.
        restoreScroll();

        return () => {
            if (!settled) {
                window.cancelAnimationFrame(frame);
            }
        };
    }, [activeSelectedKey, results.data.length, url, restoreNonce]);

    function toggleDirection() {
        setForm((current) => ({
            ...current,
            direction: current.direction === 'desc' ? 'asc' : 'desc',
        }));
    }

    function navigateToOverview(
        schedule: SearchSchedule,
        element: HTMLElement,
    ) {
        const nextSelected = {
            type: schedule.type,
            id: schedule.id,
        };
        const currentSearchUrl = resultPageSearchUrl(
            currentBrowserSearchUrl(url),
            element,
        );
        const returnTo = selectedSearchUrl(currentSearchUrl, schedule);

        if (typeof window !== 'undefined') {
            storeSearchReturnState(currentSearchUrl, returnTo, nextSelected, {
                key: scheduleKey(schedule),
                viewportTop: element.getBoundingClientRect().top,
            });
        }

        router.visit(
            overviewIndex({
                query: {
                    date: schedule.scheduled_on,
                    highlight_type: schedule.type,
                    highlight_id: schedule.id,
                    return_to: returnTo,
                },
            }),
        );
    }

    return (
        <>
            <Head title="予定検索" />
            <main className="min-h-screen bg-neutral-100 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50">
                <div
                    data-search-toolbar="true"
                    className="sticky top-0 z-50 border-b border-neutral-200 bg-white/95 px-3 py-3 shadow-sm backdrop-blur md:px-6 dark:border-neutral-800 dark:bg-neutral-950/95"
                >
                    <div className="mx-auto grid max-w-7xl gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                        <div className="grid flex-1 gap-3 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="schedule-search-location"
                                    className="text-sm font-semibold"
                                >
                                    現場名
                                </label>
                                <Input
                                    id="schedule-search-location"
                                    value={form.location}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            location: event.target.value,
                                        }))
                                    }
                                    placeholder="現場名で検索"
                                    autoComplete="off"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label
                                    htmlFor="schedule-search-general-contractor"
                                    className="text-sm font-semibold"
                                >
                                    ゼネコン会社
                                </label>
                                <Input
                                    id="schedule-search-general-contractor"
                                    value={form.general_contractor}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            general_contractor:
                                                event.target.value,
                                        }))
                                    }
                                    placeholder="ゼネコン会社で検索"
                                    autoComplete="off"
                                />
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-10 w-full lg:w-auto"
                            onClick={toggleDirection}
                            aria-label={
                                form.direction === 'desc'
                                    ? '日付の古い順へ切り替え'
                                    : '日付の新しい順へ切り替え'
                            }
                            title={
                                form.direction === 'desc'
                                    ? '日付の古い順へ切り替え'
                                    : '日付の新しい順へ切り替え'
                            }
                        >
                            {form.direction === 'desc' ? (
                                <ArrowDownAZ className="size-4" />
                            ) : (
                                <ArrowUpAZ className="size-4" />
                            )}
                            <span className="hidden sm:inline">
                                {form.direction === 'desc'
                                    ? '新しい順'
                                    : '古い順'}
                            </span>
                        </Button>
                    </div>
                </div>

                <div
                    className="mx-auto max-w-7xl px-3 py-4 md:px-6 md:py-6"
                    style={restoringScroll ? { opacity: 0 } : undefined}
                >
                    {results.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-muted-foreground dark:border-neutral-800 dark:bg-neutral-950">
                            該当する予定がありません。
                        </div>
                    ) : (
                        <InfiniteScroll data="results" className="space-y-3">
                            {results.data.map((schedule) => (
                                <SearchResultItem
                                    key={scheduleKey(schedule)}
                                    schedule={schedule}
                                    isSelected={
                                        activeSelectedKey ===
                                        scheduleKey(schedule)
                                    }
                                    onOpenDetail={setDetailEvent}
                                    onNavigate={navigateToOverview}
                                />
                            ))}
                        </InfiniteScroll>
                    )}
                </div>
            </main>

            <ScheduleDetailDialog
                event={detailEvent}
                open={detailEvent !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailEvent(null);
                    }
                }}
            />
        </>
    );
}

ScheduleSearchIndex.layout = {
    breadcrumbs: [
        {
            title: '予定検索',
            href: searchIndex(),
        },
    ],
};
