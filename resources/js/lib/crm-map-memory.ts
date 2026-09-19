export const MAP_LAYERS = [
    'OpenStreetMap',
    '地理院 淡色',
    '地理院 標準',
] as const;
export type MapLayer = (typeof MAP_LAYERS)[number];
export type MapViewport = {
    lat: number;
    lng: number;
    zoom: number;
    layer: MapLayer;
};
export type MapMemory = {
    viewport: MapViewport;
    search: string;
    focusClientId: number | null;
    selectedPlaceId: number | null;
    archived: boolean;
};

export function readMapMemory(userId: number): MapMemory | null {
    try {
        const value = JSON.parse(
            sessionStorage.getItem(`crm-map:${userId}`) ?? 'null',
        ) as MapMemory | null;

        if (
            !value ||
            !value.viewport ||
            !Number.isFinite(value.viewport.lat) ||
            Math.abs(value.viewport.lat) > 90 ||
            !Number.isFinite(value.viewport.lng) ||
            Math.abs(value.viewport.lng) > 180 ||
            !Number.isFinite(value.viewport.zoom) ||
            value.viewport.zoom < 0 ||
            value.viewport.zoom > 20 ||
            !MAP_LAYERS.includes(value.viewport.layer) ||
            typeof value.search !== 'string' ||
            typeof value.archived !== 'boolean' ||
            ![value.focusClientId, value.selectedPlaceId].every(
                (id) => id === null || (Number.isSafeInteger(id) && id > 0),
            )
        ) {
            return null;
        }

        return value;
    } catch {
        return null;
    }
}

export function saveMapMemory(userId: number, value: MapMemory): void {
    try {
        sessionStorage.setItem(`crm-map:${userId}`, JSON.stringify(value));
    } catch {
        // Storage can be unavailable in private or restricted browsers.
    }
}
