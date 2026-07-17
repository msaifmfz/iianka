import { router, useForm } from '@inertiajs/react';
import { Pencil, Phone, Plus, Save, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import {
    destroy as subcontractorDestroy,
    update as subcontractorUpdate,
} from '@/actions/App/Http/Controllers/ConstructionSubcontractorController';
import FormField from '@/components/form-field';
import { RecentResourceBadge } from '@/components/recent-resource-feedback';
import { recentResourceHighlightClass } from '@/components/recent-resource-feedback';
import { Input } from '@/components/ui/input';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import {
    recentResourceMatches,
    useRecentResource,
} from '@/hooks/use-recent-resource';
import { cn, phoneHref, toggleNumber } from '@/lib/utils';
import { fieldError } from '@/lib/validation';
import type { ConstructionSubcontractor } from '@/types';

export type NewSubcontractor = {
    name: string;
    phone: string;
};

type ExistingSubcontractorForm = {
    name: string;
    phone: string;
};

type Props = {
    subcontractors: ConstructionSubcontractor[];
    selectedIds: number[];
    onChangeSelectedIds: (ids: number[]) => void;
    newSubcontractors: NewSubcontractor[];
    onChangeNewSubcontractors: (subcontractors: NewSubcontractor[]) => void;
    /** Parent form errors; reads `subcontractor_ids` and `new_subcontractors.*`. */
    errors: object;
    /** Called after a subcontractor is deleted from the master list. */
    onDeleted?: (subcontractorId: number) => void;
};

/**
 * Searchable subcontractor selection with inline edit/delete of the master
 * list and a batch "add new" section. Owns its search/edit state and the
 * master-data mutations; selection and new rows live in the parent form.
 */
export function SubcontractorPicker({
    subcontractors,
    selectedIds,
    onChangeSelectedIds,
    newSubcontractors,
    onChangeNewSubcontractors,
    errors,
    onDeleted,
}: Props) {
    const recentResource = useRecentResource();
    const { confirm: confirmDelete, dialog: deleteDialog } = useConfirmDialog();
    const [search, setSearch] = useState('');
    const [editingSubcontractorId, setEditingSubcontractorId] = useState<
        number | null
    >(null);
    const {
        data: editingSubcontractorData,
        setData: setEditingSubcontractorData,
        patch: patchSubcontractor,
        processing: processingSubcontractorUpdate,
        errors: subcontractorErrors,
        clearErrors: clearSubcontractorErrors,
        reset: resetEditingSubcontractor,
    } = useForm<ExistingSubcontractorForm>({
        name: '',
        phone: '',
    });

    const searchTerm = search.trim().toLocaleLowerCase();
    const filteredSubcontractors =
        searchTerm === ''
            ? subcontractors
            : subcontractors.filter((subcontractor) =>
                  `${subcontractor.name} ${subcontractor.phone ?? ''}`
                      .toLocaleLowerCase()
                      .includes(searchTerm),
              );

    function editSubcontractor(subcontractor: ConstructionSubcontractor) {
        clearSubcontractorErrors();
        setEditingSubcontractorId(subcontractor.id);
        setEditingSubcontractorData({
            name: subcontractor.name,
            phone: subcontractor.phone ?? '',
        });
    }

    function cancelSubcontractorEdit() {
        clearSubcontractorErrors();
        setEditingSubcontractorId(null);
        resetEditingSubcontractor();
    }

    function saveSubcontractorEdit(subcontractor: ConstructionSubcontractor) {
        patchSubcontractor(subcontractorUpdate.url(subcontractor.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingSubcontractorId(null);
                resetEditingSubcontractor();
            },
        });
    }

    async function deleteSubcontractor(
        subcontractor: ConstructionSubcontractor,
    ) {
        if (
            !(await confirmDelete({
                title: `${subcontractor.name} を今後の選択肢から削除しますか？`,
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(subcontractorDestroy.url(subcontractor.id), {
            preserveScroll: true,
            onSuccess: () => {
                onDeleted?.(subcontractor.id);
            },
        });
    }

    function addNewSubcontractor() {
        onChangeNewSubcontractors([
            ...newSubcontractors,
            { name: '', phone: '' },
        ]);
    }

    function updateNewSubcontractor(
        index: number,
        field: 'name' | 'phone',
        value: string,
    ) {
        onChangeNewSubcontractors(
            newSubcontractors.map((subcontractor, subcontractorIndex) =>
                subcontractorIndex === index
                    ? { ...subcontractor, [field]: value }
                    : subcontractor,
            ),
        );
    }

    function removeNewSubcontractor(index: number) {
        onChangeNewSubcontractors(
            newSubcontractors.filter(
                (_subcontractor, subcontractorIndex) =>
                    subcontractorIndex !== index,
            ),
        );
    }

    return (
        <div className="rounded-2xl border p-4 md:col-span-3 dark:border-neutral-800">
            {deleteDialog}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold">下請け</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        工事予定に入る下請けを選択し、電話番号をすぐ確認できます。
                    </p>
                </div>
                {(selectedIds.length > 0 || newSubcontractors.length > 0) && (
                    <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                        選択 {selectedIds.length} / 追加{' '}
                        {newSubcontractors.length}
                    </span>
                )}
            </div>

            <div className="relative mt-3">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    className="pl-9"
                    placeholder="名前・電話番号で検索"
                />
            </div>

            <div className="mt-3 max-h-80 overflow-y-auto rounded-lg border dark:border-neutral-800">
                <div className="grid gap-2 p-2 sm:grid-cols-2">
                    {filteredSubcontractors.map((subcontractor) => {
                        const isSelected = selectedIds.includes(
                            subcontractor.id,
                        );
                        const isEditing =
                            editingSubcontractorId === subcontractor.id;
                        const isRecentResource = recentResourceMatches(
                            recentResource,
                            'construction_subcontractor',
                            subcontractor.id,
                        );

                        return (
                            <div
                                key={subcontractor.id}
                                className={cn(
                                    'flex items-start justify-between gap-3 rounded-xl border p-3 text-sm transition motion-reduce:transition-none',
                                    isSelected
                                        ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                                        : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                    isEditing &&
                                        'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
                                    isRecentResource &&
                                        recentResourceHighlightClass,
                                )}
                            >
                                {isEditing ? (
                                    <div className="grid min-w-0 flex-1 gap-2">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Input
                                                value={
                                                    editingSubcontractorData.name
                                                }
                                                onChange={(event) =>
                                                    setEditingSubcontractorData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="名前"
                                                autoFocus
                                            />
                                            <Input
                                                value={
                                                    editingSubcontractorData.phone
                                                }
                                                onChange={(event) =>
                                                    setEditingSubcontractorData(
                                                        'phone',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="電話番号（任意）"
                                            />
                                        </div>
                                        {(subcontractorErrors.name ||
                                            subcontractorErrors.phone) && (
                                            <div className="grid gap-1 text-xs text-destructive sm:grid-cols-2">
                                                <span>
                                                    {subcontractorErrors.name}
                                                </span>
                                                <span>
                                                    {subcontractorErrors.phone}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 rounded-md bg-sky-700 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-sky-500 dark:text-sky-950 dark:hover:bg-sky-400"
                                                onClick={() =>
                                                    saveSubcontractorEdit(
                                                        subcontractor,
                                                    )
                                                }
                                                disabled={
                                                    processingSubcontractorUpdate
                                                }
                                            >
                                                <Save className="size-3.5" />
                                                {processingSubcontractorUpdate
                                                    ? '下請け情報を保存中...'
                                                    : '下請け情報を保存'}
                                            </button>
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 rounded-md border bg-background px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                onClick={
                                                    cancelSubcontractorEdit
                                                }
                                            >
                                                <X className="size-3.5" />
                                                キャンセル
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-2">
                                            <input
                                                type="checkbox"
                                                className="mt-1"
                                                checked={isSelected}
                                                onChange={() =>
                                                    onChangeSelectedIds(
                                                        toggleNumber(
                                                            selectedIds,
                                                            subcontractor.id,
                                                        ),
                                                    )
                                                }
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {subcontractor.name}
                                                </span>
                                                {isRecentResource &&
                                                    recentResource !== null && (
                                                        <RecentResourceBadge
                                                            action={
                                                                recentResource.action
                                                            }
                                                            className="mt-2"
                                                        />
                                                    )}
                                                {subcontractor.phone && (
                                                    <a
                                                        href={phoneHref(
                                                            subcontractor.phone,
                                                        )}
                                                        className="mt-1 inline-flex items-center gap-1 text-xs text-sky-700 hover:underline dark:text-sky-300"
                                                    >
                                                        <Phone className="size-3.5" />
                                                        {subcontractor.phone}
                                                    </a>
                                                )}
                                            </span>
                                        </label>
                                        <div className="flex shrink-0 flex-col gap-2">
                                            <button
                                                type="button"
                                                className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                onClick={() =>
                                                    editSubcontractor(
                                                        subcontractor,
                                                    )
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                編集
                                            </button>
                                            <button
                                                type="button"
                                                className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                onClick={() =>
                                                    void deleteSubcontractor(
                                                        subcontractor,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-3.5" />
                                                削除
                                            </button>
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
                    {subcontractors.length === 0 && (
                        <p className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground sm:col-span-2 dark:border-neutral-800">
                            登録済みの下請けはまだありません。下の入力から追加できます。
                        </p>
                    )}
                    {subcontractors.length > 0 &&
                        filteredSubcontractors.length === 0 && (
                            <p className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground sm:col-span-2 dark:border-neutral-800">
                                一致する下請けはありません。
                            </p>
                        )}
                </div>
            </div>

            {fieldError(errors, 'subcontractor_ids') && (
                <p className="mt-2 text-xs text-destructive">
                    {fieldError(errors, 'subcontractor_ids')}
                </p>
            )}

            <div className="mt-4 grid gap-3 rounded-lg border bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm font-medium">新しく追加する下請け</p>
                    <button
                        type="button"
                        className="inline-flex items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted"
                        onClick={addNewSubcontractor}
                    >
                        <Plus className="size-4" />
                        行を追加
                    </button>
                </div>

                {newSubcontractors.length > 0 ? (
                    <div className="grid gap-2">
                        {newSubcontractors.map((subcontractor, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-lg border bg-background p-3 dark:border-neutral-800"
                            >
                                <div className="grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                                    <FormField label="名前" required>
                                        <Input
                                            required
                                            value={subcontractor.name}
                                            onChange={(event) =>
                                                updateNewSubcontractor(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="名前"
                                        />
                                    </FormField>
                                    <FormField label="電話番号">
                                        <Input
                                            value={subcontractor.phone}
                                            onChange={(event) =>
                                                updateNewSubcontractor(
                                                    index,
                                                    'phone',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="電話番号（任意）"
                                        />
                                    </FormField>
                                    <button
                                        type="button"
                                        className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground md:self-end"
                                        onClick={() =>
                                            removeNewSubcontractor(index)
                                        }
                                    >
                                        <Trash2 className="size-3.5" />
                                        削除
                                    </button>
                                </div>
                                {(fieldError(
                                    errors,
                                    `new_subcontractors.${index}.name`,
                                ) ||
                                    fieldError(
                                        errors,
                                        `new_subcontractors.${index}.phone`,
                                    )) && (
                                    <div className="grid gap-1 text-xs text-destructive md:grid-cols-2">
                                        <span>
                                            {fieldError(
                                                errors,
                                                `new_subcontractors.${index}.name`,
                                            )}
                                        </span>
                                        <span>
                                            {fieldError(
                                                errors,
                                                `new_subcontractors.${index}.phone`,
                                            )}
                                        </span>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        複数の下請けをまとめて追加できます。
                    </p>
                )}
            </div>
        </div>
    );
}
