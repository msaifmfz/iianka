import { Head, Link, router, useForm } from '@inertiajs/react';
import type L from 'leaflet';
import { ArrowLeft, Crosshair, MapPin, Search, Trash2 } from 'lucide-react';
import { lazy, useEffect, useRef, useState } from 'react';
import {
    show as clientShow,
    index as clientIndex,
} from '@/actions/App/Http/Controllers/ClientController';
import {
    destroy as placeDestroy,
    store as placeStore,
    update as placeUpdate,
} from '@/actions/App/Http/Controllers/ClientPlaceController';
import geocode from '@/actions/App/Http/Controllers/CrmGeocodeController';
import ClientBadge from '@/components/client-badge';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import ClientOnly from '@/components/crm-map/client-only';
import FormField from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { cn } from '@/lib/utils';
import type {
    ClientPlace,
    ClientPlaceKind,
    ClientSummary,
    CrmOption,
    MapPin as MapPinType,
} from '@/types';

const LocationPicker = lazy(
    () => import('@/components/crm-map/location-picker'),
);

type Position = { lat: number; lng: number };

type Props = {
    client: ClientSummary;
    place: ClientPlace | null;
    initialPosition: Position | null;
    otherPlaces: MapPinType[];
    kinds: CrmOption[];
};

type PlaceForm = {
    kind: ClientPlaceKind;
    name: string;
    address: string;
    lat: number | '';
    lng: number | '';
};

type Candidate = { label: string; lat: number; lng: number };

export default function ClientPlaceFormPage({
    client,
    place,
    initialPosition,
    otherPlaces,
    kinds,
}: Props) {
    const startPosition = place
        ? { lat: place.lat, lng: place.lng }
        : initialPosition;
    const { data, setData, post, patch, processing, errors, transform } =
        useForm<PlaceForm>({
            kind: place?.kind ?? 'site',
            name: place?.name ?? '',
            address: place?.address ?? '',
            lat: startPosition?.lat ?? '',
            lng: startPosition?.lng ?? '',
        });
    const mapRef = useRef<L.Map | null>(null);
    const [candidates, setCandidates] = useState<Candidate[] | null>(null);
    const [lookupMessage, setLookupMessage] = useState<string | null>(null);
    const [isSearching, setIsSearching] = useState(false);
    const searchControllerRef = useRef<AbortController | null>(null);
    const { confirm, dialog } = useConfirmDialog();

    useEffect(() => () => searchControllerRef.current?.abort(), []);

    function cancelSearch() {
        searchControllerRef.current?.abort();
        setIsSearching(false);
        setCandidates(null);
        setLookupMessage(null);
    }

    const position: Position | null =
        data.lat === '' || data.lng === ''
            ? null
            : { lat: data.lat, lng: data.lng };
    const title = place ? '地点を編集' : '地点を追加';

    function moveTo(next: Position, zoom = 17) {
        setData((current) => ({ ...current, lat: next.lat, lng: next.lng }));
        mapRef.current?.setView([next.lat, next.lng], zoom);
    }

    async function searchAddress() {
        const address = data.address.trim();

        if (address === '') {
            setLookupMessage('住所を入力してください。');

            return;
        }

        setIsSearching(true);
        setLookupMessage(null);
        setCandidates(null);
        searchControllerRef.current?.abort();
        const controller = new AbortController();
        searchControllerRef.current = controller;

        try {
            const response = await fetch(geocode.url({ query: { address } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const body = (await response.json()) as {
                candidates?: Candidate[];
            };
            const found = response.ok ? (body.candidates ?? []) : [];

            if (controller.signal.aborted) {
                return;
            }

            if (found.length === 1) {
                moveTo(found[0]);
                setCandidates(null);
            } else {
                setCandidates(found);
            }

            if (found.length === 0) {
                setLookupMessage(
                    '住所が見つかりませんでした。地図をタップして位置を指定してください。',
                );
            }
        } catch {
            if (controller.signal.aborted) {
                return;
            }

            setLookupMessage(
                '住所を検索できませんでした。地図をタップして位置を指定してください。',
            );
        } finally {
            if (!controller.signal.aborted) {
                setIsSearching(false);
            }
        }
    }

    function fillCurrentLocation() {
        cancelSearch();

        if (!('geolocation' in navigator)) {
            setLookupMessage('この端末では現在地を取得できません。');

            return;
        }

        setLookupMessage('現在地を取得中...');
        navigator.geolocation.getCurrentPosition(
            (result) => {
                setLookupMessage(null);
                moveTo({
                    lat: result.coords.latitude,
                    lng: result.coords.longitude,
                });
            },
            () =>
                setLookupMessage(
                    '現在地を取得できませんでした。位置情報の許可を確認してください。',
                ),
            { enableHighAccuracy: true, timeout: 10000 },
        );
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        // Round to ~1cm; the column keeps 7 decimals.
        transform((current) => ({
            ...current,
            lat: current.lat === '' ? '' : Number(current.lat.toFixed(7)),
            lng: current.lng === '' ? '' : Number(current.lng.toFixed(7)),
        }));

        if (place) {
            patch(placeUpdate.url(place.id));
        } else {
            post(placeStore.url(client.id));
        }
    }

    async function deletePlace() {
        if (
            !place ||
            !(await confirm({
                title: `${place.name} を削除しますか？`,
                description:
                    'この地点の記録と添付もすべて削除されます。終わった現場はアーカイブすると記録を残せます。',
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(placeDestroy.url(place.id));
    }

    return (
        <>
            <Head title={title} />
            {dialog}
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <ClientBadge client={client} />
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {client.name}
                            </p>
                            <h1 className="text-2xl font-bold">{title}</h1>
                        </div>
                    </div>
                    <Button
                        helpTitle="戻る"
                        help="地点の変更を保存せず、この顧客の詳細へ戻ります。"
                        asChild
                        variant="outline"
                    >
                        <Link href={clientShow(client.id)}>
                            <ArrowLeft className="size-4" />
                            戻る
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-2xl border bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <FormField
                        as="div"
                        label="種別"
                        required
                        error={errors.kind}
                    >
                        <div className="flex flex-wrap gap-2">
                            {kinds.map((kind) => (
                                <Button
                                    helpTitle={kind.label}
                                    help={
                                        kind.value === 'office'
                                            ? '顧客の事務所・支店を表します。地図では四角いピンになります。電話など場所のない活動も事務所に記録できます。'
                                            : kind.value === 'site'
                                              ? '工事現場や作業場所を表します。地図では丸いピンになります。'
                                              : '打ち合わせ場所など、事務所・現場以外の地点に使います。'
                                    }
                                    key={kind.value}
                                    type="button"
                                    variant={
                                        data.kind === kind.value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={data.kind === kind.value}
                                    onClick={() =>
                                        setData(
                                            'kind',
                                            kind.value as ClientPlaceKind,
                                        )
                                    }
                                >
                                    {kind.label}
                                </Button>
                            ))}
                        </div>
                        <span className="text-xs font-normal text-muted-foreground">
                            事務所は地図上で四角いピンになります。
                        </span>
                    </FormField>

                    <FormField label="地点名" required error={errors.name}>
                        <Input
                            required
                            placeholder="例：本社、梅田現場"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        as="div"
                        label="住所"
                        labelId="place-address-label"
                        error={errors.address}
                    >
                        <div className="flex gap-2">
                            <Input
                                aria-labelledby="place-address-label"
                                value={data.address}
                                placeholder="住所を入力して検索"
                                onChange={(event) => {
                                    cancelSearch();
                                    setData('address', event.target.value);
                                }}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' &&
                                        !event.nativeEvent.isComposing
                                    ) {
                                        event.preventDefault();
                                        void searchAddress();
                                    }
                                }}
                            />
                            <Button
                                helpTitle="住所を検索"
                                help="入力した住所の候補を探します。候補が複数ある場合は選択してください。見つからない場合は地図をタップして位置を指定できます。"
                                type="button"
                                variant="outline"
                                disabled={isSearching}
                                onClick={() => void searchAddress()}
                            >
                                <Search className="size-4" />
                                検索
                            </Button>
                        </div>
                    </FormField>

                    {candidates && candidates.length > 1 && (
                        <ul className="divide-y rounded-xl border text-sm dark:border-neutral-800">
                            {candidates.map((candidate) => (
                                <li key={`${candidate.lat},${candidate.lng}`}>
                                    <button
                                        type="button"
                                        className="flex w-full items-center gap-2 p-3 text-left hover:bg-neutral-50 dark:hover:bg-neutral-900"
                                        onClick={() => {
                                            moveTo(candidate);
                                            setCandidates(null);
                                        }}
                                    >
                                        <MapPin className="size-4 shrink-0 text-muted-foreground" />
                                        {candidate.label}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="grid gap-2">
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm font-medium">
                            <span>
                                位置（地図をタップ、またはピンをドラッグ）
                            </span>
                            <Button
                                helpTitle="現在地を使う"
                                help="端末の位置情報でピンを移動します。位置を確認し、最後に保存ボタンを押してください。"
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={fillCurrentLocation}
                            >
                                <Crosshair className="size-4" />
                                現在地を使う
                            </Button>
                        </div>
                        <div
                            className={cn(
                                'h-80 overflow-hidden rounded-xl border sm:h-96 dark:border-neutral-800',
                                (errors.lat || errors.lng) &&
                                    'border-destructive',
                            )}
                        >
                            <ClientOnly>
                                <LocationPicker
                                    client={client}
                                    kind={data.kind}
                                    position={position}
                                    otherPins={otherPlaces}
                                    onChange={(next) => {
                                        cancelSearch();
                                        moveTo(next, mapRef.current?.getZoom());
                                    }}
                                    onReady={(map) => {
                                        mapRef.current = map;
                                    }}
                                />
                            </ClientOnly>
                        </div>
                        {lookupMessage && (
                            <p
                                role="status"
                                className="text-xs text-muted-foreground"
                            >
                                {lookupMessage}
                            </p>
                        )}
                        {(errors.lat || errors.lng) && (
                            <p className="text-xs text-destructive">
                                {errors.lat ?? errors.lng}
                            </p>
                        )}
                        {position && (
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {position.lat.toFixed(6)},{' '}
                                {position.lng.toFixed(6)}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap justify-between gap-2">
                        {place ? (
                            <Button
                                helpTitle="地点を削除"
                                help="確認後、この地点とすべての記録・写真・音声を削除します。履歴を残したい場合は地図からアーカイブしてください。"
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => void deletePlace()}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button
                            helpTitle={title}
                            help="地点名・種別・住所と、地図のピン位置を保存します。"
                            type="submit"
                            disabled={processing}
                        >
                            {processing ? '保存中...' : title}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ClientPlaceFormPage.layout = {
    breadcrumbs: [{ title: '顧客', href: clientIndex() }],
};
