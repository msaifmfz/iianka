/**
 * Staff colors for CRM map pins, chips, and legends.
 */
export const STAFF_COLOR_PALETTE = [
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

/** Choose readable text for custom pin colors, including white and yellow. */
export function pinLabelColor(color: string): '#000000' | '#ffffff' {
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
