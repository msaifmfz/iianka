import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, UploadCloud } from 'lucide-react';
import {
    index as siteIndex,
    store as siteStore,
    update as siteUpdate,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { ConstructionSite } from '@/types';

type Props = {
    site: ConstructionSite | null;
};

type SiteForm = {
    _method: 'put' | '';
    name: string;
    address: string;
    notes: string;
    guide_files: File[];
};

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            <span>{label}</span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </label>
    );
}

export default function ConstructionSiteForm({ site }: Props) {
    const { data, setData, post, processing, progress, errors } =
        useForm<SiteForm>({
            _method: site ? 'put' : '',
            name: site?.name ?? '',
            address: site?.address ?? '',
            notes: site?.notes ?? '',
            guide_files: [],
        });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(site ? siteUpdate.url(site.id) : siteStore.url(), {
            forceFormData: true,
        });
    }

    return (
        <>
            <Head title={site ? '現場案内図編集' : '現場案内図追加'} />
            <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Site guide library
                        </p>
                        <h1 className="text-2xl font-bold">
                            {site ? '現場案内図編集' : '現場案内図追加'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={siteIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-5 rounded-3xl border bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <Field label="場所 / 現場名" error={errors.name}>
                        <Input
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="住所" error={errors.address}>
                        <Input
                            value={data.address}
                            onChange={(event) =>
                                setData('address', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="メモ" error={errors.notes}>
                        <textarea
                            className="min-h-28 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                        />
                    </Field>

                    {site?.guide_files.length ? (
                        <div className="rounded-2xl bg-neutral-50 p-4 text-sm dark:bg-neutral-900">
                            <p className="font-medium">登録済みファイル</p>
                            <p className="mt-2 text-muted-foreground">
                                {site.guide_files
                                    .map((file) => file.name)
                                    .join('、')}
                            </p>
                        </div>
                    ) : null}

                    <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed p-6 text-center text-sm text-muted-foreground dark:border-neutral-800">
                        <UploadCloud className="size-6" />
                        PDF / 画像をアップロード
                        <input
                            className="hidden"
                            type="file"
                            multiple
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            onChange={(event) =>
                                setData(
                                    'guide_files',
                                    Array.from(event.currentTarget.files ?? []),
                                )
                            }
                        />
                    </label>
                    {data.guide_files.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                            {data.guide_files.length} ファイル選択中
                        </p>
                    )}
                    {errors.guide_files && (
                        <p className="text-xs text-destructive">
                            {errors.guide_files}
                        </p>
                    )}
                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            className="w-full"
                        />
                    )}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? '保存中...' : '保存'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ConstructionSiteForm.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '現場案内図',
            href: siteIndex(),
        },
    ],
};
