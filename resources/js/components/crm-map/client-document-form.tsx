import { useForm } from '@inertiajs/react';
import { store as documentStore } from '@/actions/App/Http/Controllers/ClientDocumentController';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { ClientDocumentLimits, ClientPlace } from '@/types';

type DocumentForm = {
    file: File | null;
    name: string;
    issued_on: string;
    client_place_id: string;
};

/**
 * Files one document on a client. The name defaults to the file name, so
 * picking a file and saving is enough; date and place are optional.
 */
export default function ClientDocumentForm({
    clientId,
    places,
    limits,
    onDone,
}: {
    clientId: number;
    places: ClientPlace[];
    limits: ClientDocumentLimits;
    onDone: () => void;
}) {
    const form = useForm<DocumentForm>({
        file: null,
        name: '',
        issued_on: '',
        client_place_id: '',
    });
    const maxFileMegabytes = Math.floor(limits.max_file_bytes / (1024 * 1024));

    function selectFile(file: File | null) {
        form.clearErrors('file');

        if (file && file.size > limits.max_file_bytes) {
            form.setData('file', null);
            form.setError('file', `ファイルは${maxFileMegabytes}MBまでです。`);

            return;
        }

        form.setData((data) => ({
            ...data,
            file,
            // Only fill the name while the user has not typed their own.
            name:
                data.name === '' || data.name === baseName(data.file)
                    ? baseName(file)
                    : data.name,
        }));
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post(documentStore.url(clientId), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onDone,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border bg-neutral-50 p-4 sm:grid-cols-2 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <FormField
                label="ファイル"
                required
                error={form.errors.file}
                className="sm:col-span-2"
            >
                <Input
                    type="file"
                    required
                    accept={limits.extensions
                        .map((extension) => `.${extension}`)
                        .join(',')}
                    onChange={(event) =>
                        selectFile(event.currentTarget.files?.[0] ?? null)
                    }
                />
            </FormField>
            <FormField label="書類名" error={form.errors.name}>
                <Input
                    maxLength={255}
                    placeholder="例: 見積書 2026年9月"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </FormField>
            <FormField label="日付" error={form.errors.issued_on}>
                <Input
                    type="date"
                    value={form.data.issued_on}
                    onChange={(event) =>
                        form.setData('issued_on', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="関連する地点"
                error={form.errors.client_place_id}
                className="sm:col-span-2"
            >
                <NativeSelect
                    value={form.data.client_place_id}
                    onChange={(event) =>
                        form.setData('client_place_id', event.target.value)
                    }
                >
                    <option value="">顧客全体</option>
                    {places.map((place) => (
                        <option key={place.id} value={place.id}>
                            {place.name}
                            {place.archived_at ? '（アーカイブ）' : ''}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            {form.progress && (
                <progress
                    value={form.progress.percentage}
                    max="100"
                    className="w-full sm:col-span-2"
                />
            )}
            <div className="flex justify-end gap-2 sm:col-span-2">
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
                    disabled={form.processing || form.data.file === null}
                    className={cn(form.processing && 'opacity-70')}
                >
                    {form.processing ? '登録中...' : '登録'}
                </Button>
            </div>
        </form>
    );
}

function baseName(file: File | null): string {
    return file?.name.replace(/\.[^.]+$/, '') ?? '';
}
