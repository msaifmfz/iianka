import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, UploadCloud } from 'lucide-react';
import {
    index as guideIndex,
    store as guideStore,
    update as guideUpdate,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { SiteGuideFile } from '@/types';

type Props = {
    guideFile: SiteGuideFile | null;
};

type GuideFileForm = {
    _method: 'put' | '';
    name: string;
    guide_file: File | null;
};

const guideFileAccept =
    'application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif';

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

export default function ConstructionSiteForm({ guideFile }: Props) {
    const { data, setData, post, processing, progress, errors } =
        useForm<GuideFileForm>({
            _method: guideFile ? 'put' : '',
            name: guideFile?.name ?? '',
            guide_file: null,
        });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(guideFile ? guideUpdate.url(guideFile.id) : guideStore.url(), {
            forceFormData: true,
        });
    }

    const selectedFiles = data.guide_file === null ? [] : [data.guide_file];

    return (
        <>
            <Head title={guideFile ? '現場案内図編集' : '現場案内図追加'} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Site guide library
                        </p>
                        <h1 className="text-2xl font-bold">
                            {guideFile ? '現場案内図編集' : '現場案内図追加'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={guideIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-2xl border bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <Field label="表示名" error={errors.name}>
                        <Input
                            required
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                    </Field>

                    {guideFile && (
                        <div className="rounded-2xl bg-neutral-50 p-4 text-sm dark:bg-neutral-900">
                            <p className="font-medium">現在のファイル</p>
                            <a
                                href={guideFile.url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-flex min-w-0 items-center gap-2 text-muted-foreground hover:text-foreground"
                            >
                                <FileText className="size-4 shrink-0" />
                                <span className="truncate">
                                    {guideFile.name}
                                </span>
                            </a>
                        </div>
                    )}

                    <label className="flex min-h-52 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed p-6 text-center text-sm text-muted-foreground transition hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900">
                        <UploadCloud className="size-6" />
                        {guideFile
                            ? 'PDF / 画像を差し替え'
                            : 'PDF / 画像をアップロード'}
                        <span className="text-xs">
                            {guideFile
                                ? '表示名だけを変える場合は選択不要です。'
                                : 'PDF / JPEG / PNG / WebP / HEIC / HEIF、50MBまで。'}
                        </span>
                        <input
                            className="hidden"
                            type="file"
                            accept={guideFileAccept}
                            onChange={(event) => {
                                const files = Array.from(
                                    event.currentTarget.files ?? [],
                                );

                                setData('guide_file', files[0] ?? null);
                            }}
                        />
                    </label>

                    {selectedFiles.length > 0 && (
                        <div className="rounded-2xl bg-neutral-50 p-4 text-sm dark:bg-neutral-900">
                            <p className="font-medium">
                                {selectedFiles.length} ファイル選択中
                            </p>
                            <p className="mt-2 text-muted-foreground">
                                {selectedFiles
                                    .map((file) => file.name)
                                    .join('、')}
                            </p>
                        </div>
                    )}

                    {errors.guide_file && (
                        <p className="text-xs text-destructive">
                            {errors.guide_file}
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
            title: 'メニュー',
            href: dashboard(),
        },
        {
            title: '現場案内図',
            href: guideIndex(),
        },
    ],
};
