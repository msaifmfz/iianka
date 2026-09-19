const dateTimeFormatter = new Intl.DateTimeFormat('ja-JP', {
    timeZone: 'Asia/Tokyo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const dateFormatter = new Intl.DateTimeFormat('ja-JP', {
    timeZone: 'Asia/Tokyo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

export function formatCrmDateTime(value: string): string {
    return dateTimeFormatter.format(new Date(value));
}

export function formatCrmDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Whole days between an ISO timestamp and now, never negative.
 */
export function daysSince(value: string, now: Date = new Date()): number {
    return Math.max(
        0,
        Math.floor((now.getTime() - new Date(value).getTime()) / DAY_MS),
    );
}

/**
 * "今日" / "3日前" style label for the last activity on a client or place.
 */
export function lastActivityLabel(value: string | null): string {
    if (value === null) {
        return '記録なし';
    }

    const days = daysSince(value);

    return days === 0 ? '今日' : `${days}日前`;
}

/** A pin with activity this recent gets the small "recent" dot. */
export const RECENT_ACTIVITY_DAYS = 7;

export type PinFreshness = 'recent' | 'normal';

/**
 * How a pin shows its last activity, since color already means "which
 * client". Age never fades a pin — a place with no history yet was only just
 * added, and fading it would bury exactly the pin someone is about to visit.
 */
export function pinFreshness(
    lastLoggedAt: string | null,
    now: Date = new Date(),
): PinFreshness {
    return lastLoggedAt !== null &&
        daysSince(lastLoggedAt, now) <= RECENT_ACTIVITY_DAYS
        ? 'recent'
        : 'normal';
}

const dateTimeLocalFormatter = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Asia/Tokyo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * An instant as the `YYYY-MM-DDTHH:mm` value of a datetime-local input, in
 * business (Tokyo) time — the server reads it back in the same zone.
 */
export function toBusinessDateTimeLocal(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return dateTimeLocalFormatter.format(date).replace(' ', 'T');
}

export function googleMapsDirectionsUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
}
