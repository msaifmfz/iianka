import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import {
    create as guideCreate,
    destroy as guideDestroy,
    edit as guideEdit,
    index as guideIndex,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { SiteGuideFile } from '@/types';

type Props = {
    guideFiles: SiteGuideFile[];
    canManage: boolean;
};

function guideFileTypeLabel(file: SiteGuideFile) {
    if (file.mime_type?.includes('pdf')) {
        return 'PDF';
    }

    if (file.mime_type?.startsWith('image/')) {
        return '画像';
    }

    return 'ファイル';
}

export default function ConstructionSitesIndex({
    guideFiles,
    canManage,
}: Props) {
    function deleteGuideFile(file: SiteGuideFile) {
        if (!confirm(`${file.name} を削除しますか？`)) {
            return;
        }

        router.delete(guideDestroy.url(file.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="現場案内図" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <section className="rounded-2xl border bg-white p-5 shadow-sm sm:p-6 xl:p-7 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Site guide library
                            </p>
                            <h1 className="text-2xl font-bold tracking-tight xl:text-3xl">
                                現場案内図
                            </h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                ファイル名で判別できるように案内図を管理します。
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-2xl border bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/70">
                                <p className="text-xs font-medium text-muted-foreground">
                                    登録ファイル
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {guideFiles.length}
                                </p>
                            </div>
                            {canManage && (
                                <Button asChild>
                                    <Link href={guideCreate()}>
                                        <Plus className="size-4" />
                                        案内図を追加
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {guideFiles.map((file) => (
                        <Card
                            key={file.id}
                            className="rounded-2xl border-neutral-200/80 shadow-sm dark:border-neutral-800"
                        >
                            <CardContent className="grid gap-4 p-4">
                                <div className="flex min-w-0 items-start gap-3">
                                    <div className="rounded-lg bg-neutral-100 p-2 dark:bg-neutral-900">
                                        <FileText className="size-5 text-muted-foreground" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate font-semibold">
                                            {file.name}
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {guideFileTypeLabel(file)}
                                        </p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="flex-1 justify-center"
                                    >
                                        <a
                                            href={file.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink className="size-4" />
                                            確認
                                        </a>
                                    </Button>
                                    {canManage && (
                                        <>
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link href={guideEdit(file.id)}>
                                                    <Pencil className="size-4" />
                                                    編集
                                                </Link>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    deleteGuideFile(file)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                削除
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                    {guideFiles.length === 0 && (
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3 dark:border-neutral-800">
                            現場案内図はまだ登録されていません。
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

ConstructionSitesIndex.layout = {
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
