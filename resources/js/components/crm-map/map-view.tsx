import 'leaflet/dist/leaflet.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.css';
import './crm-map.css';
import type L from 'leaflet';
import { useEffect, useRef } from 'react';
import {
    Circle,
    CircleMarker,
    LayersControl,
    MapContainer,
    Marker,
    Pane,
    TileLayer,
    Tooltip,
    useMap,
    ZoomControl,
    useMapEvents,
} from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatCrmDateTime, pinFreshness } from '@/lib/crm';
import { MAP_LAYERS } from '@/lib/crm-map-memory';
import type { MapLayer, MapViewport } from '@/lib/crm-map-memory';
import {
    displayedActivity,
    sortedStaff,
    staffColor,
    staffName,
} from '@/lib/crm-staff';
import type { ClientSummary, CrmMapPin } from '@/types';
import { clusterIcon, pinIcon } from './pin-icons';

/** Osaka: the fallback view before any place exists. */
export const DEFAULT_CENTER: [number, number] = [34.6937, 135.5023];
export const DEFAULT_ZOOM = 9;

const GSI_ATTRIBUTION =
    '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noreferrer">地理院タイル</a>';
const OSM_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a>';

export function BaseLayers({
    position = 'topright',
    initialLayer = 'OpenStreetMap',
}: {
    position?: L.ControlPosition;
    initialLayer?: MapLayer;
}) {
    return (
        <LayersControl position={position}>
            <LayersControl.BaseLayer
                checked={initialLayer === 'OpenStreetMap'}
                name="OpenStreetMap"
            >
                <TileLayer
                    url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution={OSM_ATTRIBUTION}
                    maxZoom={19}
                />
            </LayersControl.BaseLayer>
            <LayersControl.BaseLayer
                checked={initialLayer === '地理院 淡色'}
                name="地理院 淡色"
            >
                <TileLayer
                    url="https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png"
                    attribution={GSI_ATTRIBUTION}
                    maxNativeZoom={18}
                    maxZoom={20}
                />
            </LayersControl.BaseLayer>
            <LayersControl.BaseLayer
                checked={initialLayer === '地理院 標準'}
                name="地理院 標準"
            >
                <TileLayer
                    url="https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png"
                    attribution={GSI_ATTRIBUTION}
                    maxNativeZoom={18}
                    maxZoom={20}
                />
            </LayersControl.BaseLayer>
        </LayersControl>
    );
}

type Props = {
    pins: CrmMapPin[];
    staffId: number | null;
    clientsById: Map<number, ClientSummary>;
    focusClientId: number | null;
    selectedPlaceId: number | null;
    onSelectPlace: (placeId: number) => void;
    onVisiblePlacesChange: (placeIds: number[]) => void;
    /** Long-press (or right-click) on empty map; only for content managers. */
    onPickLocation?: (lat: number, lng: number) => void;
    onReady: (map: L.Map) => void;
    initialViewport?: MapViewport | null;
    onViewportChange: (viewport: MapViewport) => void;
    initialClientId: number | null;
    currentLocation: { lat: number; lng: number; accuracy: number } | null;
};

/**
 * Frames the pins on first load: the selected place if the URL opened one,
 * otherwise every pin. Runs once, so later reloads never yank the view away
 * from where the user has panned.
 */
function InitialView({
    pins,
    selectedPlaceId,
    initialViewport,
    initialClientId,
}: {
    pins: CrmMapPin[];
    selectedPlaceId: number | null;
    initialViewport?: MapViewport | null;
    initialClientId: number | null;
}) {
    const map = useMap();
    const hasFramed = useRef(false);

    useEffect(() => {
        if (hasFramed.current) {
            return;
        }

        hasFramed.current = true;

        if (initialViewport) {
            map.setView(
                [initialViewport.lat, initialViewport.lng],
                initialViewport.zoom,
                { animate: false },
            );

            return;
        }

        const framedPins =
            initialClientId === null
                ? pins
                : pins.filter((pin) => pin.client_id === initialClientId);
        const selected = pins.find((pin) => pin.id === selectedPlaceId);

        if (selected) {
            map.setView([selected.lat, selected.lng], 15);
        } else if (framedPins.length === 1) {
            map.setView([framedPins[0].lat, framedPins[0].lng], 15);
        } else if (framedPins.length > 1) {
            const size = map.getSize();
            const hasLegend = window.matchMedia('(min-width: 640px)').matches;

            map.fitBounds(
                framedPins.map((pin) => [pin.lat, pin.lng] as [number, number]),
                {
                    paddingTopLeft: hasLegend
                        ? [Math.min(380, size.x * 0.7), 40]
                        : [24, 180],
                    paddingBottomRight: [40, 40],
                    maxZoom: 15,
                    animate: false,
                },
            );
        }
    }, [map, pins, selectedPlaceId, initialViewport, initialClientId]);

    return null;
}

function MapEvents({
    pins,
    onVisiblePlacesChange,
    onPickLocation,
    onReady,
    initialViewport,
    onViewportChange,
}: Pick<
    Props,
    | 'pins'
    | 'onVisiblePlacesChange'
    | 'onPickLocation'
    | 'onReady'
    | 'initialViewport'
    | 'onViewportChange'
>) {
    const layerRef = useRef<MapLayer>(
        initialViewport?.layer ?? 'OpenStreetMap',
    );
    const map = useMapEvents({
        moveend: () => {
            reportVisiblePlaces();
            reportViewport();
        },
        baselayerchange: (event) => {
            if (MAP_LAYERS.includes(event.name as MapLayer)) {
                layerRef.current = event.name as MapLayer;
                reportViewport();
            }
        },
        contextmenu: (event) => {
            const position = event.latlng.wrap();
            onPickLocation?.(position.lat, position.lng);
        },
    });

    function reportViewport() {
        const center = map.getCenter().wrap();
        onViewportChange({
            lat: center.lat,
            lng: center.lng,
            zoom: map.getZoom(),
            layer: layerRef.current,
        });
    }

    function reportVisiblePlaces() {
        const bounds = map.getBounds();
        const placeIds: number[] = [];

        for (const pin of pins) {
            if (bounds.contains([pin.lat, pin.lng])) {
                placeIds.push(pin.id);
            }
        }

        onVisiblePlacesChange(placeIds);
    }

    useEffect(() => {
        onReady(map);
        reportVisiblePlaces();
        reportViewport();
        // Re-report when the pin set changes (e.g. archived toggled).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, pins]);

    return null;
}

function SelectedView({
    pins,
    selectedPlaceId,
    initialViewport,
}: Pick<Props, 'pins' | 'selectedPlaceId' | 'initialViewport'>) {
    const map = useMap();
    const restoredSelection = useRef(
        initialViewport ? selectedPlaceId : undefined,
    );
    const isMobile = useIsMobile();
    const centeredPlaceId = useRef<number | null>(null);

    useEffect(() => {
        const pin = pins.find((item) => item.id === selectedPlaceId);

        if (!pin) {
            centeredPlaceId.current = null;

            return;
        }

        function frameSelection() {
            if (!pin) {
                return;
            }

            if (centeredPlaceId.current !== selectedPlaceId) {
                map.setView([pin.lat, pin.lng], Math.max(map.getZoom(), 15), {
                    animate: false,
                });
                centeredPlaceId.current = selectedPlaceId;
            }

            const size = map.getSize();
            map.panInside([pin.lat, pin.lng], {
                paddingTopLeft: [
                    isMobile ? 24 : Math.min(380, size.x * 0.35),
                    isMobile ? 280 : 24,
                ],
                paddingBottomRight: [
                    isMobile ? 230 : Math.min(650, size.x * 0.6),
                    isMobile ? size.y * 0.55 + 24 : 24,
                ],
                animate: false,
            });
        }

        if (restoredSelection.current !== selectedPlaceId) {
            restoredSelection.current = undefined;
            frameSelection();
        } else {
            centeredPlaceId.current = selectedPlaceId;
        }

        function onResize() {
            restoredSelection.current = undefined;
            frameSelection();
        }

        map.on('resize', onResize);

        return () => {
            map.off('resize', onResize);
        };
    }, [map, pins, selectedPlaceId, isMobile]);

    return null;
}

export default function MapView({
    pins,
    staffId,
    clientsById,
    focusClientId,
    selectedPlaceId,
    onSelectPlace,
    onVisiblePlacesChange,
    onPickLocation,
    onReady,
    initialViewport,
    onViewportChange,
    initialClientId,
    currentLocation,
}: Props) {
    return (
        <MapContainer
            center={DEFAULT_CENTER}
            zoom={DEFAULT_ZOOM}
            className="size-full"
            zoomControl={false}
            // Leaflet 1.9's zoom-transition timer can fire after Inertia removes the map.
            zoomAnimation={false}
        >
            {/* Bottom-left keeps controls clear of the desktop panel. */}
            <BaseLayers
                position="bottomleft"
                initialLayer={initialViewport?.layer}
            />
            <ZoomControl position="bottomleft" />
            <InitialView
                pins={pins}
                selectedPlaceId={selectedPlaceId}
                initialViewport={initialViewport}
                initialClientId={initialClientId}
            />
            {currentLocation && (
                <>
                    <Circle
                        center={[currentLocation.lat, currentLocation.lng]}
                        radius={currentLocation.accuracy}
                        interactive={false}
                        pathOptions={{
                            color: '#2563eb',
                            weight: 1,
                            fillOpacity: 0.1,
                            className: 'crm-location-accuracy',
                        }}
                    />
                    <Pane
                        name="current-location"
                        style={{ zIndex: 625, pointerEvents: 'none' }}
                    >
                        <CircleMarker
                            center={[currentLocation.lat, currentLocation.lng]}
                            interactive={false}
                            radius={7}
                            pathOptions={{
                                color: '#ffffff',
                                weight: 3,
                                fillColor: '#2563eb',
                                fillOpacity: 1,
                                className: 'crm-current-location',
                            }}
                        >
                            <Tooltip permanent direction="top" offset={[0, -8]}>
                                現在地（取得時点）
                            </Tooltip>
                        </CircleMarker>
                    </Pane>
                </>
            )}
            <SelectedView
                pins={pins}
                selectedPlaceId={selectedPlaceId}
                initialViewport={initialViewport}
            />
            <MapEvents
                pins={pins}
                onVisiblePlacesChange={onVisiblePlacesChange}
                onPickLocation={onPickLocation}
                onReady={onReady}
                initialViewport={initialViewport}
                onViewportChange={onViewportChange}
            />
            <MarkerClusterGroup
                key={staffId ?? 'all'}
                iconCreateFunction={clusterIcon}
                chunkedLoading
                maxClusterRadius={40}
                spiderfyOnMaxZoom
                showCoverageOnHover={false}
            >
                {pins.map((pin) => {
                    const client = clientsById.get(pin.client_id);

                    if (!client) {
                        return null;
                    }

                    const isSelected = pin.id === selectedPlaceId;
                    const activity = displayedActivity(pin, staffId);
                    const authors = sortedStaff(pin.staff_activities);
                    const staff =
                        authors.length > 0
                            ? authors.map((author) => ({
                                  name: staffName(author),
                                  color: staffColor(author.user?.id ?? null),
                                  highlighted:
                                      staffId !== null &&
                                      author.user?.id === staffId,
                              }))
                            : [
                                  {
                                      name: staffName(null),
                                      color: staffColor(null),
                                      highlighted: false,
                                  },
                              ];
                    const label = staff.map((person) => person.name).join('・');
                    const color =
                        staff.length === 1
                            ? staff[0].color
                            : staffColor(staffId);
                    const accessibleName = `${label} ・ ${client.name} ${pin.name}${activity ? ` ・ ${formatCrmDateTime(activity.occurred_at)}` : ''}`;
                    // Leaflet passes unknown props through as marker
                    // options; the cluster icon reads the color back.
                    const clusterOptions: object = {
                        staffColors: staff.map((person) => person.color),
                    };

                    return (
                        <Marker
                            key={`${pin.id}:${label}:${activity?.occurred_at ?? ''}`}
                            position={[pin.lat, pin.lng]}
                            title={accessibleName}
                            alt={accessibleName}
                            zIndexOffset={isSelected ? 1000 : 0}
                            icon={pinIcon({
                                kind: pin.kind,
                                color,
                                label,
                                staff,
                                name: accessibleName,
                                freshness: pinFreshness(
                                    activity?.occurred_at ?? null,
                                ),
                                dimmed:
                                    focusClientId !== null &&
                                    focusClientId !== pin.client_id,
                                selected: isSelected,
                            })}
                            eventHandlers={{
                                click: () => onSelectPlace(pin.id),
                                keydown: (event) => {
                                    if (
                                        event.originalEvent.key === 'Enter' ||
                                        event.originalEvent.key === ' '
                                    ) {
                                        event.originalEvent.preventDefault();
                                        onSelectPlace(pin.id);
                                    }
                                },
                            }}
                            {...clusterOptions}
                        >
                            <Tooltip direction="top" offset={[0, -24]}>
                                {authors.length === 0 && (
                                    <span className="block">記録なし</span>
                                )}
                                {authors.map((author) => (
                                    <span
                                        key={author.user?.id ?? 'unknown'}
                                        className="block"
                                    >
                                        {staffName(author)} ・ 最終記録:{' '}
                                        {formatCrmDateTime(author.occurred_at)}
                                    </span>
                                ))}
                                <span className="block">{client.name}</span>
                                <span className="block">{pin.name}</span>
                            </Tooltip>
                        </Marker>
                    );
                })}
            </MarkerClusterGroup>
        </MapContainer>
    );
}
