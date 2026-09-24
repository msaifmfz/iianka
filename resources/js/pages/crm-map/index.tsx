import { Head, Link, router, usePage } from '@inertiajs/react';
import type L from 'leaflet';
import { ChevronDown, Crosshair, List, Plus, Search, X } from 'lucide-react';
import { lazy, useEffect, useMemo, useRef, useState } from 'react';
import { index as clientIndex } from '@/actions/App/Http/Controllers/ClientController';
import { create as clientCreate } from '@/actions/App/Http/Controllers/ClientController';
import { create as placeCreate } from '@/actions/App/Http/Controllers/ClientPlaceController';
import crmMap from '@/actions/App/Http/Controllers/CrmMapController';
import { ActionHelp, HelpButton } from '@/components/crm-map/action-help';
import ClientOnly from '@/components/crm-map/client-only';
import PlacePanel from '@/components/crm-map/place-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { formatDistance, nearbyPlaces } from '@/lib/crm-location';
import type { CurrentLocation } from '@/lib/crm-location';
import { readMapMemory, saveMapMemory } from '@/lib/crm-map-memory';
import type { MapViewport } from '@/lib/crm-map-memory';
import { displayedActivity, staffColor } from '@/lib/crm-staff';
import { cn } from '@/lib/utils';
import type {
    ClientSummary,
    CrmAttachmentLimits,
    CrmOption,
    CrmMapPin,
    SelectedPlace,
} from '@/types';

const MapView = lazy(() => import('@/components/crm-map/map-view'));

type Props = {
    clients: ClientSummary[];
    places: CrmMapPin[];
    filters: { archived: boolean };
    selectedPlace: SelectedPlace | null;
    canManage: boolean;
    logTypes: CrmOption[];
    reactions: CrmOption[];
    attachmentLimits: CrmAttachmentLimits;
};

const MAP_ONLY_PROPS = ['selectedPlace', 'places', 'filters'];

function mapQuery(placeId: number | null, archived: boolean, allLogs = false) {
    return {
        ...(placeId !== null ? { place: placeId } : {}),
        ...(archived ? { archived: 1 } : {}),
        ...(allLogs ? { logs: 'all' } : {}),
    };
}

export default function CrmMapPage(props: Props) {
    return (
        <>
            <Head title="顧客マップ" />
            <ClientOnly>
                <CrmMapContent {...props} />
            </ClientOnly>
        </>
    );
}

function CrmMapContent({
    clients,
    places,
    filters,
    selectedPlace,
    canManage,
    logTypes,
    reactions,
    attachmentLimits,
}: Props) {
    const { auth } = usePage().props;
    const pageUrl = usePage().url;
    const [initialClientId] = useState(() => {
        const value = Number(
            new URLSearchParams(pageUrl.split('?')[1]).get('client'),
        );

        return clients.some((client) => client.id === value) ? value : null;
    });
    const [saved] = useState(() =>
        new URLSearchParams(pageUrl.split('?')[1]).get('restore') === '1'
            ? readMapMemory(auth.user.id)
            : null,
    );
    const initialViewport = saved?.viewport ?? null;
    const [viewport, setViewport] = useState<MapViewport | null>(
        initialViewport,
    );
    const [pendingPlaceId, setPendingPlaceId] = useState<
        number | null | undefined
    >();
    const selectedPlaceId =
        pendingPlaceId === undefined
            ? (selectedPlace?.place.id ?? null)
            : pendingPlaceId;
    const [selectionError, setSelectionError] = useState<string | null>(null);
    const [isChangingArchive, setIsChangingArchive] = useState(false);
    const [isLoadingOlderLogs, setIsLoadingOlderLogs] = useState(false);
    const [focusClientId, setFocusClientId] = useState<number | null>(
        initialClientId ??
            (clients.some((client) => client.id === saved?.focusClientId)
                ? (saved?.focusClientId ?? null)
                : null),
    );
    const [staffId, setStaffId] = useState<number | null>(() => {
        const id = saved?.staffId ?? null;
        const selectedPin = places.find((pin) => pin.id === selectedPlaceId);

        if (
            id === null ||
            (selectedPin && displayedActivity(selectedPin, id) === null)
        ) {
            return null;
        }

        return id === auth.user.id ||
            places.some((pin) => displayedActivity(pin, id) !== null)
            ? id
            : null;
    });
    const [previousPlaces, setPreviousPlaces] = useState(places);
    const [visiblePlaceIds, setVisiblePlaceIds] = useState<number[]>([]);
    const staff = useMemo(() => {
        const users = new Map<number, { id: number; name: string }>();

        for (const pin of places) {
            for (const activity of pin.staff_activities) {
                if (activity.user) {
                    users.set(activity.user.id, activity.user);
                }
            }
        }

        return [...users.values()].sort((a, b) =>
            a.name.localeCompare(b.name, 'ja'),
        );
    }, [places]);

    if (previousPlaces !== places) {
        setPreviousPlaces(places);
        const selectedPin = places.find((pin) => pin.id === selectedPlaceId);

        if (
            staffId !== null &&
            ((selectedPlaceId !== null &&
                (!selectedPin ||
                    displayedActivity(selectedPin, staffId) === null)) ||
                (staffId !== auth.user.id &&
                    !staff.some((user) => user.id === staffId)))
        ) {
            setStaffId(null);
        }
    }

    const filteredPlaces = useMemo(
        () =>
            staffId === null
                ? places
                : places.filter(
                      (pin) => displayedActivity(pin, staffId) !== null,
                  ),
        [places, staffId],
    );
    const [search, setSearch] = useState(saved?.search ?? '');
    const [clientChoiceSearch, setClientChoiceSearch] = useState('');
    const [pickedLocation, setPickedLocation] = useState<{
        lat: number;
        lng: number;
    } | null>(null);
    const [locateMessage, setLocateMessage] = useState<string | null>(null);
    const [currentLocation, setCurrentLocation] =
        useState<CurrentLocation | null>(null);
    const [isLocating, setIsLocating] = useState(false);
    const [nearbyExpanded, setNearbyExpanded] = useState(false);
    const locationRequestRef = useRef(0);
    const mapRef = useRef<L.Map | null>(null);
    const selectionRequestRef = useRef(0);
    const nearby = currentLocation ? nearbyPlaces(currentLocation, places) : [];
    // Derived from the URL rather than held in state, so it cannot drift out
    // of step with the history the server actually sent.
    const isShowingAllLogs =
        new URLSearchParams(pageUrl.split('?')[1]).get('logs') === 'all';

    useEffect(
        () => () => {
            locationRequestRef.current++;
        },
        [],
    );

    useEffect(() => {
        if (viewport) {
            saveMapMemory(auth.user.id, {
                viewport,
                search,
                focusClientId,
                staffId,
                selectedPlaceId: selectedPlace?.place.id ?? null,
                archived: filters.archived,
            });
        }
    }, [
        auth.user.id,
        viewport,
        search,
        focusClientId,
        staffId,
        selectedPlace,
        filters.archived,
    ]);

    const clientsById = new Map(clients.map((client) => [client.id, client]));
    const focusClient =
        focusClientId === null ? null : clientsById.get(focusClientId);
    const searchTerm = search.trim().toLocaleLowerCase();
    const searchResults =
        searchTerm === ''
            ? []
            : clients
                  .filter(
                      (client) =>
                          client.name
                              .toLocaleLowerCase()
                              .includes(searchTerm) &&
                          (staffId === null ||
                              filteredPlaces.some(
                                  (pin) => pin.client_id === client.id,
                              )),
                  )
                  .slice(0, 8);
    const clientChoiceTerm = clientChoiceSearch.trim().toLocaleLowerCase();
    const clientChoices = clients.filter((client) =>
        client.name.toLocaleLowerCase().includes(clientChoiceTerm),
    );
    const visiblePlaces = new Set(visiblePlaceIds);
    const visibleStaffIds = new Set(
        filteredPlaces
            .filter((pin) => visiblePlaces.has(pin.id))
            .flatMap((pin) =>
                pin.staff_activities.map((activity) => activity.user?.id),
            ),
    );
    const legendStaff = staff.filter((user) => visibleStaffIds.has(user.id));

    /**
     * Opening a pin swaps only the `selectedPlace` prop; the URL carries
     * `?place=` so the panel survives a reload and can be shared.
     */
    function visitMap(
        placeId: number | null,
        archived = filters.archived,
        allLogs = false,
    ) {
        const targetPin = places.find((pin) => pin.id === placeId);

        if (
            placeId !== null &&
            staffId !== null &&
            (!targetPin || displayedActivity(targetPin, staffId) === null)
        ) {
            setStaffId(null);
        }

        const requestId = ++selectionRequestRef.current;
        setPendingPlaceId(placeId);
        setSelectionError(null);
        setIsChangingArchive(archived !== filters.archived);
        router.get(
            crmMap.url({ query: mapQuery(placeId, archived, allLogs) }),
            {},
            {
                only: MAP_ONLY_PROPS,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: (page) => {
                    if (placeId !== null && !page.props.selectedPlace) {
                        setSelectionError(
                            'この地点は削除されたか、表示できなくなりました。',
                        );
                    }
                },
                onFinish: () => {
                    if (requestId === selectionRequestRef.current) {
                        setPendingPlaceId(undefined);
                        setIsChangingArchive(false);
                        setIsLoadingOlderLogs(false);
                    }
                },
            },
        );
    }

    function selectPlace(placeId: number) {
        if (
            focusClientId !== null &&
            places.find((pin) => pin.id === placeId)?.client_id !==
                focusClientId
        ) {
            setFocusClientId(null);
        }

        visitMap(placeId);
    }

    function closePanel() {
        visitMap(null);
    }

    function focusOn(client: ClientSummary) {
        setFocusClientId(client.id);
        setSearch('');

        const clientPins = filteredPlaces.filter(
            (pin) => pin.client_id === client.id,
        );

        if (clientPins.length > 0) {
            mapRef.current?.fitBounds(
                clientPins.map((pin) => [pin.lat, pin.lng] as [number, number]),
                { padding: [60, 60], maxZoom: 15 },
            );
        }
    }

    function chooseStaff(id: number | null) {
        setStaffId(id);
        const matchingPins = places.filter(
            (pin) =>
                (id === null || displayedActivity(pin, id) !== null) &&
                (focusClientId === null || pin.client_id === focusClientId),
        );

        if (matchingPins.some((pin) => pin.id === selectedPlaceId)) {
            return;
        }

        if (
            selectedPlaceId !== null &&
            !matchingPins.some((pin) => pin.id === selectedPlaceId)
        ) {
            visitMap(null);
        }

        if (matchingPins.length > 0) {
            mapRef.current?.fitBounds(
                matchingPins.map(
                    (pin) => [pin.lat, pin.lng] as [number, number],
                ),
                {
                    paddingTopLeft: [40, 210],
                    paddingBottomRight: [60, 60],
                    maxZoom: 15,
                    animate: false,
                },
            );
        }
    }

    function locateMe() {
        if (isLocating) {
            return;
        }

        if (!('geolocation' in navigator)) {
            setLocateMessage('この端末では現在地を取得できません。');

            return;
        }

        const requestId = ++locationRequestRef.current;
        setIsLocating(true);
        setLocateMessage('現在地を取得中...');
        navigator.geolocation.getCurrentPosition(
            (result) => {
                if (requestId !== locationRequestRef.current) {
                    return;
                }

                setIsLocating(false);
                setLocateMessage(null);
                setCurrentLocation({
                    lat: result.coords.latitude,
                    lng: result.coords.longitude,
                    accuracy: result.coords.accuracy,
                    timestamp: result.timestamp,
                });
                mapRef.current?.setView(
                    [result.coords.latitude, result.coords.longitude],
                    14,
                    { animate: false },
                );
            },
            (error) => {
                if (requestId !== locationRequestRef.current) {
                    return;
                }

                setIsLocating(false);
                setLocateMessage(
                    error.code === 1
                        ? '位置情報が許可されていません。ブラウザの設定で許可してから、再度お試しください。'
                        : '現在地を取得できませんでした。電波状況を確認して、再度お試しください。',
                );
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    }

    const panelSelection =
        selectedPlace !== null && selectedPlace.place.id === selectedPlaceId
            ? selectedPlace
            : null;

    return (
        <>
            <div className="relative isolate h-[calc(100svh-4rem)] w-full overflow-hidden md:h-[calc(100svh-5rem)]">
                <ClientOnly>
                    <MapView
                        currentLocation={currentLocation}
                        initialClientId={initialClientId}
                        initialViewport={initialViewport}
                        onViewportChange={setViewport}
                        pins={filteredPlaces}
                        staffId={staffId}
                        clientsById={clientsById}
                        focusClientId={focusClientId}
                        selectedPlaceId={selectedPlaceId}
                        onSelectPlace={selectPlace}
                        onVisiblePlacesChange={setVisiblePlaceIds}
                        onPickLocation={
                            canManage
                                ? (lat, lng) => setPickedLocation({ lat, lng })
                                : undefined
                        }
                        onReady={(map) => {
                            mapRef.current = map;
                        }}
                    />
                </ClientOnly>

                <div className="pointer-events-none absolute top-3 left-3 z-[1000] flex w-[min(22rem,calc(100%-1.5rem))] flex-col gap-2">
                    <div className="pointer-events-auto rounded-2xl border bg-white/95 p-2 shadow-lg backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/95">
                        <div className="mb-2 space-y-1 border-b pb-2">
                            <label
                                htmlFor="crm-staff-filter"
                                className="px-1 text-xs font-medium"
                            >
                                関わった担当者(社)
                            </label>
                            <NativeSelect
                                id="crm-staff-filter"
                                value={
                                    staffId === null ? 'all' : String(staffId)
                                }
                                onChange={(event) =>
                                    chooseStaff(
                                        event.target.value === 'all'
                                            ? null
                                            : Number(event.target.value),
                                    )
                                }
                            >
                                <option value="all">すべての担当者</option>
                                <option value={auth.user.id}>
                                    自分が関わった地点（{auth.user.name}）
                                </option>
                                {staff
                                    .filter((user) => user.id !== auth.user.id)
                                    .map((user) => (
                                        <option key={user.id} value={user.id}>
                                            {user.name}
                                        </option>
                                    ))}
                            </NativeSelect>
                            <p className="px-1 text-[11px] text-muted-foreground">
                                {staffId === null
                                    ? 'すべての記録から、関わった担当者を表示します。'
                                    : '選んだ担当者の地点を表示中。他の担当者も確認できます。'}
                            </p>
                        </div>
                        {focusClient ? (
                            <div className="flex items-center gap-2 px-1">
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                    {focusClient.name}
                                </span>
                                <HelpButton
                                    helpTitle="絞り込みを解除"
                                    help="顧客の絞り込みを解除して、すべての顧客の地点を表示します。地図の位置は変わりません。"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="絞り込みを解除"
                                    onClick={() => setFocusClientId(null)}
                                >
                                    <X className="size-4" />
                                </HelpButton>
                            </div>
                        ) : (
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    aria-label="顧客を探す"
                                    className="border-0 pl-9 shadow-none focus-visible:ring-0"
                                    placeholder="顧客を探す"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                        )}
                        {searchResults.length > 0 && (
                            <ul className="mt-1 max-h-64 overflow-y-auto border-t pt-1 dark:border-neutral-800">
                                {searchResults.map((client) => (
                                    <li key={client.id}>
                                        <button
                                            type="button"
                                            className="flex w-full items-center gap-2 rounded-lg p-2 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-900"
                                            onClick={() => focusOn(client)}
                                        >
                                            {client.name}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {searchTerm !== '' && searchResults.length === 0 && (
                            <p className="p-2 text-xs text-muted-foreground">
                                {staffId === null
                                    ? '該当する顧客がいません。'
                                    : 'この担当者が関わった地点に該当する顧客がいません。'}
                            </p>
                        )}
                    </div>

                    {!currentLocation && legendStaff.length > 0 && (
                        <div className="pointer-events-auto hidden max-h-[40vh] overflow-y-auto rounded-2xl border bg-white/95 p-2 shadow-lg backdrop-blur sm:block dark:border-neutral-800 dark:bg-neutral-950/95">
                            <p className="px-1 pb-1 text-xs text-muted-foreground">
                                表示中の担当者（タップで地点を絞り込み）
                            </p>
                            <ul>
                                {legendStaff.map((user) => (
                                    <li key={user.id}>
                                        <button
                                            type="button"
                                            aria-pressed={staffId === user.id}
                                            className={cn(
                                                'flex w-full items-center gap-2 rounded-lg p-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-900',
                                                staffId === user.id &&
                                                    'bg-neutral-100 font-medium dark:bg-neutral-900',
                                            )}
                                            onClick={() =>
                                                chooseStaff(
                                                    staffId === user.id
                                                        ? null
                                                        : user.id,
                                                )
                                            }
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="size-3 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor: staffColor(
                                                        user.id,
                                                    ),
                                                }}
                                            />
                                            <span className="truncate">
                                                {user.name}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="pointer-events-auto flex flex-wrap items-center gap-2">
                        {canManage && (
                            <HelpButton
                                helpTitle="地点を追加"
                                help="顧客を選び、住所検索・地図のタップ・ピンの移動で地点を登録します。最初は現在の地図の中央が選ばれます。"
                                size="sm"
                                className="rounded-full shadow-md"
                                onClick={() => {
                                    const center = mapRef.current
                                        ?.getCenter()
                                        .wrap();

                                    if (center) {
                                        setPickedLocation({
                                            lat: center.lat,
                                            lng: center.lng,
                                        });
                                    }
                                }}
                            >
                                <Plus className="size-4" />
                                地点を追加
                            </HelpButton>
                        )}
                        <span className="inline-flex items-center rounded-full border bg-white/95 pr-1 text-xs shadow-md dark:border-neutral-800 dark:bg-neutral-950/95">
                            <label className="flex cursor-pointer items-center gap-2 py-1.5 pr-2 pl-3">
                                <Checkbox
                                    checked={filters.archived}
                                    disabled={isChangingArchive}
                                    onCheckedChange={(checked) =>
                                        visitMap(
                                            selectedPlaceId,
                                            checked === true,
                                            isShowingAllLogs,
                                        )
                                    }
                                />
                                アーカイブも表示
                            </label>
                            <ActionHelp title="アーカイブも表示" embedded>
                                完了した現場など、通常は隠している地点も地図に表示します。過去の記録は削除されません。
                            </ActionHelp>
                        </span>
                        <HelpButton
                            helpTitle="現在地"
                            help="許可すると取得時点の現在地を青い点、精度を円で表示します。自動追跡はしません。移動後は再度押して更新してください。近い訪問先を直線距離で表示します。位置情報はサーバーに保存しません。"
                            size="sm"
                            variant="outline"
                            className="rounded-full bg-white/95 shadow-md dark:bg-neutral-950/95"
                            onClick={locateMe}
                            disabled={isLocating || viewport === null}
                        >
                            <Crosshair className="size-4" />
                            {isLocating
                                ? '取得中...'
                                : currentLocation
                                  ? '現在地を更新'
                                  : '現在地'}
                        </HelpButton>
                        <HelpButton
                            helpTitle="一覧"
                            help="顧客・担当者・地点を一覧で確認します。「地図に戻る」で、今の地図の位置や絞り込みに戻れます。"
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-full bg-white/95 shadow-md dark:bg-neutral-950/95"
                        >
                            <Link href={clientIndex()}>
                                <List className="size-4" />
                                一覧
                            </Link>
                        </HelpButton>
                        <ActionHelp title="地図の操作">
                            ピンには、その地点に記録を残したすべての担当者を表示します。訪問・電話・打合せなど、すべての種類の記録が対象です。担当者を選ぶと、その方が関わった地点に絞り込めます。ピンを押すと各担当者の最終記録日と顧客全体の履歴が開きます。数字の丸は複数の地点で、押すと拡大します。顧客名でも検索できます。
                        </ActionHelp>
                    </div>
                    {locateMessage && (
                        <p
                            role="status"
                            className="pointer-events-auto rounded-lg bg-white/95 px-3 py-2 text-xs shadow-md dark:bg-neutral-950/95"
                        >
                            {locateMessage}
                        </p>
                    )}
                    {currentLocation && selectedPlaceId === null && (
                        <section
                            aria-label="近くの訪問先"
                            className="pointer-events-auto max-h-[30svh] space-y-2 overflow-y-auto rounded-2xl border bg-white/95 p-3 shadow-lg dark:border-neutral-800 dark:bg-neutral-950/95"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        近くの訪問先{' '}
                                        <span className="text-xs font-normal">
                                            5km以内・近い順
                                        </span>
                                    </h2>
                                    <p className="text-[11px] text-muted-foreground">
                                        取得{' '}
                                        {new Date(
                                            currentLocation.timestamp,
                                        ).toLocaleTimeString('ja-JP', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}{' '}
                                        ・ 精度 約
                                        {formatDistance(
                                            currentLocation.accuracy,
                                        )}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="ml-auto shrink-0 sm:hidden"
                                    aria-expanded={nearbyExpanded}
                                    aria-controls="nearby-places-content"
                                    onClick={() =>
                                        setNearbyExpanded(
                                            (expanded) => !expanded,
                                        )
                                    }
                                >
                                    候補{nearby.length}件
                                    <ChevronDown
                                        className={cn(
                                            'size-4 transition-transform',
                                            nearbyExpanded && 'rotate-180',
                                        )}
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-7 shrink-0"
                                    aria-label="現在地の表示を消す"
                                    onClick={() => {
                                        locationRequestRef.current++;
                                        setIsLocating(false);
                                        setCurrentLocation(null);
                                        setLocateMessage(null);
                                    }}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                            <div
                                id="nearby-places-content"
                                className={cn(
                                    'space-y-2',
                                    !nearbyExpanded && 'hidden sm:block',
                                )}
                            >
                                <p className="text-[11px] text-muted-foreground">
                                    直線距離の目安です。移動距離・所要時間ではありません。移動後は現在地を更新してください。
                                </p>
                                {currentLocation.accuracy > 1000 && (
                                    <p className="text-xs text-amber-700 dark:text-amber-400">
                                        位置情報の精度が低いため、候補・距離は参考にしてください。
                                    </p>
                                )}
                                {nearby.length === 0 ? (
                                    <p className="text-xs">
                                        5km以内にアクティブな地点がありません。
                                    </p>
                                ) : (
                                    <ul className="space-y-1">
                                        {nearby.map(({ pin, distance }) => (
                                            <li key={pin.id}>
                                                <button
                                                    type="button"
                                                    className="flex w-full items-center justify-between gap-2 rounded-lg p-2 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                                    onClick={() =>
                                                        selectPlace(pin.id)
                                                    }
                                                >
                                                    <span className="min-w-0">
                                                        <span className="block truncate font-medium">
                                                            {
                                                                clientsById.get(
                                                                    pin.client_id,
                                                                )?.name
                                                            }
                                                        </span>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {pin.name}
                                                        </span>
                                                    </span>
                                                    <span className="shrink-0 text-xs tabular-nums">
                                                        約
                                                        {formatDistance(
                                                            distance,
                                                        )}
                                                    </span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </section>
                    )}
                    {focusClient &&
                        !filteredPlaces.some(
                            (pin) => pin.client_id === focusClient.id,
                        ) && (
                            <p
                                role="status"
                                className="pointer-events-auto rounded-lg bg-white/95 px-3 py-2 text-sm dark:bg-neutral-950/95"
                            >
                                この顧客の表示できる地点がありません。
                                {canManage
                                    ? 'アーカイブ表示を確認するか、顧客詳細から地点を追加してください。'
                                    : 'アーカイブ表示を確認するか、管理者に地点の登録を依頼してください。'}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setFocusClientId(null);
                                        chooseStaff(null);
                                    }}
                                >
                                    絞り込みをリセット
                                </Button>
                            </p>
                        )}
                    {staffId !== null &&
                        filteredPlaces.length === 0 &&
                        !focusClient && (
                            <div
                                role="status"
                                className="pointer-events-auto rounded-lg bg-background p-3 text-sm shadow-md"
                            >
                                この担当者の記録がある地点はありません。
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => chooseStaff(null)}
                                >
                                    すべての担当者を表示
                                </Button>
                            </div>
                        )}
                    {selectionError && (
                        <p
                            role="alert"
                            className="pointer-events-auto rounded-lg bg-white/95 px-3 py-2 text-sm shadow-md dark:bg-neutral-950/95"
                        >
                            {selectionError}
                        </p>
                    )}
                    {places.length === 0 && (
                        <p className="pointer-events-auto rounded-lg bg-white/95 px-3 py-2 text-xs text-muted-foreground shadow-md dark:bg-neutral-950/95">
                            {canManage
                                ? '地点がまだありません。地図を長押しするか、顧客ページから地点を追加してください。'
                                : '地点がまだありません。'}
                        </p>
                    )}
                </div>

                <p className="pointer-events-none absolute right-3 bottom-6 z-[400] hidden rounded-lg bg-white/90 px-2 py-1 text-[11px] text-muted-foreground shadow sm:block dark:bg-neutral-950/90">
                    色＝担当者 ・ 四角＝事務所 ・ 緑の点＝7日以内の記録
                    {canManage && ' ・ 長押しで地点を追加'}
                </p>

                {selectedPlaceId !== null && (
                    <PlacePanel
                        key={selectedPlaceId}
                        selected={panelSelection}
                        staffId={staffId}
                        canManage={canManage}
                        logTypes={logTypes}
                        reactions={reactions}
                        attachmentLimits={attachmentLimits}
                        isLoadingOlderLogs={isLoadingOlderLogs}
                        onClose={closePanel}
                        onNavigatePlace={(id, archived) =>
                            visitMap(id, archived || filters.archived)
                        }
                        onShowOlderLogs={() => {
                            setIsLoadingOlderLogs(true);
                            visitMap(selectedPlaceId, filters.archived, true);
                        }}
                    />
                )}
            </div>

            <Dialog
                open={pickedLocation !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPickedLocation(null);
                        setClientChoiceSearch('');
                    }
                }}
            >
                <DialogContent className="max-h-[80svh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>ここに地点を追加</DialogTitle>
                        <DialogDescription>
                            顧客を選択してください。次の画面で住所や位置を調整できます。
                        </DialogDescription>
                    </DialogHeader>
                    {clients.length === 0 ? (
                        <HelpButton
                            asChild
                            helpTitle="顧客を追加"
                            help="まず顧客名と色を登録します。登録後、その顧客の事務所や現場を地図に追加できます。"
                        >
                            <Link href={clientCreate()}>顧客を追加</Link>
                        </HelpButton>
                    ) : (
                        <>
                            <Input
                                aria-label="地点を追加する顧客を探す"
                                placeholder="顧客名で検索"
                                value={clientChoiceSearch}
                                onChange={(event) =>
                                    setClientChoiceSearch(event.target.value)
                                }
                            />
                            {clientChoices.length === 0 ? (
                                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground dark:border-neutral-800">
                                    該当する顧客がいません。
                                </p>
                            ) : (
                                <ul className="divide-y rounded-xl border dark:border-neutral-800">
                                    {clientChoices.map((client) => (
                                        <li key={client.id}>
                                            <Link
                                                href={placeCreate(client.id, {
                                                    query: pickedLocation ?? {},
                                                })}
                                                className="flex items-center gap-3 p-3 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-900"
                                            >
                                                {client.name}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

CrmMapPage.layout = {
    breadcrumbs: [{ title: '顧客マップ', href: crmMap() }],
};
