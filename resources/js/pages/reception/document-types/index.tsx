import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Plus, RotateCcw, Save } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import {
    store as documentTypeStore,
    update as documentTypeUpdate,
} from '@/actions/App/Http/Controllers/ReceptionDocumentTypeController';
import documentTypeOrderUpdate from '@/actions/App/Http/Controllers/ReceptionDocumentTypeOrderController';
import FormField from '@/components/form-field';
import SortableOrderList from '@/components/sortable-order-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ReceptionDocumentType } from '@/types';

type Props = {
    documentTypes: ReceptionDocumentType[];
};

type DocumentTypeForm = {
    name: string;
    is_active: boolean;
};

function DocumentTypeRow({
    dragHandle,
    documentType,
    isDragging,
    position,
}: {
    dragHandle: ReactNode;
    documentType: ReceptionDocumentType;
    isDragging: boolean;
    position: number;
}) {
    const form = useForm<DocumentTypeForm>({
        name: documentType.name,
        is_active: documentType.is_active,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.patch(documentTypeUpdate.url(documentType.id), {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={submit}
            className={cn(
                'grid gap-3 rounded-lg border bg-background p-4 transition-shadow lg:grid-cols-[auto_minmax(0,1fr)_10rem_auto]',
                isDragging && 'shadow-lg ring-2 ring-ring/20',
            )}
        >
            <div className="flex items-end gap-2 lg:items-center">
                {dragHandle}
                <Badge
                    variant="outline"
                    className="h-9 min-w-11 justify-center"
                >
                    #{position}
                </Badge>
            </div>
            <FormField label="案件書類名" error={form.errors.name}>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </FormField>
            <div className="grid gap-2 text-sm font-medium">
                <span>状態</span>
                <label className="flex h-9 items-center gap-2 rounded-md border px-3">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData('is_active', event.target.checked)
                        }
                    />
                    有効
                </label>
            </div>
            <div className="flex items-end gap-2">
                <Badge variant={form.data.is_active ? 'secondary' : 'outline'}>
                    {form.data.is_active ? '有効' : '無効'}
                </Badge>
                {documentType.reception_cases_count != null && (
                    <Badge variant="outline">
                        使用 {documentType.reception_cases_count}件
                    </Badge>
                )}
                <Button type="submit" disabled={form.processing}>
                    <Save className="size-4" />
                    保存
                </Button>
            </div>
        </form>
    );
}

function documentTypeIds(documentTypes: ReceptionDocumentType[]): number[] {
    return documentTypes.map((documentType) => documentType.id);
}

function idsAreEqual(firstIds: number[], secondIds: number[]): boolean {
    return (
        firstIds.length === secondIds.length &&
        firstIds.every((id, index) => id === secondIds[index])
    );
}

function getDocumentTypeId(documentType: ReceptionDocumentType): number {
    return documentType.id;
}

function getDocumentTypeLabel(documentType: ReceptionDocumentType): string {
    return documentType.name;
}

function normalizeOrderedIds(
    pendingOrderedIds: number[] | null,
    serverIds: number[],
): number[] {
    if (pendingOrderedIds === null) {
        return serverIds;
    }

    const serverIdSet = new Set(serverIds);
    const preservedIds = pendingOrderedIds.filter((id) => serverIdSet.has(id));
    const preservedIdSet = new Set(preservedIds);
    const addedIds = serverIds.filter((id) => !preservedIdSet.has(id));

    return [...preservedIds, ...addedIds];
}

function orderDocumentTypesByIds(
    documentTypes: ReceptionDocumentType[],
    orderedIds: number[],
): ReceptionDocumentType[] {
    const documentTypesById = new Map(
        documentTypes.map((documentType) => [documentType.id, documentType]),
    );

    return orderedIds.flatMap((id) => {
        const documentType = documentTypesById.get(id);

        return documentType ? [documentType] : [];
    });
}

function orderErrorMessage(errors: Record<string, string>): string {
    if (errors.ordered_ids) {
        return errors.ordered_ids;
    }

    const elementError = Object.entries(errors).find(([key]) =>
        key.startsWith('ordered_ids.'),
    );

    return elementError?.[1] ?? '表示順を保存できませんでした。';
}

export default function ReceptionDocumentTypesIndex({ documentTypes }: Props) {
    const form = useForm<DocumentTypeForm>({
        name: '',
        is_active: true,
    });
    const [pendingOrderedIds, setPendingOrderedIds] = useState<number[] | null>(
        null,
    );
    const [isSavingOrder, setIsSavingOrder] = useState(false);
    const [orderError, setOrderError] = useState<string | null>(null);
    const serverIds = useMemo(
        () => documentTypeIds(documentTypes),
        [documentTypes],
    );
    const orderedIds = useMemo(
        () => normalizeOrderedIds(pendingOrderedIds, serverIds),
        [pendingOrderedIds, serverIds],
    );
    const orderedDocumentTypes = useMemo(
        () => orderDocumentTypesByIds(documentTypes, orderedIds),
        [documentTypes, orderedIds],
    );
    const isOrderDirty = !idsAreEqual(orderedIds, serverIds);

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(documentTypeStore.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    }

    function resetOrder() {
        setOrderError(null);
        setPendingOrderedIds(null);
    }

    function saveOrder() {
        if (!isOrderDirty) {
            return;
        }

        setIsSavingOrder(true);
        setOrderError(null);

        router.patch(
            documentTypeOrderUpdate.url(),
            {
                ordered_ids: orderedIds,
            },
            {
                only: ['documentTypes'],
                preserveScroll: true,
                onError: (errors) => setOrderError(orderErrorMessage(errors)),
                onFinish: () => setIsSavingOrder(false),
                onSuccess: () => {
                    setOrderError(null);
                    setPendingOrderedIds(null);
                },
            },
        );
    }

    return (
        <>
            <Head title="案件書類マスター" />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Reception Document Types
                    </p>
                    <h1 className="text-2xl font-bold">案件書類マスター</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-5" />
                            新規追加
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-3 lg:grid-cols-[1fr_10rem_auto]"
                        >
                            <FormField
                                label="案件書類名"
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            </FormField>
                            <div className="grid gap-2 text-sm font-medium">
                                <span>状態</span>
                                <label className="flex h-9 items-center gap-2 rounded-md border px-3">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(event) =>
                                            form.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    有効
                                </label>
                            </div>
                            <div className="flex items-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <FileText className="size-4" />
                                    追加
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>登録済み</CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                            {isOrderDirty && (
                                <Badge variant="outline">表示順 未保存</Badge>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={resetOrder}
                                disabled={!isOrderDirty || isSavingOrder}
                            >
                                <RotateCcw className="size-4" />
                                元に戻す
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                onClick={saveOrder}
                                disabled={!isOrderDirty || isSavingOrder}
                            >
                                <Save className="size-4" />
                                表示順を保存
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {orderError && (
                            <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                {orderError}
                            </div>
                        )}
                        <SortableOrderList
                            disabled={isSavingOrder}
                            emptyState={
                                <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                    登録済みの案件書類はありません。
                                </div>
                            }
                            getId={getDocumentTypeId}
                            getLabel={getDocumentTypeLabel}
                            items={orderedDocumentTypes}
                            onReorder={(nextDocumentTypes) => {
                                setOrderError(null);
                                setPendingOrderedIds(
                                    documentTypeIds(nextDocumentTypes),
                                );
                            }}
                            renderItem={(
                                documentType,
                                { dragHandle, index, isDragging },
                            ) => (
                                <DocumentTypeRow
                                    dragHandle={dragHandle}
                                    documentType={documentType}
                                    isDragging={isDragging}
                                    position={index + 1}
                                />
                            )}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
