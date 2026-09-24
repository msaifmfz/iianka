import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    MapPin,
    Mic,
    Navigation,
    Pencil,
    Plus,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { show as clientShow } from '@/actions/App/Http/Controllers/ClientController';
import {
    store as placeArchive,
    destroy as placeUnarchive,
} from '@/actions/App/Http/Controllers/ClientPlaceArchiveController';
import { edit as placeEdit } from '@/actions/App/Http/Controllers/ClientPlaceController';
import { destroy as attachmentDestroy } from '@/actions/App/Http/Controllers/ClientPlaceLogAttachmentController';
import { destroy as logDestroy } from '@/actions/App/Http/Controllers/ClientPlaceLogController';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import { Badge } from '@/components/ui/badge';
import { Button as PlainButton } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { formatCrmDateTime, googleMapsDirectionsUrl } from '@/lib/crm';
import { sortedStaff, staffColor, staffName } from '@/lib/crm-staff';
import { formatMinutesSeconds } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ClientPlaceLog,
    ClientReaction,
    CrmAttachmentLimits,
    CrmOption,
    SelectedPlace,
} from '@/types';
import PanelShell from './panel-shell';
import PlaceLogForm from './place-log-form';

const reactionBadge: Record<ClientReaction, string> = {
    positive:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    neutral:
        'border-neutral-200 bg-neutral-50 text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300',
    negative:
        'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300',
};

const partialReload = {
    preserveScroll: true,
    preserveState: true,
    only: ['selectedPlace', 'places'],
};

function LogEntry({
    log,
    selectedPlaceId,
    onNavigatePlace,
    onEdit,
    onDelete,
    onDeleteAttachment,
}: {
    log: ClientPlaceLog;
    selectedPlaceId: number;
    onNavigatePlace: (placeId: number, archived: boolean) => void;
    onEdit: () => void;
    onDelete: () => void;
    onDeleteAttachment: (attachmentId: number) => void;
}) {
    const images = log.attachments.filter((item) => item.kind === 'image');
    const recordings = log.attachments.filter((item) => item.kind === 'audio');
    const isCurrentPlace = log.place.id === selectedPlaceId;

    return (
        <li
            data-current-place={isCurrentPlace}
            className={cn(
                'relative space-y-2 border-l-2 border-neutral-200 pb-5 pl-4 dark:border-neutral-800',
                isCurrentPlace &&
                    'mb-3 rounded-r-xl border-l-sky-400 bg-sky-50/70 py-3 pr-3 dark:border-l-sky-700 dark:bg-sky-950/25',
                !isCurrentPlace &&
                    'opacity-70 transition-opacity focus-within:opacity-100 hover:opacity-100',
            )}
        >
            <span className="absolute top-1 -left-[5px] size-2 rounded-full bg-neutral-400" />
            <p className="flex flex-wrap items-center gap-1.5 text-xs">
                <MapPin className="size-3.5 shrink-0" />
                <span className="font-medium">{log.place.name}</span>
                {isCurrentPlace ? (
                    <Badge variant="default">この地点</Badge>
                ) : (
                    <PlainButton
                        type="button"
                        size="sm"
                        variant="secondary"
                        className="h-7 px-2 text-xs"
                        aria-label={`${log.place.name}を地図で表示`}
                        onClick={() =>
                            onNavigatePlace(
                                log.place.id,
                                log.place.archived_at !== null,
                            )
                        }
                    >
                        別地点 <MapPin className="size-3" />
                    </PlainButton>
                )}
                {log.place.archived_at && (
                    <Badge variant="outline">アーカイブ</Badge>
                )}
            </p>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <time dateTime={log.occurred_at}>
                    {formatCrmDateTime(log.occurred_at)}
                </time>
                <Badge variant="outline">{log.type_label}</Badge>
                {log.reaction && (
                    <Badge
                        variant="outline"
                        className={reactionBadge[log.reaction]}
                    >
                        <span aria-hidden="true">
                            {
                                {
                                    positive: '😊',
                                    neutral: '😐',
                                    negative: '😟',
                                }[log.reaction]
                            }
                        </span>
                        {log.reaction_label}
                    </Badge>
                )}
                {log.can_edit && (
                    <span className="ml-auto flex">
                        <PlainButton
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label="記録を編集"
                            onClick={onEdit}
                        >
                            <Pencil className="size-3.5" />
                        </PlainButton>
                        <PlainButton
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label="記録を削除"
                            onClick={onDelete}
                        >
                            <Trash2 className="size-3.5" />
                        </PlainButton>
                    </span>
                )}
            </div>
            <p className="text-sm leading-6 wrap-anywhere whitespace-pre-line">
                {log.summary}
            </p>
            <p className="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
                <span className="font-medium">
                    担当者(社): {log.user?.name ?? '未登録・削除済み'}
                </span>
                {log.contact && (
                    <span className="flex items-center gap-1">
                        <UserRound className="size-3" />
                        担当者(客): {log.contact.name}
                    </span>
                )}
            </p>
            {images.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {images.map((image) => (
                        <span key={image.id} className="relative">
                            <a
                                href={image.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={image.url}
                                    alt={image.name}
                                    loading="lazy"
                                    className="size-20 rounded-lg border object-cover dark:border-neutral-800"
                                />
                            </a>
                            {log.can_edit && (
                                <button
                                    type="button"
                                    aria-label="写真を削除"
                                    className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white"
                                    onClick={() => onDeleteAttachment(image.id)}
                                >
                                    <Trash2 className="size-3" />
                                </button>
                            )}
                        </span>
                    ))}
                </div>
            )}
            {recordings.map((recording) => (
                <div key={recording.id} className="flex items-center gap-2">
                    <Mic className="size-4 shrink-0 text-muted-foreground" />
                    <audio
                        controls
                        preload="none"
                        src={recording.url}
                        className="h-9 min-w-0 flex-1"
                    />
                    {recording.duration_seconds !== null && (
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {formatMinutesSeconds(recording.duration_seconds)}
                        </span>
                    )}
                    {log.can_edit && (
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label="音声を削除"
                            helpTitle="音声を削除"
                            help="確認後、この音声だけを削除します。記録の本文は残ります。"
                            onClick={() => onDeleteAttachment(recording.id)}
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    )}
                </div>
            ))}
        </li>
    );
}

/**
 * A tapped pin's panel: who and where, then its history newest first so the
 * next person sees the latest conversation and reaction before they act.
 */
export default function PlacePanel({
    selected,
    staffId,
    canManage,
    logTypes,
    reactions,
    attachmentLimits,
    isLoadingOlderLogs,
    onClose,
    onNavigatePlace,
    onShowOlderLogs,
}: {
    /** `null` while the tapped pin's history is loading. */
    selected: SelectedPlace | null;
    staffId: number | null;
    canManage: boolean;
    logTypes: CrmOption[];
    reactions: CrmOption[];
    attachmentLimits: CrmAttachmentLimits;
    isLoadingOlderLogs: boolean;
    onClose: () => void;
    onNavigatePlace: (placeId: number, archived: boolean) => void;
    onShowOlderLogs: () => void;
}) {
    const { confirm, dialog } = useConfirmDialog();
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    if (selected === null) {
        return (
            <PanelShell
                title={<Skeleton className="h-10 w-48" />}
                onClose={onClose}
            >
                <div className="space-y-4">
                    <Skeleton className="h-9 w-32" />
                    <Skeleton className="h-24 w-full" />
                    <Skeleton className="h-24 w-full" />
                </div>
            </PanelShell>
        );
    }

    const { place, client, logs, older_logs_count: olderLogsCount } = selected;
    const isArchived = place.archived_at !== null;

    async function deleteLog(log: ClientPlaceLog) {
        if (
            await confirm({
                title: 'この記録を削除しますか？',
                description:
                    log.attachments.length > 0
                        ? `添付${log.attachments.length}件も削除されます。`
                        : undefined,
                confirmLabel: '削除',
                variant: 'destructive',
            })
        ) {
            router.delete(logDestroy.url(log.id), partialReload);
        }
    }

    async function deleteAttachment(attachmentId: number) {
        if (
            await confirm({
                title: 'この添付を削除しますか？',
                confirmLabel: '削除',
                variant: 'destructive',
            })
        ) {
            router.delete(attachmentDestroy.url(attachmentId), partialReload);
        }
    }

    async function toggleArchive() {
        if (isArchived) {
            router.delete(placeUnarchive.url(place.id), partialReload);

            return;
        }

        if (
            await confirm({
                title: `${place.name} をアーカイブしますか？`,
                description:
                    '地図に表示されなくなります。記録は残り、「アーカイブも表示」でいつでも確認できます。',
                confirmLabel: 'アーカイブ',
            })
        ) {
            router.post(placeArchive.url(place.id), {}, partialReload);
        }
    }

    const title = (
        <div className="flex items-center gap-3">
            <div className="min-w-0">
                <Link
                    href={clientShow(client.id)}
                    className="block truncate text-xs text-muted-foreground hover:underline"
                >
                    {client.name}
                </Link>
                <p className="flex items-center gap-2 truncate font-semibold">
                    {place.name}
                    <Badge variant="outline" className="shrink-0">
                        {place.kind_label}
                    </Badge>
                    {isArchived && (
                        <Badge variant="secondary" className="shrink-0">
                            アーカイブ
                        </Badge>
                    )}
                </p>
            </div>
        </div>
    );

    return (
        <PanelShell title={title} onClose={onClose} expand={editing !== null}>
            {dialog}
            <div className="space-y-4">
                <section
                    aria-label="担当者の活動サマリー"
                    className="rounded-lg border bg-muted/50 p-3 text-sm"
                >
                    <p className="mb-1 text-xs text-muted-foreground">
                        この地点に関わった担当者(社)・最終記録
                    </p>
                    {selected.staff_activities.length === 0 ? (
                        <p>記録なし</p>
                    ) : (
                        <ul className="space-y-2">
                            {sortedStaff(selected.staff_activities).map(
                                (activity) => (
                                    <li
                                        key={activity.user?.id ?? 'unknown'}
                                        className="flex items-start gap-2"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="mt-1 size-3 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: staffColor(
                                                    activity.user?.id ?? null,
                                                ),
                                            }}
                                        />
                                        <div className="min-w-0">
                                            <p className="font-medium wrap-anywhere">
                                                {staffName(activity)}
                                                {staffId !== null &&
                                                    activity.user?.id ===
                                                        staffId && (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2"
                                                        >
                                                            選択中
                                                        </Badge>
                                                    )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatCrmDateTime(
                                                    activity.occurred_at,
                                                )}
                                            </p>
                                        </div>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </section>
                {place.address && (
                    <p className="text-sm text-muted-foreground">
                        {place.address}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    {editing === null && (
                        <Button
                            helpTitle="記録を追加"
                            help="この地点での訪問・打ち合わせ・電話などを記録します。担当者、顧客の反応、写真・音声も残せます。"
                            size="sm"
                            onClick={() => setEditing('new')}
                        >
                            <Plus className="size-4" />
                            記録を追加
                        </Button>
                    )}
                    <Button
                        helpTitle="経路"
                        help="別のタブでGoogleマップを開き、この地点までの経路を調べます。"
                        asChild
                        size="sm"
                        variant="outline"
                    >
                        <a
                            href={googleMapsDirectionsUrl(place.lat, place.lng)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Navigation className="size-4" />
                            経路
                        </a>
                    </Button>
                    {canManage && (
                        <>
                            <Button
                                helpTitle="地点を編集"
                                help="この地点の名前・住所・種類・ピンの位置を変更します。"
                                asChild
                                size="sm"
                                variant="outline"
                            >
                                <Link href={placeEdit(place.id)}>
                                    <Pencil className="size-4" />
                                    地点を編集
                                </Link>
                            </Button>
                            <Button
                                helpTitle={isArchived ? '再表示' : 'アーカイブ'}
                                help="完了した地点はアーカイブすると地図から隠れます。記録は残り、あとで再表示できます。"
                                size="sm"
                                variant="outline"
                                onClick={() => void toggleArchive()}
                            >
                                {isArchived ? (
                                    <ArchiveRestore className="size-4" />
                                ) : (
                                    <Archive className="size-4" />
                                )}
                                {isArchived ? 'アーカイブ解除' : 'アーカイブ'}
                            </Button>
                        </>
                    )}
                </div>

                {editing === 'new' && (
                    <PlaceLogForm
                        canManage={canManage}
                        key={`new-${place.id}`}
                        selected={selected}
                        log={null}
                        logTypes={logTypes}
                        reactions={reactions}
                        attachmentLimits={attachmentLimits}
                        onDone={() => setEditing(null)}
                    />
                )}

                <div className="space-y-1">
                    <h3 className="text-sm font-semibold">顧客全体の履歴</h3>
                    <p className="text-xs leading-5 text-muted-foreground">
                        新しい順に表示。別地点の履歴は薄く表示しています。新しい記録は「
                        {place.name}」に追加されます。
                        {olderLogsCount > 0 &&
                            `直近${logs.length}件を表示中（全${selected.logs_total}件）。`}
                    </p>
                </div>
                {logs.length === 0 && editing !== 'new' ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground dark:border-neutral-800">
                        まだ記録がありません。最初の記録を追加しましょう。
                    </p>
                ) : (
                    <ol className={cn('pt-2', editing === 'new' && 'pt-4')}>
                        {logs.map((log) =>
                            editing === log.id ? (
                                <li key={log.id} className="pb-5">
                                    <PlaceLogForm
                                        canManage={canManage}
                                        selected={selected}
                                        log={log}
                                        logTypes={logTypes}
                                        reactions={reactions}
                                        attachmentLimits={attachmentLimits}
                                        onDone={() => setEditing(null)}
                                    />
                                </li>
                            ) : (
                                <LogEntry
                                    key={log.id}
                                    log={log}
                                    selectedPlaceId={place.id}
                                    onNavigatePlace={onNavigatePlace}
                                    onEdit={() => setEditing(log.id)}
                                    onDelete={() => void deleteLog(log)}
                                    onDeleteAttachment={(id) =>
                                        void deleteAttachment(id)
                                    }
                                />
                            ),
                        )}
                    </ol>
                )}
                {olderLogsCount > 0 && (
                    <PlainButton
                        type="button"
                        variant="outline"
                        className="w-full"
                        disabled={isLoadingOlderLogs}
                        onClick={onShowOlderLogs}
                    >
                        {isLoadingOlderLogs
                            ? '読み込み中...'
                            : `古い記録を表示（他${olderLogsCount}件）`}
                    </PlainButton>
                )}
            </div>
        </PanelShell>
    );
}
