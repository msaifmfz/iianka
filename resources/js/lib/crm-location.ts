import type { MapPin } from '@/types';

export type CurrentLocation = {
    lat: number;
    lng: number;
    accuracy: number;
    timestamp: number;
};

/** Great-circle distance in metres, not driving distance or travel time. */
export function straightLineDistance(
    from: { lat: number; lng: number },
    to: { lat: number; lng: number },
): number {
    const radians = Math.PI / 180;
    const halfLat = ((to.lat - from.lat) * radians) / 2;
    const halfLng = ((to.lng - from.lng) * radians) / 2;
    const haversine =
        Math.sin(halfLat) ** 2 +
        Math.cos(from.lat * radians) *
            Math.cos(to.lat * radians) *
            Math.sin(halfLng) ** 2;

    return (
        6371000 * 2 * Math.asin(Math.sqrt(Math.min(1, Math.max(0, haversine))))
    );
}

export function nearbyPlaces(location: CurrentLocation, pins: MapPin[]) {
    return pins
        .filter((pin) => pin.archived_at === null)
        .map((pin) => ({ pin, distance: straightLineDistance(location, pin) }))
        .filter(({ distance }) => distance <= 5000)
        .sort((a, b) => a.distance - b.distance || a.pin.id - b.pin.id)
        .slice(0, 3);
}

export function formatDistance(metres: number): string {
    return metres < 1000
        ? `${Math.round(metres / 10) * 10}m`
        : `${(metres / 1000).toFixed(1)}km`;
}
