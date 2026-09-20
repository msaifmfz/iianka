import 'leaflet/dist/leaflet.css';
import './crm-map.css';
import type L from 'leaflet';
import { useEffect } from 'react';
import { MapContainer, Marker, useMap, useMapEvents } from 'react-leaflet';
import type { ClientPlaceKind, ClientSummary, MapPin } from '@/types';
import { BaseLayers, DEFAULT_CENTER, DEFAULT_ZOOM } from './map-view';
import { pinIcon } from './pin-icons';

type Position = { lat: number; lng: number };

type Props = {
    client: ClientSummary;
    kind: ClientPlaceKind;
    position: Position | null;
    otherPins: MapPin[];
    onChange: (position: Position) => void;
    onReady: (map: L.Map) => void;
};

function PickEvents({
    onChange,
    onReady,
}: Pick<Props, 'onChange' | 'onReady'>) {
    const map = useMapEvents({
        click: (event) => {
            const position = event.latlng.wrap();
            onChange({ lat: position.lat, lng: position.lng });
        },
    });

    useEffect(() => {
        onReady(map);
    }, [map, onReady]);

    return null;
}

function InitialView({
    position,
    otherPins,
}: Pick<Props, 'position' | 'otherPins'>) {
    const map = useMap();

    useEffect(() => {
        if (position) {
            map.setView([position.lat, position.lng], 16);
        } else if (otherPins.length > 0) {
            map.fitBounds(
                otherPins.map((pin) => [pin.lat, pin.lng] as [number, number]),
                { padding: [40, 40], maxZoom: 15 },
            );
        }
        // Frame once on mount only; later moves come from the user.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map]);

    return null;
}

/**
 * Tap the map or drag the pin to set a place's position. The client's other
 * places are drawn faded so a new pin is not dropped on top of one.
 */
export default function LocationPicker({
    client,
    kind,
    position,
    otherPins,
    onChange,
    onReady,
}: Props) {
    return (
        <MapContainer
            center={DEFAULT_CENTER}
            zoom={DEFAULT_ZOOM}
            className="size-full"
            zoomAnimation={false}
        >
            <BaseLayers />
            <InitialView position={position} otherPins={otherPins} />
            <PickEvents onChange={onChange} onReady={onReady} />
            {otherPins.map((pin) => (
                <Marker
                    key={pin.id}
                    position={[pin.lat, pin.lng]}
                    title={pin.name}
                    interactive={false}
                    icon={pinIcon({
                        kind: pin.kind,
                        color: client.color,
                        label: client.short_label,
                        name: pin.name,
                        freshness: 'normal',
                        dimmed: false,
                        muted: true,
                        selected: false,
                    })}
                />
            ))}
            {position && (
                <Marker
                    position={[position.lat, position.lng]}
                    draggable
                    zIndexOffset={1000}
                    icon={pinIcon({
                        kind,
                        color: client.color,
                        label: client.short_label,
                        freshness: 'normal',
                        dimmed: false,
                        selected: true,
                    })}
                    eventHandlers={{
                        dragend: (event) => {
                            const latLng = (event.target as L.Marker)
                                .getLatLng()
                                .wrap();
                            onChange({ lat: latLng.lat, lng: latLng.lng });
                        },
                    }}
                />
            )}
        </MapContainer>
    );
}
