const overviewEditReturnStoragePrefix = 'schedule-overview:edit-return:';

function normalizedAppUrl(url: string) {
    const parsed = new URL(url, 'http://localhost');
    const query = parsed.searchParams.toString();

    return `${parsed.pathname}${query ? `?${query}` : ''}`;
}

function overviewEditReturnStorageKey(editUrl: string) {
    return `${overviewEditReturnStoragePrefix}${normalizedAppUrl(editUrl)}`;
}

export function rememberScheduleOverviewEditReturn(
    editUrl: string,
    returnTo: string,
) {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(
        overviewEditReturnStorageKey(editUrl),
        normalizedAppUrl(returnTo),
    );
}

export function consumeScheduleOverviewEditReturn(
    editUrl: string,
    returnTo: string | null | undefined,
) {
    if (typeof window === 'undefined' || returnTo == null) {
        return false;
    }

    const key = overviewEditReturnStorageKey(editUrl);

    if (window.sessionStorage.getItem(key) !== normalizedAppUrl(returnTo)) {
        return false;
    }

    window.sessionStorage.removeItem(key);

    return true;
}
