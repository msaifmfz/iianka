const businessDateFormatter = new Intl.DateTimeFormat('en-US', {
    calendar: 'gregory',
    day: '2-digit',
    month: '2-digit',
    numberingSystem: 'latn',
    timeZone: 'Asia/Tokyo',
    year: 'numeric',
});

export function businessDateString(date = new Date()) {
    const parts = businessDateFormatter.formatToParts(date);
    const values = Object.fromEntries(
        parts.map((part) => [part.type, part.value]),
    );

    return `${values.year}-${values.month}-${values.day}`;
}

/**
 * Parses a `YYYY-MM-DD` business date at UTC noon. UTC noon renders as the
 * same calendar day both through Asia/Tokyo formatters (21:00 JST) and
 * through getUTC* getters, so calendar math done with the UTC getters and
 * setters plus businessDateString() round-trips the day regardless of the
 * browser timezone.
 */
export function parseBusinessDate(date: string) {
    return new Date(`${date}T12:00:00Z`);
}
