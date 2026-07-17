import {
    ExternalLink,
    Files,
    FileText,
    Search,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn, toggleNumber } from '@/lib/utils';
import { fieldError } from '@/lib/validation';
import type { SiteGuideFile } from '@/types';

const guideFileAccept =
    'application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif';

function guideFileTypeLabel(file: SiteGuideFile) {
    if (file.mime_type?.includes('pdf')) {
        return 'PDF';
    }

    if (file.mime_type?.startsWith('image/')) {
        return '画像';
    }

    return 'ファイル';
}

function defaultGuideFileName(file: File) {
    const nameWithoutExtension = file.name.replace(/\.[^/.]+$/, '').trim();

    return nameWithoutExtension || file.name;
}

function fileSizeLabel(size: number) {
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }

    if (size >= 1024) {
        return `${Math.ceil(size / 1024)} KB`;
    }

    return `${size} B`;
}

type Props = {
    siteGuideFiles: SiteGuideFile[];
    selectedIds: number[];
    onChangeSelectedIds: (ids: number[]) => void;
    uploads: File[];
    uploadNames: string[];
    onChangeUploads: (files: File[], names: string[]) => void;
    /** Parent form errors; reads `guide_files*` and `guide_file_names*`. */
    errors: object;
};

/**
 * Site guide file selection: search/select registered files plus batch upload
 * of new ones with editable display names. Selection and upload lists live in
 * the parent form; search state is internal.
 */
export function SiteGuideFilePicker({
    siteGuideFiles,
    selectedIds,
    onChangeSelectedIds,
    uploads,
    uploadNames,
    onChangeUploads,
    errors,
}: Props) {
    const [search, setSearch] = useState('');

    const searchTerm = search.trim().toLocaleLowerCase();
    const filteredSiteGuideFiles =
        searchTerm === ''
            ? siteGuideFiles
            : siteGuideFiles.filter((file) =>
                  `${file.name} ${guideFileTypeLabel(file)}`
                      .toLocaleLowerCase()
                      .includes(searchTerm),
              );
    const selectedVisibleIds = filteredSiteGuideFiles
        .filter((file) => selectedIds.includes(file.id))
        .map((file) => file.id);

    function addUploads(files: File[]) {
        if (files.length === 0) {
            return;
        }

        onChangeUploads(
            [...uploads, ...files],
            [
                ...uploadNames,
                ...files.map((file) => defaultGuideFileName(file)),
            ],
        );
    }

    function removeUpload(index: number) {
        onChangeUploads(
            uploads.filter((_file, fileIndex) => fileIndex !== index),
            uploadNames.filter((_name, nameIndex) => nameIndex !== index),
        );
    }

    function updateUploadName(index: number, name: string) {
        onChangeUploads(
            uploads,
            uploadNames.map((uploadName, nameIndex) =>
                nameIndex === index ? name : uploadName,
            ),
        );
    }

    function selectVisibleFiles() {
        onChangeSelectedIds([
            ...new Set([
                ...selectedIds,
                ...filteredSiteGuideFiles.map((file) => file.id),
            ]),
        ]);
    }

    function clearVisibleFiles() {
        const visibleFileIds = new Set(
            filteredSiteGuideFiles.map((file) => file.id),
        );

        onChangeSelectedIds(
            selectedIds.filter((fileId) => !visibleFileIds.has(fileId)),
        );
    }

    return (
        <div className="rounded-2xl border p-4 dark:border-neutral-800">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">現場案内図</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        登録済みの案内図を探して選択、または新しいファイルをまとめて追加できます。
                    </p>
                </div>
                {(selectedIds.length > 0 || uploads.length > 0) && (
                    <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                        選択 {selectedIds.length} / 追加 {uploads.length}
                    </span>
                )}
            </div>

            <div className="mt-4 grid gap-3">
                <div className="grid gap-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-medium">登録済みから選択</p>
                        {filteredSiteGuideFiles.length > 0 && (
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    className="rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                    disabled={
                                        selectedVisibleIds.length ===
                                        filteredSiteGuideFiles.length
                                    }
                                    onClick={selectVisibleFiles}
                                >
                                    表示中をすべて選択
                                </button>
                                <button
                                    type="button"
                                    className="rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                    disabled={selectedVisibleIds.length === 0}
                                    onClick={clearVisibleFiles}
                                >
                                    表示中を解除
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="pl-9"
                            placeholder="表示名で検索"
                        />
                    </div>

                    <div className="max-h-80 overflow-y-auto rounded-lg border dark:border-neutral-800">
                        {siteGuideFiles.length === 0 ? (
                            <p className="p-3 text-sm text-muted-foreground">
                                登録済みの案内図はまだありません。下のアップロードから追加できます。
                            </p>
                        ) : filteredSiteGuideFiles.length ? (
                            <div className="grid gap-2 p-2">
                                {filteredSiteGuideFiles.map((file) => {
                                    const isSelected = selectedIds.includes(
                                        file.id,
                                    );
                                    const inputId = `site-guide-file-${file.id}`;

                                    return (
                                        <div
                                            key={file.id}
                                            className={cn(
                                                'flex items-start gap-3 rounded-lg border p-3 transition',
                                                isSelected
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                            )}
                                        >
                                            <label
                                                htmlFor={inputId}
                                                className="flex min-w-0 flex-1 cursor-pointer items-start gap-3 text-sm"
                                            >
                                                <input
                                                    id={inputId}
                                                    type="checkbox"
                                                    className="mt-1"
                                                    checked={isSelected}
                                                    onChange={() =>
                                                        onChangeSelectedIds(
                                                            toggleNumber(
                                                                selectedIds,
                                                                file.id,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <span className="min-w-0 space-y-1">
                                                    <span className="flex min-w-0 items-center gap-2 font-medium">
                                                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                        <span className="truncate">
                                                            {file.name}
                                                        </span>
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {guideFileTypeLabel(
                                                            file,
                                                        )}
                                                    </span>
                                                </span>
                                            </label>
                                            <a
                                                href={file.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            >
                                                <ExternalLink className="size-3.5" />
                                                確認
                                            </a>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="p-3 text-sm text-muted-foreground">
                                一致する案内図はありません。
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 rounded-lg border bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-2">
                            <Files className="size-4 shrink-0 text-muted-foreground" />
                            <p className="text-sm font-medium">
                                新しく追加するファイル
                            </p>
                        </div>
                        <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted">
                            <UploadCloud className="size-4" />
                            ファイルを選択
                            <input
                                className="hidden"
                                type="file"
                                multiple
                                accept={guideFileAccept}
                                onChange={(event) => {
                                    addUploads(
                                        Array.from(
                                            event.currentTarget.files ?? [],
                                        ),
                                    );
                                    event.currentTarget.value = '';
                                }}
                            />
                        </label>
                    </div>

                    {uploads.length > 0 ? (
                        <div className="max-h-96 overflow-y-auto rounded-lg border bg-background dark:border-neutral-800">
                            <div className="grid gap-2 p-2">
                                {uploads.map((file, index) => (
                                    <div
                                        key={`${file.name}-${file.lastModified}-${index}`}
                                        className="grid gap-2 rounded-lg border p-3 dark:border-neutral-800"
                                    >
                                        <div className="flex min-w-0 items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {file.name}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {fileSizeLabel(file.size)}
                                                </p>
                                                {fieldError(
                                                    errors,
                                                    `guide_files.${index}`,
                                                ) && (
                                                    <p className="mt-1 text-xs text-destructive">
                                                        {fieldError(
                                                            errors,
                                                            `guide_files.${index}`,
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                className="inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                onClick={() =>
                                                    removeUpload(index)
                                                }
                                            >
                                                <Trash2 className="size-3.5" />
                                                削除
                                            </button>
                                        </div>
                                        <div className="grid gap-1.5">
                                            <label
                                                htmlFor={`guide-file-name-${index}`}
                                                className="text-xs font-medium text-muted-foreground"
                                            >
                                                表示名
                                            </label>
                                            <Input
                                                id={`guide-file-name-${index}`}
                                                required
                                                value={uploadNames[index] ?? ''}
                                                onChange={(event) =>
                                                    updateUploadName(
                                                        index,
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="例: 搬入口案内図"
                                            />
                                            {fieldError(
                                                errors,
                                                `guide_file_names.${index}`,
                                            ) && (
                                                <p className="text-xs text-destructive">
                                                    {fieldError(
                                                        errors,
                                                        `guide_file_names.${index}`,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            PDF / JPEG / PNG / WebP / HEIC /
                            HEIF、50MBまで。複数ファイルをまとめて選ぶと、ファイル名から表示名を自動入力します。
                        </p>
                    )}
                </div>
            </div>
            {uploads.length > 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    {uploads.length} ファイル選択中
                </p>
            )}
            {fieldError(errors, 'guide_files') && (
                <p className="mt-2 text-xs text-destructive">
                    {fieldError(errors, 'guide_files')}
                </p>
            )}
            {fieldError(errors, 'guide_file_names') && (
                <p className="mt-2 text-xs text-destructive">
                    {fieldError(errors, 'guide_file_names')}
                </p>
            )}
        </div>
    );
}
