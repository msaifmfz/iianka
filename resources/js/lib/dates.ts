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
