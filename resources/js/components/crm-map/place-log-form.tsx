import { useForm, usePage } from '@inertiajs/react';
import { Camera, FileText, Mic, Plus, Square, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    store as logStore,
    update as logUpdate,
} from '@/actions/App/Http/Controllers/ClientPlaceLogController';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useAudioRecorder } from '@/hooks/use-audio-recorder';
import { toBusinessDateTimeLocal } from '@/lib/crm';
import { formatMinutesSeconds } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ClientContact,
    ClientPlaceLog,
    CrmAttachmentLimits,
    CrmOption,
    SelectedPlace,
} from '@/types';
import { HelpButton } from './action-help';
import { DocumentIcon } from './client-document-list';
import ContactCreateDialog from './contact-create-dialog';

type PendingAttachment = {
    key: string;
    file: File;
    durationSeconds: number | null;
    previewUrl: string | null;
};

type LogForm = {
    type: string;
    occurred_at: string;
    client_contact_id: string;
    reaction: string;
    summary: string;
    attachments: { file: File; duration_seconds: number | null }[];
};

const reactionStyles: Record<string, string> = {
    positive:
        'data-[active=true]:bg-emerald-600 data-[active=true]:text-white data-[active=true]:border-emerald-600',
    neutral:
        'data-[active=true]:bg-neutral-600 data-[active=true]:text-white data-[active=true]:border-neutral-600',
    negative:
        'data-[active=true]:bg-rose-600 data-[active=true]:text-white data-[active=true]:border-rose-600',
};

/**
 * Add or edit one history entry. Photos, voice memos and documents are
 * collected on the device and sent with the entry in a single request, so a
 * visit is logged in one tap even on a patchy connection.
 */
export default function PlaceLogForm({
    selected,
    log,
    logTypes,
    reactions,
    attachmentLimits,
    canManage,
    onDone,
}: {
    selected: SelectedPlace;
    log: ClientPlaceLog | null;
    logTypes: CrmOption[];
    reactions: CrmOption[];
    /** Server-owned ceilings, so the form never restates a PHP constant. */
    attachmentLimits: CrmAttachmentLimits;
    canManage: boolean;
    onDone: () => void;
}) {
    const {
        max_per_log: maxAttachments,
        max_file_bytes: maxFileBytes,
        max_recording_seconds: maxRecordingSeconds,
        image_extensions: photoExtensions,
        document_extensions: documentExtensions,
    } = attachmentLimits;
    const maxFileMegabytes = Math.floor(maxFileBytes / (1024 * 1024));
    const { auth } = usePage().props;
    const form = useForm<LogForm>({
        type: log?.type ?? logTypes[0]?.value ?? 'visit',
        occurred_at: toBusinessDateTimeLocal(log?.occurred_at ?? new Date()),
        client_contact_id: log?.contact ? String(log.contact.id) : '',
        reaction: log?.reaction ?? '',
        summary: log?.summary ?? '',
        attachments: [],
    });
    const [pending, setPending] = useState<PendingAttachment[]>([]);
    const pendingRef = useRef<PendingAttachment[]>([]);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const documentInputRef = useRef<HTMLInputElement>(null);
    const [attachmentError, setAttachmentError] = useState<string | null>(null);
    const [isAddingContact, setIsAddingContact] = useState(false);
    const [addedContacts, setAddedContacts] = useState<ClientContact[]>([]);
    const contacts = [
        ...selected.contacts,
        ...addedContacts.filter(
            (contact) =>
                !selected.contacts.some(
                    (existing) => existing.id === contact.id,
                ),
        ),
    ];
    const existingCount = log?.attachments.length ?? 0;
    const remainingSlots = maxAttachments - existingCount - pending.length;

    useEffect(
        () => () => {
            pendingRef.current.forEach((item) => {
                if (item.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                }
            });
        },
        [],
    );

    /**
     * `pickedAs` is which picker the files came from, so a document picked as
     * a photo (or the other way round) is caught before upload.
     */
    function addAttachments(
        files: File[],
        durationSeconds: number | null,
        pickedAs: 'photo' | 'document' | 'recording',
    ) {
        const next = [...pendingRef.current];
        let error: string | null = null;

        for (const file of files) {
            if (existingCount + next.length >= maxAttachments) {
                error = `添付は${maxAttachments}件までです。`;
                break;
            }

            if (file.size > maxFileBytes) {
                error = `添付ファイルは${maxFileMegabytes}MBまでです。`;
                continue;
            }

            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

            if (pickedAs === 'photo' && !photoExtensions.includes(extension)) {
                error =
                    '写真はJPEG・PNG・GIF・WebP・HEIC形式で選択してください。';
                continue;
            }

            if (
                pickedAs === 'document' &&
                !documentExtensions.includes(extension)
            ) {
                error =
                    '書類はPDF・Word・Excel・PowerPoint・テキスト・CSV形式で選択してください。';
                continue;
            }

            next.push({
                key: `${Date.now()}-${next.length}-${file.name}`,
                file,
                durationSeconds,
                previewUrl:
                    pickedAs === 'recording' ||
                    (pickedAs === 'photo' && file.type.startsWith('image/'))
                        ? URL.createObjectURL(file)
                        : null,
            });
        }

        pendingRef.current = next;
        setPending(next);
        setAttachmentError(error);
    }

    function removeAttachment(key: string) {
        const removed = pendingRef.current.find((item) => item.key === key);

        if (removed?.previewUrl) {
            URL.revokeObjectURL(removed.previewUrl);
        }

        pendingRef.current = pendingRef.current.filter(
            (item) => item.key !== key,
        );
        setPending(pendingRef.current);
        setAttachmentError(null);
    }

    const recorder = useAudioRecorder({
        maxRecordingSeconds,
        onSave: (file, durationSeconds) => {
            addAttachments([file], durationSeconds, 'recording');

            return Promise.resolve();
        },
        onError: setAttachmentError,
    });
    const isRecording = recorder.recordingState === 'recording';

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.processing || recorder.recordingState !== 'idle') {
            return;
        }

        // Multipart bodies need POST; Laravel reads the real verb from
        // `_method` when editing.
        form.transform((data) => ({
            ...data,
            ...(log ? { _method: 'patch' } : {}),
            attachments: pending.map((item) => ({
                file: item.file,
                duration_seconds: item.durationSeconds,
            })),
        }));

        form.post(
            log ? logUpdate.url(log.id) : logStore.url(selected.place.id),
            {
                preserveScroll: true,
                preserveState: true,
                forceFormData: true,
                only: ['selectedPlace', 'places'],
                onSuccess: () => {
                    pending.forEach(
                        (item) =>
                            item.previewUrl &&
                            URL.revokeObjectURL(item.previewUrl),
                    );
                    onDone();
                },
            },
        );
    }

    const attachmentErrors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('attachments'))
        .map(([, message]) => message);

    return (
        <>
            {isAddingContact && (
                <ContactCreateDialog
                    client={selected.client}
                    onClose={() => setIsAddingContact(false)}
                    onCreated={(contact) => {
                        setAddedContacts((current) => [...current, contact]);
                        form.setData('client_contact_id', String(contact.id));
                        setIsAddingContact(false);
                    }}
                />
            )}
            <form
                onSubmit={submit}
                className="grid gap-4 rounded-xl border bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div className="space-y-1 rounded-lg bg-background p-3 text-sm">
                    <p className="font-medium">
                        記録先: {log?.place.name ?? selected.place.name}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        担当者(社):{' '}
                        {log
                            ? (log.user?.name ?? '未登録・削除済み')
                            : auth.user.name}
                    </p>
                </div>
                <div className="grid gap-3">
                    <FormField label="種類" required error={form.errors.type}>
                        <NativeSelect
                            value={form.data.type}
                            onChange={(event) =>
                                form.setData('type', event.target.value)
                            }
                        >
                            {logTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label="日時"
                        required
                        error={form.errors.occurred_at}
                    >
                        <Input
                            type="datetime-local"
                            max={toBusinessDateTimeLocal(new Date())}
                            required
                            value={form.data.occurred_at}
                            onChange={(event) =>
                                form.setData('occurred_at', event.target.value)
                            }
                        />
                    </FormField>
                </div>

                <div className="grid gap-2">
                    <FormField
                        label="担当者(客)"
                        error={form.errors.client_contact_id}
                    >
                        <NativeSelect
                            value={form.data.client_contact_id}
                            onChange={(event) =>
                                form.setData(
                                    'client_contact_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">指定なし</option>
                            {contacts.map((contact) => (
                                <option key={contact.id} value={contact.id}>
                                    {contact.name}
                                    {contact.title
                                        ? `（${contact.title}）`
                                        : ''}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {canManage && (
                        <div>
                            <HelpButton
                                helpTitle="担当者を追加"
                                help="一覧にいない担当者を、この画面を離れずに登録できます。登録後は自動で選択されます。"
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => setIsAddingContact(true)}
                            >
                                <Plus className="size-4" />
                                担当者を追加
                            </HelpButton>
                        </div>
                    )}
                </div>

                <FormField
                    as="div"
                    label="顧客の反応"
                    error={form.errors.reaction}
                >
                    <div className="flex flex-wrap gap-2">
                        {reactions.map((reaction) => {
                            const isActive =
                                form.data.reaction === reaction.value;

                            return (
                                <HelpButton
                                    helpTitle={reaction.label}
                                    help={
                                        {
                                            positive:
                                                '前向きな返答や、次の商談につながりそうな反応です。もう一度押すと選択を解除します。',
                                            neutral:
                                                '特に良くも悪くもない、通常の反応です。もう一度押すと選択を解除します。',
                                            negative:
                                                '懸念・不満・難色があり、次回の対応で注意したい反応です。もう一度押すと選択を解除します。',
                                        }[reaction.value] ??
                                        '顧客の反応を選びます。'
                                    }
                                    key={reaction.value}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    data-active={isActive}
                                    aria-pressed={isActive}
                                    className={reactionStyles[reaction.value]}
                                    onClick={() =>
                                        form.setData(
                                            'reaction',
                                            isActive ? '' : reaction.value,
                                        )
                                    }
                                >
                                    <span aria-hidden="true">
                                        {
                                            {
                                                positive: '😊',
                                                neutral: '😐',
                                                negative: '😟',
                                            }[reaction.value]
                                        }
                                    </span>
                                    {reaction.label}
                                </HelpButton>
                            );
                        })}
                    </div>
                </FormField>

                <FormField label="内容" required error={form.errors.summary}>
                    <Textarea
                        required
                        maxLength={5000}
                        rows={5}
                        placeholder="何をしたか、どんな話をしたか、次に何をすべきか"
                        value={form.data.summary}
                        onChange={(event) =>
                            form.setData('summary', event.target.value)
                        }
                    />
                </FormField>

                <div className="grid gap-2 text-sm font-medium">
                    <span>写真・音声メモ・書類</span>
                    <div className="flex flex-wrap gap-2">
                        <HelpButton
                            helpTitle="写真"
                            help={`端末の写真を選んで添付します。写真・音声・書類を合わせて${maxAttachments}件、1件${maxFileMegabytes}MBまで。「記録する」でまとめて保存します。`}
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                remainingSlots <= 0 ||
                                form.processing ||
                                recorder.recordingState !== 'idle'
                            }
                            onClick={() => photoInputRef.current?.click()}
                        >
                            <Camera className="size-4" />
                            写真
                        </HelpButton>
                        <input
                            ref={photoInputRef}
                            type="file"
                            accept={photoExtensions
                                .map((extension) => `.${extension}`)
                                .join(',')}
                            multiple
                            hidden
                            disabled={
                                remainingSlots <= 0 ||
                                form.processing ||
                                recorder.recordingState !== 'idle'
                            }
                            onChange={(event) => {
                                addAttachments(
                                    Array.from(event.currentTarget.files ?? []),
                                    null,
                                    'photo',
                                );
                                event.currentTarget.value = '';
                            }}
                        />
                        <HelpButton
                            helpTitle="書類"
                            help={`見積書・図面・議事録などのPDF・Word・Excel・PowerPoint・テキスト・CSVを添付します。写真・音声・書類を合わせて${maxAttachments}件、1件${maxFileMegabytes}MBまで。`}
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={
                                remainingSlots <= 0 ||
                                form.processing ||
                                recorder.recordingState !== 'idle'
                            }
                            onClick={() => documentInputRef.current?.click()}
                        >
                            <FileText className="size-4" />
                            書類
                        </HelpButton>
                        <input
                            ref={documentInputRef}
                            type="file"
                            accept={documentExtensions
                                .map((extension) => `.${extension}`)
                                .join(',')}
                            multiple
                            hidden
                            disabled={
                                remainingSlots <= 0 ||
                                form.processing ||
                                recorder.recordingState !== 'idle'
                            }
                            onChange={(event) => {
                                addAttachments(
                                    Array.from(event.currentTarget.files ?? []),
                                    null,
                                    'document',
                                );
                                event.currentTarget.value = '';
                            }}
                        />
                        {isRecording ? (
                            <HelpButton
                                helpTitle="録音を停止"
                                help="録音を終了し、この記録の添付として準備します。再生して確認したあと「記録する」を押すと、音声も一緒にアップロードされます。"
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={() => recorder.stopRecording(true)}
                            >
                                <Square className="size-4" />
                                停止{' '}
                                {formatMinutesSeconds(
                                    recorder.recordingSeconds,
                                )}
                            </HelpButton>
                        ) : (
                            <HelpButton
                                helpTitle="録音"
                                help={`マイクの使用を許可すると録音が始まります（最大${Math.round(maxRecordingSeconds / 60)}分）。「停止」で添付し、「記録する」で内容と一緒に保存してください。`}
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={
                                    remainingSlots <= 0 ||
                                    form.processing ||
                                    recorder.recordingState !== 'idle'
                                }
                                onClick={() => void recorder.startRecording()}
                            >
                                <Mic className="size-4" />
                                {recorder.recordingState === 'requesting'
                                    ? 'マイクを確認中...'
                                    : recorder.recordingState === 'saving'
                                      ? '音声を準備中...'
                                      : '録音'}
                            </HelpButton>
                        )}
                    </div>
                    <p
                        role="status"
                        className="text-xs font-normal text-muted-foreground"
                    >
                        {isRecording
                            ? '録音中です。「停止」を押すと確認できます。'
                            : recorder.recordingState === 'requesting'
                              ? 'ブラウザのマイク使用許可を確認してください。'
                              : recorder.recordingState === 'saving'
                                ? '音声を準備しています…'
                                : pending.length > 0
                                  ? '添付はまだ保存されていません。「記録する」でまとめて保存します。'
                                  : '録音・写真・書類は記録と一緒に保存されます。'}
                    </p>
                    {pending.length > 0 && (
                        <ul className="flex flex-wrap gap-2">
                            {pending.map((item) => (
                                <li
                                    key={item.key}
                                    className="relative flex h-16 items-center gap-2 overflow-hidden rounded-lg border bg-white pr-8 text-xs font-normal dark:border-neutral-800 dark:bg-neutral-950"
                                >
                                    {item.durationSeconds !== null &&
                                    item.previewUrl ? (
                                        <audio
                                            aria-label="録音の確認"
                                            controls
                                            preload="metadata"
                                            src={item.previewUrl}
                                            className="h-9 w-48 max-w-full"
                                        />
                                    ) : item.previewUrl ? (
                                        <img
                                            src={item.previewUrl}
                                            alt=""
                                            className="h-16 w-16 object-cover"
                                        />
                                    ) : item.durationSeconds !== null ? (
                                        <span className="flex items-center gap-1 pl-3">
                                            <Mic className="size-4" />
                                            {formatMinutesSeconds(
                                                item.durationSeconds,
                                            )}
                                        </span>
                                    ) : (
                                        <span className="flex max-w-48 items-center gap-1 pl-3">
                                            <DocumentIcon
                                                extension={
                                                    item.file.name
                                                        .split('.')
                                                        .pop() ?? null
                                                }
                                            />
                                            <span className="truncate">
                                                {item.file.name}
                                            </span>
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        aria-label="添付を外す"
                                        className="absolute top-1 right-1 flex size-7 items-center justify-center rounded-full bg-black/60 text-white"
                                        disabled={form.processing}
                                        onClick={() =>
                                            removeAttachment(item.key)
                                        }
                                    >
                                        <X className="size-3" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {[attachmentError, ...attachmentErrors]
                        .filter(Boolean)
                        .map((message) => (
                            <span
                                key={message}
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {message}
                            </span>
                        ))}
                </div>

                {form.progress && (
                    <progress
                        value={form.progress.percentage}
                        max="100"
                        className="w-full"
                    />
                )}

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={onDone}
                    >
                        キャンセル
                    </Button>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            recorder.recordingState !== 'idle'
                        }
                        className={cn(form.processing && 'opacity-70')}
                    >
                        {form.processing
                            ? '保存中...'
                            : log
                              ? '更新'
                              : '記録する'}
                    </Button>
                </div>
            </form>
        </>
    );
}
