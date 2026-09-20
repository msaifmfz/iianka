import L from 'leaflet';
import type { PinFreshness } from '@/lib/crm';
import { clientLabelColor } from '@/lib/crm-colors';
import type { ClientPlaceKind } from '@/types';

const HEX_COLOR = /^#[0-9a-f]{6}$/i;
const FALLBACK_COLOR = '#6b7280';

function safeColor(color: string): string {
    return HEX_COLOR.test(color) ? color : FALLBACK_COLOR;
}

/**
 * Leaflet builds markers from HTML strings, so anything user-entered (the
 * client's short label) must be escaped before it goes in.
 */
function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export type PinIconOptions = {
    kind: ClientPlaceKind;
    color: string;
    label: string;
    /** The accessible name for the pin; falls back to the short label. */
    name?: string;
    freshness: PinFreshness;
    /** Another client is focused: grey this pin out. */
    dimmed: boolean;
    /** Background context, not the subject: fade without greying out. */
    muted?: boolean;
    selected: boolean;
};

/**
 * The client's color and short label on every pin, so a glance tells whose
 * pin it is. Offices are squares, every other place is a round teardrop.
 * Age never fades a pin; recent activity is indicated by a dot only.
 */
export function pinIcon({
    kind,
    color,
    label,
    name,
    freshness,
    dimmed,
    muted = false,
    selected,
}: PinIconOptions): L.DivIcon {
    const classes = [
        'crm-pin',
        kind === 'office' ? 'crm-pin--office' : 'crm-pin--place',
        muted && 'crm-pin--muted',
        dimmed && 'crm-pin--dimmed',
        selected && 'crm-pin--selected',
    ]
        .filter(Boolean)
        .join(' ');

    const recentDot =
        freshness === 'recent'
            ? '<span class="crm-pin__recent" aria-hidden="true"></span>'
            : '';

    return L.divIcon({
        className: 'crm-pin-wrapper',
        // Leaflet puts `alt` on the wrapping div, where it means nothing to a
        // screen reader, so the icon carries its own name.
        html: `<div class="${classes}" role="img" aria-label="${escapeHtml(name ?? label)}" style="--pin-color:${safeColor(color)};--pin-label-color:${clientLabelColor(color)}"><span class="crm-pin__label" aria-hidden="true">${escapeHtml(label)}</span>${recentDot}</div>`,
        iconSize: [36, 36],
        iconAnchor: kind === 'office' ? [18, 18] : [18, 36],
    });
}

/** Leaflet marker options carry the client color so clusters can show it. */
export type ClientMarkerOptions = L.MarkerOptions & { clientColor?: string };

/**
 * A cluster ring split between the colors of the clients inside it (up to
 * four), so a cluster still says whose pins it holds.
 */
export function clusterIcon(cluster: L.MarkerCluster): L.DivIcon {
    const colors = [
        ...new Set(
            cluster
                .getAllChildMarkers()
                .map((marker) =>
                    safeColor(
                        (marker.options as ClientMarkerOptions).clientColor ??
                            FALLBACK_COLOR,
                    ),
                ),
        ),
    ].slice(0, 4);

    const step = 360 / colors.length;
    const gradient = colors
        .map(
            (color, index) =>
                `${color} ${index * step}deg ${(index + 1) * step}deg`,
        )
        .join(', ');

    return L.divIcon({
        className: 'crm-pin-wrapper',
        html: `<div class="crm-cluster" style="background:conic-gradient(${gradient})"><span>${cluster.getChildCount()}</span></div>`,
        iconSize: [44, 44],
        iconAnchor: [22, 22],
    });
}
