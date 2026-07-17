/**
 * sessionStorage layer for schedule-search state (selection, scroll, anchor,
 * and the return flag checked by schedule-overview). Keys are derived from
 * URLs normalized to drop selection params (and optionally the page), so the
 * same logical search maps to one stored state.
 */

export type SearchScrollAnchor = {
    key: string;
    viewportTop: number;
};

export function normalizedSearchStateUrl(url: string, keepPage = true) {
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

export function normalizedSearchStateUrls(url: string) {
    return Array.from(
        new Set([
            normalizedSearchStateUrl(url),
            normalizedSearchStateUrl(url, false),
        ]),
    );
}

export function searchSelectionStorageKey(url: string) {
    return `schedule-search:selected:${url}`;
}

export function searchScrollStorageKey(url: string) {
    return `schedule-search:scroll:${url}`;
}

export function searchAnchorStorageKey(url: string) {
    return `schedule-search:anchor:${url}`;
}

export function searchReturnStorageKey(url: string) {
    return `schedule-search:return:${url}`;
}

export function clearStoredSearchState(url: string) {
    normalizedSearchStateUrls(url).forEach((normalizedUrl) => {
        window.sessionStorage.removeItem(
            searchSelectionStorageKey(normalizedUrl),
        );
        window.sessionStorage.removeItem(searchScrollStorageKey(normalizedUrl));
        window.sessionStorage.removeItem(searchAnchorStorageKey(normalizedUrl));
    });
}

export function storeSearchReturnState(
    url: string,
    returnTo: string,
    selected: { type: string | null; id: number | null },
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

export function storedSearchScrollPosition(url: string): number | null {
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

export function storedSearchScrollAnchor(
    url: string,
): SearchScrollAnchor | null {
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
