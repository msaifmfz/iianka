import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    Handshake,
    Play,
    Save,
    Trash2,
    UserRound,
} from 'lucide-react';
import { index as receptionArchiveIndex } from '@/actions/App/Http/Controllers/ReceptionArchiveController';
import {
    assign as receptionAssign,
    start as receptionStart,
} from '@/actions/App/Http/Controllers/ReceptionCaseAssignmentController';
import receptionComplete from '@/actions/App/Http/Controllers/ReceptionCaseCompletionController';
import {
    destroyDraft,
    index as receptionCasesIndex,
    submit as receptionCaseSubmit,
    update as receptionCaseUpdate,
} from '@/actions/App/Http/Controllers/ReceptionCaseController';
import receptionHandover from '@/actions/App/Http/Controllers/ReceptionCaseHandoverController';
import receptionWorkMemo from '@/actions/App/Http/Controllers/ReceptionCaseWorkMemoController';
import { index as receptionHome } from '@/actions/App/Http/Controllers/ReceptionHomeController';
import FormField from '@/components/form-field';
import ReceptionAttachmentPanel from '@/components/reception-attachment-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    formatReceptionDateTime,
    ReceptionPriorityBadge,
    ReceptionStatusBadge,
    useReceptionMeta,
} from '@/lib/reception';
import type {
    ReceptionAttachmentConstraints,
    ReceptionCase,
    ReceptionCaseActivity,
    ReceptionCasePriority,
    ReceptionDocumentType,
    ReceptionUser,
} from '@/types';
import type { QueryParams } from '@/wayfinder';

type Props = {
    caseData: ReceptionCase;
    documentTypes: ReceptionDocumentType[];
    assigneeOptions: ReceptionUser[];
    attachmentConstraints: ReceptionAttachmentConstraints;
    returnTo: ReturnTo;
};

type ReturnTo =
    | { source: 'cases' }
    | { source: 'home' }
    | {
          source: 'archive';
          filters: {
              keyword: string;
              completed_from: string;
              completed_to: string;
          };
      };

type CaseForm = {
    priority: ReceptionCasePriority;
    company_name: string;
    site_name: string;
    reception_document_type_id: string;
    reception_content: string;
    due_on: string;
    scheduled_on: string;
};

const activityLabels: Record<ReceptionCaseActivity['type'], string> = {
    created_draft: '下書き作成',
    submitted: '受付完了',
    updated: '内容更新',
    assigned: '担当設定',
    started: '対応開始',
    handover_requested: '引継ぎ依頼',
    completed: '完了',
    attachment_added: '添付追加',
    attachment_deleted: '添付削除',
};

function assignedUserActivityLabel(
    activity: ReceptionCaseActivity,
): string | null {
    const fromId = activity.from_assigned_user?.id ?? null;
    const toId = activity.to_assigned_user?.id ?? null;
    const fromName = activity.from_assigned_user?.name;
    const toName = activity.to_assigned_user?.name;

    if (fromId === toId || (!fromName && !toName)) {
        return null;
    }

    return `${fromName ?? '未設定'} → ${toName ?? '未設定'}`;
}

function Timeline({ activities }: { activities: ReceptionCaseActivity[] }) {
    const { statusLabels } = useReceptionMeta();

    return (
        <Card>
            <CardHeader>
                <CardTitle>活動履歴</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {activities.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                        活動履歴はありません。
                    </div>
                ) : (
                    activities.map((activity) => {
                        const assignedUserLabel =
                            assignedUserActivityLabel(activity);
                        const hasStatusChange =
                            activity.from_status !== activity.to_status &&
                            (activity.from_status !== null ||
                                activity.to_status !== null);

                        return (
                            <div
                                key={activity.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary">
                                        {activityLabels[activity.type]}
                                    </Badge>
                                    <span className="text-sm text-muted-foreground">
                                        {activity.user?.name ?? '不明'} /{' '}
                                        {formatReceptionDateTime(
                                            activity.created_at,
                                        )}
                                    </span>
                                </div>
                                {hasStatusChange && (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {activity.from_status
                                            ? statusLabels[activity.from_status]
                                            : '-'}{' '}
                                        →{' '}
                                        {activity.to_status
                                            ? statusLabels[activity.to_status]
                                            : '-'}
                                    </p>
                                )}
                                {assignedUserLabel && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        担当: {assignedUserLabel}
                                    </p>
                                )}
                                {activity.memo && (
                                    <p className="mt-3 rounded-md bg-muted p-3 text-sm whitespace-pre-wrap">
                                        {activity.memo}
                                    </p>
                                )}
                            </div>
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

function AssignmentPanel({
    caseData,
    assigneeOptions,
}: {
    caseData: ReceptionCase;
    assigneeOptions: ReceptionUser[];
}) {
    const assignForm = useForm({
        assigned_user_id: caseData.assigned_user?.id.toString() ?? '',
        memo: '',
    });

    function assign(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        assignForm.patch(receptionAssign.url(caseData.id), {
            preserveScroll: true,
        });
    }

    function start() {
        router.patch(
            receptionStart.url(caseData.id),
            {},
            {
                preserveScroll: true,
            },
        );
    }

    if (!caseData.can.assign && !caseData.can.start) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>担当者</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {caseData.can.assign && (
                    <form onSubmit={assign} className="grid gap-3">
                        <FormField
                            label="担当者"
                            required
                            error={assignForm.errors.assigned_user_id}
                        >
                            <NativeSelect
                                value={assignForm.data.assigned_user_id}
                                onChange={(event) =>
                                    assignForm.setData(
                                        'assigned_user_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">選択してください</option>
                                {assigneeOptions.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label="担当者へのメモ"
                            error={assignForm.errors.memo}
                        >
                            <Textarea
                                value={assignForm.data.memo}
                                onChange={(event) =>
                                    assignForm.setData(
                                        'memo',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <Button type="submit" disabled={assignForm.processing}>
                            <UserRound className="size-4" />
                            担当者を保存
                        </Button>
                    </form>
                )}
                {caseData.can.start && (
                    <Button
                        type="button"
                        className="w-full"
                        disabled={caseData.assigned_user === null}
                        onClick={start}
                    >
                        <Play className="size-4" />
                        対応開始
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function TaskActionPanel({ caseData }: { caseData: ReceptionCase }) {
    const workMemoForm = useForm({ work_memo: caseData.work_memo ?? '' });
    const completeForm = useForm({});
    const handoverForm = useForm({ memo: '' });
    const { confirm, dialog } = useConfirmDialog();
    const showWorkMemo =
        caseData.can.update_work_memo || caseData.work_memo !== null;

    function saveWorkMemo(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        workMemoForm.patch(receptionWorkMemo.url(caseData.id), {
            preserveScroll: true,
        });
    }

    async function complete(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const confirmed = await confirm({
            title: 'このやることを完了しますか？',
            confirmLabel: '完了する',
        });

        if (!confirmed) {
            return;
        }

        completeForm.post(receptionComplete.url(caseData.id), {
            preserveScroll: true,
        });
    }

    async function handover(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const confirmed = await confirm({
            title: 'このやることを引継ぎますか？',
            confirmLabel: '引継ぎする',
        });

        if (!confirmed) {
            return;
        }

        handoverForm.post(receptionHandover.url(caseData.id), {
            preserveScroll: true,
        });
    }

    if (!caseData.can.complete && !caseData.can.handover && !showWorkMemo) {
        return null;
    }

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>やること</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-2">
                    {showWorkMemo && (
                        <form
                            onSubmit={saveWorkMemo}
                            className="grid gap-3 lg:col-span-2"
                        >
                            <FormField
                                label="作業メモ"
                                error={workMemoForm.errors.work_memo}
                            >
                                {caseData.can.update_work_memo ? (
                                    <Textarea
                                        className="min-h-28"
                                        value={workMemoForm.data.work_memo}
                                        onChange={(event) =>
                                            workMemoForm.setData(
                                                'work_memo',
                                                event.target.value,
                                            )
                                        }
                                    />
                                ) : (
                                    <div className="min-h-20 rounded-md border bg-muted/30 p-3 text-sm whitespace-pre-wrap">
                                        {caseData.work_memo}
                                    </div>
                                )}
                            </FormField>
                            {caseData.can.update_work_memo && (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={workMemoForm.processing}
                                >
                                    <Save className="size-4" />
                                    作業メモを保存
                                </Button>
                            )}
                        </form>
                    )}
                    {caseData.can.complete && (
                        <form onSubmit={complete} className="grid gap-3">
                            <Button
                                type="submit"
                                disabled={completeForm.processing}
                            >
                                <CheckCircle2 className="size-4" />
                                やることを完了した
                            </Button>
                        </form>
                    )}
                    {caseData.can.handover && (
                        <form onSubmit={handover} className="grid gap-3">
                            <FormField
                                label="引継ぎメモ"
                                required
                                error={handoverForm.errors.memo}
                            >
                                <Textarea
                                    className="min-h-28"
                                    value={handoverForm.data.memo}
                                    onChange={(event) =>
                                        handoverForm.setData(
                                            'memo',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={handoverForm.processing}
                            >
                                <Handshake className="size-4" />
                                引継ぎ
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>
            {dialog}
        </>
    );
}

function assignedUserLabel(caseData: ReceptionCase): string {
    if (caseData.assigned_user_handover_chain.length > 1) {
        return caseData.assigned_user_handover_chain
            .map((user) => user.name)
            .join(' → ');
    }

    return caseData.assigned_user?.name ?? '未設定';
}

function ReceptionInfoSummary({ caseData }: { caseData: ReceptionCase }) {
    return (
        <div className="grid gap-4 rounded-lg border bg-muted/30 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p className="text-muted-foreground">受付者</p>
                <p className="font-medium">
                    {caseData.receptor?.name ?? '未設定'}
                </p>
            </div>
            <div>
                <p className="text-muted-foreground">担当者</p>
                <p className="font-medium break-words">
                    {assignedUserLabel(caseData)}
                </p>
            </div>
            {caseData.completed_at && (
                <div>
                    <p className="text-muted-foreground">完了</p>
                    <p className="font-medium">
                        {formatReceptionDateTime(caseData.completed_at)}
                    </p>
                </div>
            )}
        </div>
    );
}

function compactArchiveFilters(
    filters: Extract<ReturnTo, { source: 'archive' }>['filters'],
): QueryParams {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );
}

export default function ReceptionCaseShow({
    caseData,
    documentTypes,
    assigneeOptions,
    attachmentConstraints,
    returnTo,
}: Props) {
    const { priorityOptions } = useReceptionMeta();
    const { confirm, dialog } = useConfirmDialog();
    const form = useForm<CaseForm>({
        priority: caseData.priority,
        company_name: caseData.company_name ?? '',
        site_name: caseData.site_name ?? '',
        reception_document_type_id:
            caseData.reception_document_type_id?.toString() ?? '',
        reception_content: caseData.reception_content ?? '',
        due_on: caseData.due_on ?? '',
        scheduled_on: caseData.scheduled_on ?? '',
    });
    const readOnly = !caseData.can.update;
    const backHref =
        returnTo.source === 'archive'
            ? receptionArchiveIndex({
                  query: compactArchiveFilters(returnTo.filters),
              })
            : returnTo.source === 'home'
              ? receptionHome()
              : receptionCasesIndex();
    const backLabel =
        returnTo.source === 'home' ? '受付ホームへ戻る' : '一覧へ戻る';
    const hasAssignmentPanel = caseData.can.assign || caseData.can.start;

    function update(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.patch(receptionCaseUpdate.url(caseData.id), {
            preserveScroll: true,
        });
    }

    function submitDraft() {
        form.post(receptionCaseSubmit.url(caseData.id), {
            preserveScroll: true,
        });
    }

    async function deleteDraft() {
        const confirmed = await confirm({
            title: 'この受付下書きを削除しますか？',
            confirmLabel: '削除',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(destroyDraft.url(caseData.id));
    }

    return (
        <>
            <Head title={`やることカード ${caseData.case_number}`} />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <ReceptionStatusBadge status={caseData.status} />
                            <ReceptionPriorityBadge
                                priority={caseData.priority}
                            />
                            {caseData.is_unseen && <Badge>新着/更新</Badge>}
                        </div>
                        <h1 className="text-2xl font-bold">
                            {caseData.case_number}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {caseData.company_name ?? '会社名未入力'} /{' '}
                            {caseData.site_name ?? '現場名未入力'}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={backHref}>
                            <ArrowLeft className="size-4" />
                            {backLabel}
                        </Link>
                    </Button>
                </div>

                <div
                    className={`grid gap-6 ${hasAssignmentPanel ? 'lg:grid-cols-[1fr_20rem]' : ''}`}
                >
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>受付情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <ReceptionInfoSummary caseData={caseData} />
                                <form onSubmit={update} className="grid gap-5">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <FormField
                                            label="会社名"
                                            required
                                            error={form.errors.company_name}
                                        >
                                            <Input
                                                disabled={readOnly}
                                                value={form.data.company_name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'company_name',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="現場名"
                                            required
                                            error={form.errors.site_name}
                                        >
                                            <Input
                                                disabled={readOnly}
                                                value={form.data.site_name}
                                                onChange={(event) =>
                                                    form.setData(
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
                                            form.errors
                                                .reception_document_type_id
                                        }
                                    >
                                        <NativeSelect
                                            disabled={readOnly}
                                            value={
                                                form.data
                                                    .reception_document_type_id
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'reception_document_type_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                選択してください
                                            </option>
                                            {documentTypes.map(
                                                (documentType) => (
                                                    <option
                                                        key={documentType.id}
                                                        value={documentType.id}
                                                    >
                                                        {documentType.name}
                                                        {!documentType.is_active
                                                            ? '（無効）'
                                                            : ''}
                                                    </option>
                                                ),
                                            )}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        label="優先度"
                                        error={form.errors.priority}
                                    >
                                        <NativeSelect
                                            disabled={
                                                !caseData.can.update_priority
                                            }
                                            value={form.data.priority}
                                            onChange={(event) =>
                                                form.setData(
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
                                        error={form.errors.reception_content}
                                    >
                                        <Textarea
                                            disabled={readOnly}
                                            className="min-h-36"
                                            value={form.data.reception_content}
                                            onChange={(event) =>
                                                form.setData(
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
                                            error={form.errors.due_on}
                                        >
                                            <Input
                                                disabled={readOnly}
                                                type="date"
                                                value={form.data.due_on}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'due_on',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="予定日"
                                            error={form.errors.scheduled_on}
                                        >
                                            <Input
                                                disabled={readOnly}
                                                type="date"
                                                value={form.data.scheduled_on}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'scheduled_on',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    {caseData.can.update && (
                                        <div className="flex flex-col gap-2 sm:flex-row">
                                            <Button
                                                type="submit"
                                                disabled={form.processing}
                                            >
                                                <Save className="size-4" />
                                                受付情報を更新
                                            </Button>
                                            {caseData.can.submit && (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={submitDraft}
                                                >
                                                    <ClipboardCheck className="size-4" />
                                                    受付完了
                                                </Button>
                                            )}
                                            {caseData.can.delete_draft && (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        void deleteDraft()
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    下書きを削除
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </form>
                            </CardContent>
                        </Card>

                        <ReceptionAttachmentPanel
                            caseId={caseData.id}
                            initialAttachments={caseData.attachments}
                            canUpdate={caseData.can.attach_files}
                            constraints={attachmentConstraints}
                        />

                        <TaskActionPanel caseData={caseData} />
                        <Timeline activities={caseData.activities} />
                    </div>

                    {hasAssignmentPanel && (
                        <div className="space-y-4">
                            <AssignmentPanel
                                caseData={caseData}
                                assigneeOptions={assigneeOptions}
                            />
                        </div>
                    )}
                </div>
            </div>
            {dialog}
        </>
    );
}
