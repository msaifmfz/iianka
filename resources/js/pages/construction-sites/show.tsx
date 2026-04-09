import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Pencil } from 'lucide-react';
import {
    edit as siteEdit,
    index as siteIndex,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { ConstructionSite } from '@/types';

type Props = {
    site: ConstructionSite;
    canManage: boolean;
};

export default function ConstructionSiteShow({ site, canManage }: Props) {
    return (
        <>
            <Head title={`${site.name} - 現場案内図`} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link href={siteIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={siteEdit(site.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="border-neutral-200/80 shadow-sm dark:border-neutral-800">
                    <CardHeader className="space-y-4 xl:px-8 xl:pt-8">
                        <div className="space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Site guide library
                            </p>
                            <CardTitle className="text-3xl xl:text-4xl">
                                {site.name}
                            </CardTitle>
                        </div>
                        {site.address && (
                            <p className="text-sm text-muted-foreground">
                                {site.address}
                            </p>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)] xl:px-8 xl:pb-8">
                        <section className="space-y-3">
                            <h2 className="font-semibold">メモ</h2>
                            <div className="rounded-2xl border bg-neutral-50/70 p-5 dark:border-neutral-800 dark:bg-neutral-900/60">
                                <p className="leading-7 whitespace-pre-line">
                                    {site.notes || 'メモはありません。'}
                                </p>
                            </div>
                        </section>
                        <section className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="font-semibold">登録ファイル</h2>
                                <span className="text-sm text-muted-foreground">
                                    {site.guide_files.length} 件
                                </span>
                            </div>
                            {site.guide_files.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground dark:border-neutral-800">
                                    ファイルは登録されていません。
                                </p>
                            ) : (
                                <div className="grid gap-2">
                                    {site.guide_files.map((file) => (
                                        <Button
                                            key={file.id}
                                            asChild
                                            variant="outline"
                                            className="h-auto justify-start py-3"
                                        >
                                            <a
                                                href={file.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText className="size-4 shrink-0" />
                                                <span className="truncate">
                                                    {file.name}
                                                </span>
                                            </a>
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </section>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ConstructionSiteShow.layout = {
    breadcrumbs: [
        {
            title: 'メニュー',
            href: dashboard(),
        },
        {
            title: '現場案内図',
            href: siteIndex(),
        },
    ],
};
