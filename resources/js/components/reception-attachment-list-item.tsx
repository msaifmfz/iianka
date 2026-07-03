import {
    Download,
    ExternalLink,
    FileAudio,
    FileText,
    Image as ImageIcon,
    Trash2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ReceptionCaseAttachment } from '@/types';

function formatBytes(size: number | null): string {
    if (size === null) {
        return '-';
    }

    if (size < 1024 * 1024) {
        return `${Math.max(1, Math.round(size / 1024))}KB`;
    }

    return `${(size / 1024 / 1024).toFixed(1)}MB`;
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '';
    }

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function AttachmentIcon({
    attachment,
}: {
    attachment: ReceptionCaseAttachment;
}) {
    if (attachment.preview_mode === 'image') {
        return <ImageIcon className="size-4 text-sky-600" />;
    }

    if (attachment.preview_mode === 'audio') {
        return <FileAudio className="size-4 text-emerald-600" />;
    }

    return <FileText className="size-4 text-muted-foreground" />;
}

export function AttachmentListItem({
    attachment,
    canUpdate,
    deleteDisabled,
    onDelete,
}: {
    attachment: ReceptionCaseAttachment;
    canUpdate: boolean;
    deleteDisabled: boolean;
    onDelete: (attachment: ReceptionCaseAttachment) => void;
}) {
    const hasOpenPreview =
        attachment.preview_mode === 'image' ||
        attachment.preview_mode === 'pdf';

    return (
        <div className="grid gap-3 rounded-lg border p-3">
            <div className="grid gap-3 sm:grid-cols-[auto_1fr_auto] sm:items-start">
                <div
                    className={cn(
                        'flex size-12 items-center justify-center overflow-hidden rounded-md border bg-muted',
                        attachment.preview_mode === 'image' && 'bg-background',
                    )}
                >
                    {attachment.preview_mode === 'image' ? (
                        <img
                            src={attachment.url}
                            alt=""
                            loading="lazy"
                            className="size-full object-cover"
                        />
                    ) : (
                        <AttachmentIcon attachment={attachment} />
                    )}
                </div>

                <div className="min-w-0 space-y-1">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-medium">
                            {attachment.name}
                        </p>
                        <Badge variant="secondary">
                            {attachment.kind_label}
                        </Badge>
                        <Badge variant="outline">
                            {attachment.source_label}
                        </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {formatBytes(attachment.size)}
                        {attachment.duration_seconds
                            ? ` / ${formatDuration(attachment.duration_seconds)}`
                            : ''}
                        {attachment.uploaded_by
                            ? ` / ${attachment.uploaded_by.name}`
                            : ''}
                        {attachment.created_at
                            ? ` / ${formatDate(attachment.created_at)}`
                            : ''}
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    {hasOpenPreview && (
                        <Button
                            asChild
                            type="button"
                            variant="outline"
                            size="sm"
                        >
                            <a
                                href={attachment.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                開く
                            </a>
                        </Button>
                    )}
                    <Button
                        asChild
                        type="button"
                        variant={
                            attachment.preview_mode === 'download'
                                ? 'outline'
                                : 'ghost'
                        }
                        size={
                            attachment.preview_mode === 'download'
                                ? 'sm'
                                : 'icon'
                        }
                    >
                        <a
                            href={attachment.download_url}
                            aria-label="添付資料をダウンロード"
                        >
                            <Download className="size-4" />
                            {attachment.preview_mode === 'download' &&
                                'ダウンロード'}
                        </a>
                    </Button>
                    {canUpdate && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="添付資料を削除"
                            disabled={deleteDisabled}
                            onClick={() => onDelete(attachment)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </div>
            </div>

            {attachment.preview_mode === 'image' && (
                <a
                    href={attachment.url}
                    target="_blank"
                    rel="noreferrer"
                    className="block overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        src={attachment.url}
                        alt=""
                        loading="lazy"
                        className="max-h-56 w-full object-contain"
                    />
                </a>
            )}

            {attachment.preview_mode === 'pdf' && (
                <iframe
                    title={attachment.name}
                    src={attachment.url}
                    className="h-72 w-full rounded-md border bg-muted"
                />
            )}

            {attachment.preview_mode === 'audio' && (
                <audio
                    className="w-full"
                    src={attachment.url}
                    controls
                    preload="metadata"
                />
            )}
        </div>
    );
}
