import { useHttp } from '@inertiajs/react';
import {
    AlertCircle,
    Mic,
    Paperclip,
    Square,
    UploadCloud,
    X,
} from 'lucide-react';
import type { DragEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    destroy as destroyAttachment,
    store as storeAttachment,
} from '@/actions/App/Http/Controllers/ReceptionCaseAttachmentController';
import { AttachmentListItem } from '@/components/reception-attachment-list-item';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAudioRecorder } from '@/hooks/use-audio-recorder';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { cn } from '@/lib/utils';
import type {
    ReceptionAttachmentConstraints,
    ReceptionCaseAttachment,
    ReceptionCaseAttachmentSource,
} from '@/types';

type Props = {
    caseId: number | null;
    initialAttachments: ReceptionCaseAttachment[];
    canUpdate: boolean;
    constraints: ReceptionAttachmentConstraints;
    onCreateDraft?: () => Promise<number | null>;
};

type AttachmentUploadForm = {
    file: File | null;
    name: string;
    source: ReceptionCaseAttachmentSource;
    duration_seconds: number | null;
};

type AttachmentUploadResponse = {
    attachment: ReceptionCaseAttachment;
};

type AttachmentDeleteResponse = {
    deleted_id: number;
};

function fileNameWithoutExtension(name: string): string {
    return name.replace(/\.[^/.]+$/, '');
}

function formatTimer(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function formatUploadLimit(kilobytes: number): string {
    if (kilobytes >= 1024) {
        return `${Math.floor(kilobytes / 1024)}MB`;
    }

    return `${kilobytes}KB`;
}

function errorMessage(errors: Record<string, unknown>): string | null {
    const first: unknown = Object.values(errors)[0];
    const value: unknown = Array.isArray(first) ? first[0] : first;

    return typeof value === 'string' && value !== '' ? value : null;
}

// useHttp only fills `errors` from 422 validation responses. 403/500 reject with
// an HttpResponseError (carrying response.status) and network failures reject
// without a response — those never touch `errors`, so surface a fallback for them.
function attachmentFailureMessage(
    error: unknown,
    fallback: string,
): string | null {
    const status = (error as { response?: { status?: number } } | null)
        ?.response?.status;

    return status === 422 ? null : fallback;
}

export default function ReceptionAttachmentPanel({
    caseId,
    initialAttachments,
    canUpdate,
    constraints,
    onCreateDraft,
}: Props) {
    const {
        max_attachments: maxAttachments,
        max_recordings: maxRecordings,
        max_file_kilobytes: maxFileKilobytes,
        accept,
    } = constraints;

    const [attachments, setAttachments] =
        useState<ReceptionCaseAttachment[]>(initialAttachments);
    const [panelError, setPanelError] = useState<string | null>(null);
    const [isCreatingDraft, setIsCreatingDraft] = useState(false);
    const [isDragging, setIsDragging] = useState(false);

    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const latestCaseIdRef = useRef<number | null>(caseId);
    const attachmentsRef =
        useRef<ReceptionCaseAttachment[]>(initialAttachments);

    const { confirm, dialog } = useConfirmDialog();

    const uploadHttp = useHttp<AttachmentUploadForm, AttachmentUploadResponse>({
        file: null,
        name: '',
        source: 'upload',
        duration_seconds: null,
    });
    const deleteHttp = useHttp<Record<string, never>, AttachmentDeleteResponse>(
        {},
    );

    useEffect(() => {
        latestCaseIdRef.current = caseId;
    }, [caseId]);

    useEffect(() => {
        attachmentsRef.current = attachments;
    }, [attachments]);

    const recordingCount = useMemo(
        () =>
            attachments.filter(
                (attachment) => attachment.source === 'recording',
            ).length,
        [attachments],
    );

    async function ensureCaseId(): Promise<number | null> {
        if (latestCaseIdRef.current !== null) {
            return latestCaseIdRef.current;
        }

        if (!onCreateDraft) {
            setPanelError('下書きを作成してから添付してください。');

            return null;
        }

        setIsCreatingDraft(true);
        setPanelError(null);

        try {
            const draftId = await onCreateDraft();
            latestCaseIdRef.current = draftId;

            return draftId;
        } finally {
            setIsCreatingDraft(false);
        }
    }

    async function uploadFile(
        file: File,
        source: ReceptionCaseAttachmentSource,
        durationSeconds: number | null = null,
        displayName: string = fileNameWithoutExtension(file.name),
    ): Promise<void> {
        if (attachmentsRef.current.length >= maxAttachments) {
            setPanelError(`添付資料は${maxAttachments}件までです。`);

            return;
        }

        const targetCaseId = await ensureCaseId();

        if (targetCaseId === null) {
            return;
        }

        setPanelError(null);
        uploadHttp.clearErrors();
        uploadHttp.transform(() => ({
            file,
            name: displayName,
            source,
            duration_seconds: durationSeconds,
        }));

        const response = await uploadHttp.post(
            storeAttachment.url(targetCaseId),
        );

        setAttachments((current) => [response.attachment, ...current]);
    }

    const { recordingState, recordingSeconds, startRecording, stopRecording } =
        useAudioRecorder({
            maxRecordingSeconds: constraints.max_recording_seconds,
            onSave: (file, durationSeconds, displayName) =>
                uploadFile(file, 'recording', durationSeconds, displayName),
            onError: setPanelError,
        });

    const canAddAttachment =
        canUpdate &&
        attachments.length < maxAttachments &&
        !uploadHttp.processing &&
        !isCreatingDraft &&
        recordingState !== 'saving';
    const canRecord =
        canAddAttachment &&
        recordingCount < maxRecordings &&
        recordingState === 'idle';
    const uploadError = errorMessage(uploadHttp.errors);
    const deleteError = errorMessage(deleteHttp.errors);

    async function uploadSelectedFiles(selectedFiles: File[]): Promise<void> {
        const maxFileBytes = maxFileKilobytes * 1024;
        const allowedSizeFiles = selectedFiles.filter(
            (file) => file.size <= maxFileBytes,
        );

        if (allowedSizeFiles.length < selectedFiles.length) {
            setPanelError(
                `添付資料は${formatUploadLimit(maxFileKilobytes)}までです。`,
            );
        }

        const remainingSlots = maxAttachments - attachmentsRef.current.length;
        const files = allowedSizeFiles.slice(0, remainingSlots);

        if (files.length < allowedSizeFiles.length) {
            setPanelError(`添付資料は${maxAttachments}件までです。`);
        }

        for (const file of files) {
            // Isolate each upload so one rejection (validation/server error) still
            // surfaces without aborting the rest of the batch.
            try {
                await uploadFile(file, 'upload');
            } catch (error) {
                const message = attachmentFailureMessage(
                    error,
                    'アップロードに失敗しました。',
                );

                if (message !== null) {
                    setPanelError(message);
                }
            }
        }
    }

    function handleUploadDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setIsDragging(false);

        if (!canAddAttachment) {
            return;
        }

        void uploadSelectedFiles(Array.from(event.dataTransfer.files));
    }

    async function deleteAttachment(attachment: ReceptionCaseAttachment) {
        const confirmed = await confirm({
            title: 'この添付資料を削除しますか？',
            confirmLabel: '削除',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        setPanelError(null);

        try {
            await deleteHttp.delete(destroyAttachment.url(attachment.id));

            setAttachments((current) =>
                current.filter((item) => item.id !== attachment.id),
            );
        } catch (error) {
            const message = attachmentFailureMessage(
                error,
                '添付資料の削除に失敗しました。',
            );

            if (message !== null) {
                setPanelError(message);
            }
        }
    }

    return (
        <>
            <Card>
                <CardHeader className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <CardTitle className="flex items-center gap-2">
                            <Paperclip className="size-5" />
                            添付資料
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {attachments.length}/{maxAttachments}
                            </Badge>
                            <Badge variant="outline">
                                録音 {recordingCount}/{maxRecordings}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {canUpdate && (
                        <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
                            <div
                                className={cn(
                                    'grid min-h-28 place-items-center rounded-lg border border-dashed bg-muted/25 p-4 text-center transition-colors',
                                    canAddAttachment &&
                                        'cursor-pointer hover:border-primary/60 hover:bg-primary/5',
                                    isDragging &&
                                        'border-primary bg-primary/10 text-primary',
                                    !canAddAttachment &&
                                        'cursor-not-allowed opacity-60',
                                )}
                                role="button"
                                tabIndex={canAddAttachment ? 0 : -1}
                                aria-disabled={!canAddAttachment}
                                onClick={() => fileInputRef.current?.click()}
                                onKeyDown={(event) => {
                                    if (
                                        canAddAttachment &&
                                        (event.key === 'Enter' ||
                                            event.key === ' ')
                                    ) {
                                        event.preventDefault();
                                        fileInputRef.current?.click();
                                    }
                                }}
                                onDragEnter={(event) => {
                                    event.preventDefault();

                                    if (canAddAttachment) {
                                        setIsDragging(true);
                                    }
                                }}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                }}
                                onDragLeave={(event) => {
                                    event.preventDefault();
                                    setIsDragging(false);
                                }}
                                onDrop={handleUploadDrop}
                            >
                                <div className="space-y-2">
                                    <div className="mx-auto flex size-10 items-center justify-center rounded-md bg-background shadow-xs">
                                        <UploadCloud className="size-5" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium">
                                            ファイルを追加
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            PDF / 画像 / 動画 / 音声 / Office /
                                            ZIP
                                        </p>
                                    </div>
                                </div>
                                <input
                                    ref={fileInputRef}
                                    className="hidden"
                                    type="file"
                                    multiple
                                    accept={accept}
                                    disabled={!canAddAttachment}
                                    onChange={(event) => {
                                        void uploadSelectedFiles(
                                            Array.from(
                                                event.currentTarget.files ?? [],
                                            ),
                                        );
                                        event.currentTarget.value = '';
                                    }}
                                />
                            </div>

                            {recordingState === 'recording' ? (
                                <div className="grid min-w-40 grid-cols-[1fr_auto] gap-2 lg:self-start">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() => stopRecording(true)}
                                    >
                                        <Square className="size-4" />
                                        {formatTimer(recordingSeconds)}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="録音を破棄"
                                        onClick={() => stopRecording(false)}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-w-40 lg:self-start"
                                    disabled={!canRecord}
                                    onClick={() => {
                                        setPanelError(null);
                                        void startRecording();
                                    }}
                                >
                                    <Mic className="size-4" />
                                    {recordingState === 'saving'
                                        ? '保存中'
                                        : '録音'}
                                </Button>
                            )}
                        </div>
                    )}

                    {(isCreatingDraft || uploadHttp.processing) && (
                        <div className="space-y-2">
                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary transition-all"
                                    style={{
                                        width: `${uploadHttp.progress?.percentage ?? 35}%`,
                                    }}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {isCreatingDraft
                                    ? '下書きを作成中'
                                    : 'アップロード中'}
                            </p>
                        </div>
                    )}

                    {(panelError || uploadError || deleteError) && (
                        <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                            <AlertCircle className="mt-0.5 size-4 shrink-0" />
                            <p>{panelError ?? uploadError ?? deleteError}</p>
                        </div>
                    )}

                    {attachments.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                            添付資料はありません。
                        </div>
                    ) : (
                        <div className="grid gap-3">
                            {attachments.map((attachment) => (
                                <AttachmentListItem
                                    key={attachment.id}
                                    attachment={attachment}
                                    canUpdate={canUpdate}
                                    deleteDisabled={deleteHttp.processing}
                                    onDelete={(item) =>
                                        void deleteAttachment(item)
                                    }
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
            {dialog}
        </>
    );
}
