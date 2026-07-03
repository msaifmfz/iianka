import { Head, Link, router, useHttp } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, RotateCcw, Save, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    destroyDraft,
    submit as receptionCaseSubmit,
} from '@/actions/App/Http/Controllers/ReceptionCaseController';
import {
    store as storeDraft,
    update as updateDraft,
} from '@/actions/App/Http/Controllers/ReceptionCaseDraftController';
import { index as receptionHome } from '@/actions/App/Http/Controllers/ReceptionHomeController';
import FormField from '@/components/form-field';
import ReceptionAttachmentPanel from '@/components/reception-attachment-panel';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { useReceptionMeta } from '@/lib/reception';
import type {
    ReceptionAttachmentConstraints,
    ReceptionCasePriority,
    ReceptionDocumentType,
} from '@/types';

type Props = {
    documentTypes: ReceptionDocumentType[];
    attachmentConstraints: ReceptionAttachmentConstraints;
};

type DraftForm = {
    priority: ReceptionCasePriority;
    company_name: string;
    site_name: string;
    reception_document_type_id: string;
    reception_content: string;
    due_on: string;
    scheduled_on: string;
    media_only: boolean;
};

type StoreDraftResponse = {
    id: number;
    case_number: string;
};

function hasMeaningfulInput(data: DraftForm): boolean {
    return (
        data.priority !== 'normal' ||
        data.company_name.trim() !== '' ||
        data.site_name.trim() !== '' ||
        data.reception_document_type_id.trim() !== '' ||
        data.reception_content.trim() !== '' ||
        data.due_on.trim() !== '' ||
        data.scheduled_on.trim() !== ''
    );
}

function isReadyToSubmit(data: DraftForm, draftId: number | null): boolean {
    return (
        draftId !== null &&
        data.company_name.trim() !== '' &&
        data.site_name.trim() !== '' &&
        data.reception_document_type_id.trim() !== '' &&
        data.reception_content.trim() !== '' &&
        data.due_on.trim() !== ''
    );
}

export default function ReceptionCaseForm({
    documentTypes,
    attachmentConstraints,
}: Props) {
    const { priorityOptions } = useReceptionMeta();
    const { confirm, dialog } = useConfirmDialog();
    const http = useHttp<DraftForm, StoreDraftResponse>({
        priority: 'normal',
        company_name: '',
        site_name: '',
        reception_document_type_id: '',
        reception_content: '',
        due_on: '',
        scheduled_on: '',
        media_only: false,
    });
    const [draftId, setDraftId] = useState<number | null>(null);
    const [caseNumber, setCaseNumber] = useState<string | null>(null);
    const [submitErrors, setSubmitErrors] = useState<
        Partial<Record<keyof DraftForm, string>>
    >({});
    const [saveState, setSaveState] = useState<
        'idle' | 'saving' | 'saved' | 'failed'
    >('idle');
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const timerRef = useRef<number | null>(null);
    const lastSavedPayloadRef = useRef<string>('');
    const draftIdRef = useRef<number | null>(null);
    const pendingCreateRef = useRef<Promise<number | null> | null>(null);

    const payload = useMemo(() => JSON.stringify(http.data), [http.data]);
    const canSubmit = isReadyToSubmit(http.data, draftId);

    useEffect(() => {
        draftIdRef.current = draftId;
    }, [draftId]);

    // Single guarded create path shared by autosave and the attachment panel, so
    // concurrent callers reuse one in-flight request instead of creating two drafts.
    const createDraft = useCallback(
        (mediaOnly: boolean): Promise<number | null> => {
            if (draftIdRef.current !== null) {
                return Promise.resolve(draftIdRef.current);
            }

            if (pendingCreateRef.current !== null) {
                return pendingCreateRef.current;
            }

            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
            }

            setSaveState('saving');

            const request = (async (): Promise<number | null> => {
                try {
                    http.transform((data) => ({
                        ...data,
                        media_only: mediaOnly,
                    }));

                    const response = await http.post(storeDraft.url());

                    draftIdRef.current = response.id;
                    setDraftId(response.id);
                    setCaseNumber(response.case_number);
                    lastSavedPayloadRef.current = payload;
                    setSavedAt(new Date().toISOString());
                    setSaveState('saved');

                    return response.id;
                } catch {
                    setSaveState('failed');

                    return null;
                } finally {
                    http.transform((data) => ({ ...data, media_only: false }));
                    pendingCreateRef.current = null;
                }
            })();

            pendingCreateRef.current = request;

            return request;
        },
        [http, payload],
    );

    useEffect(() => {
        if (timerRef.current !== null) {
            window.clearTimeout(timerRef.current);
        }

        if (!hasMeaningfulInput(http.data)) {
            return;
        }

        if (payload === lastSavedPayloadRef.current || http.processing) {
            return;
        }

        timerRef.current = window.setTimeout(() => {
            const currentDraftId = draftIdRef.current;

            if (currentDraftId === null) {
                // Route creation through the guarded helper so an in-flight
                // create (e.g. triggered by an attachment) is never duplicated.
                void createDraft(false);

                return;
            }

            setSaveState('saving');

            http.patch(updateDraft.url(currentDraftId))
                .then(() => {
                    lastSavedPayloadRef.current = payload;
                    setSavedAt(new Date().toISOString());
                    setSaveState('saved');
                })
                .catch(() => {
                    setSaveState('failed');
                });
        }, 800);

        return () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
            }
        };
    }, [createDraft, http, http.data, http.processing, payload]);

    function submit() {
        if (!canSubmit || draftId === null) {
            return;
        }

        router.post(receptionCaseSubmit.url(draftId), http.data, {
            preserveScroll: 'errors',
            preserveState: 'errors',
            onError: (errors) => setSubmitErrors(errors),
            onSuccess: () => setSubmitErrors({}),
        });
    }

    function createDraftForAttachments(): Promise<number | null> {
        return createDraft(true);
    }

    async function discardDraft() {
        if (draftId === null) {
            return;
        }

        const confirmed = await confirm({
            title: 'この受付下書きを削除しますか？',
            confirmLabel: '削除',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(destroyDraft.url(draftId));
    }

    return (
        <>
            <Head title="受付画面" />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Reception Intake
                        </p>
                        <h1 className="text-2xl font-bold">受付画面</h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={receptionHome()}>
                            <ArrowLeft className="size-4" />
                            受付ホーム
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>受付内容</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        label="会社名"
                                        required
                                        error={
                                            http.errors.company_name ??
                                            submitErrors.company_name
                                        }
                                    >
                                        <Input
                                            value={http.data.company_name}
                                            onChange={(event) =>
                                                http.setData(
                                                    'company_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="現場名"
                                        required
                                        error={
                                            http.errors.site_name ??
                                            submitErrors.site_name
                                        }
                                    >
                                        <Input
                                            value={http.data.site_name}
                                            onChange={(event) =>
                                                http.setData(
                                                    'site_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </div>

                                <FormField
                                    label="案件書類"
                                    required
                                    error={
                                        http.errors
                                            .reception_document_type_id ??
                                        submitErrors.reception_document_type_id
                                    }
                                >
                                    <NativeSelect
                                        value={
                                            http.data.reception_document_type_id
                                        }
                                        onChange={(event) =>
                                            http.setData(
                                                'reception_document_type_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            選択してください
                                        </option>
                                        {documentTypes.map((documentType) => (
                                            <option
                                                key={documentType.id}
                                                value={documentType.id}
                                            >
                                                {documentType.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>

                                <FormField
                                    label="優先度"
                                    error={
                                        http.errors.priority ??
                                        submitErrors.priority
                                    }
                                >
                                    <NativeSelect
                                        value={http.data.priority}
                                        onChange={(event) =>
                                            http.setData(
                                                'priority',
                                                event.target
                                                    .value as ReceptionCasePriority,
                                            )
                                        }
                                    >
                                        {priorityOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>

                                <FormField
                                    label="受付内容"
                                    required
                                    error={
                                        http.errors.reception_content ??
                                        submitErrors.reception_content
                                    }
                                >
                                    <Textarea
                                        className="min-h-36"
                                        value={http.data.reception_content}
                                        onChange={(event) =>
                                            http.setData(
                                                'reception_content',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        label="期限"
                                        required
                                        error={
                                            http.errors.due_on ??
                                            submitErrors.due_on
                                        }
                                    >
                                        <Input
                                            type="date"
                                            value={http.data.due_on}
                                            onChange={(event) =>
                                                http.setData(
                                                    'due_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="予定日"
                                        error={
                                            http.errors.scheduled_on ??
                                            submitErrors.scheduled_on
                                        }
                                    >
                                        <Input
                                            type="date"
                                            value={http.data.scheduled_on}
                                            onChange={(event) =>
                                                http.setData(
                                                    'scheduled_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </div>
                            </CardContent>
                        </Card>

                        <ReceptionAttachmentPanel
                            caseId={draftId}
                            initialAttachments={[]}
                            canUpdate
                            constraints={attachmentConstraints}
                            onCreateDraft={createDraftForAttachments}
                        />
                    </div>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>保存状態</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground">
                                        案件ID
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {caseNumber ?? '未発行'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {saveState === 'saving' && (
                                        <Save className="size-4 animate-pulse text-amber-600" />
                                    )}
                                    {saveState === 'saved' && (
                                        <CheckCircle2 className="size-4 text-emerald-600" />
                                    )}
                                    {saveState === 'failed' && (
                                        <RotateCcw className="size-4 text-destructive" />
                                    )}
                                    <span>
                                        {saveState === 'idle' &&
                                            '入力すると自動保存します'}
                                        {saveState === 'saving' && '保存中'}
                                        {saveState === 'saved' &&
                                            `保存済み ${savedAt ? new Intl.DateTimeFormat('ja-JP', { hour: '2-digit', minute: '2-digit' }).format(new Date(savedAt)) : ''}`}
                                        {saveState === 'failed' &&
                                            '保存に失敗しました'}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={!canSubmit}
                                    onClick={submit}
                                >
                                    受付完了
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    disabled={draftId === null}
                                    onClick={() => void discardDraft()}
                                >
                                    <Trash2 className="size-4" />
                                    下書きを削除
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            {dialog}
        </>
    );
}
