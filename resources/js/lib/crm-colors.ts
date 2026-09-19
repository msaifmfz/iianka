/**
 * Client colors for CRM map pins. Each is dark enough for a white short
 * label on top, and adjacent entries differ in hue so the default order
 * already spreads clients apart.
 */
export const CLIENT_COLOR_PALETTE = [
    '#dc2626', // red
    '#2563eb', // blue
    '#16a34a', // green
    '#b45309', // amber
    '#9333ea', // purple
    '#0e7490', // cyan
    '#db2777', // pink
    '#4d7c0f', // lime
    '#ea580c', // orange
    '#0f766e', // teal
    '#4f46e5', // indigo
    '#a21caf', // fuchsia
] as const;

/**
 * Suggest a color for a new client, given the colors other clients use.
 *
 * Picks the least-used palette color, earliest in palette order on ties, so
 * the first 12 clients each get their own color and later ones spread evenly
 * instead of piling onto red. Custom colors outside the palette are ignored.
 * With more clients than palette entries, some colors must repeat; the pin's
 * short label and the map's focus mode tell such clients apart.
 */
export function pickClientColor(usedColors: string[]): string {
    const usage = new Map<string, number>();

    for (const color of usedColors) {
        const key = color.toLowerCase();
        usage.set(key, (usage.get(key) ?? 0) + 1);
    }

    let best: string = CLIENT_COLOR_PALETTE[0];

    for (const color of CLIENT_COLOR_PALETTE) {
        if ((usage.get(color) ?? 0) < (usage.get(best) ?? 0)) {
            best = color;
        }
    }

    return best;
}

/** Choose readable text for custom pin colors, including white and yellow. */
export function clientLabelColor(color: string): '#000000' | '#ffffff' {
    if (!/^#[0-9a-f]{6}$/i.test(color)) {
        return '#ffffff';
    }

    const channels = [1, 3, 5].map((offset) => {
        const channel = parseInt(color.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance =
        channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;

    return luminance > 0.179 ? '#000000' : '#ffffff';
}
