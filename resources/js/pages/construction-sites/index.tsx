import { Head, Link } from '@inertiajs/react';
import { FileText, MapPin, Pencil, Plus } from 'lucide-react';
import {
    create as siteCreate,
    edit as siteEdit,
    index as siteIndex,
    show as siteShow,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { ConstructionSite } from '@/types';

type Props = {
    sites: ConstructionSite[];
    canManage: boolean;
};

export default function ConstructionSitesIndex({ sites, canManage }: Props) {
    const totalGuideFiles = sites.reduce(
        (count, site) => count + site.guide_files.length,
        0,
    );

    return (
        <>
            <Head title="現場案内図" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <section className="rounded-3xl border bg-linear-to-br from-white via-neutral-50 to-sky-50/60 p-5 shadow-sm dark:border-neutral-800 dark:from-neutral-950 dark:via-neutral-950 dark:to-sky-950/20 sm:p-6 xl:p-7">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Reusable site guide library
                            </p>
                            <h1 className="text-2xl font-bold tracking-tight xl:text-3xl">
                                現場案内図
                            </h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                現場ごとの案内図と補足メモをまとめて管理できます。
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:min-w-[320px]">
                            <div className="rounded-2xl border bg-white/90 p-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                                <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                    Sites
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {sites.length}
                                </p>
                            </div>
                            <div className="rounded-2xl border bg-white/90 p-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                                <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                    Files
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {totalGuideFiles}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="max-w-2xl">
                        <p className="text-sm text-muted-foreground">
                            登録済みの現場一覧
                        </p>
                        <h2 className="text-xl font-semibold">
                            案内図ライブラリをすばやく確認
                        </h2>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={siteCreate()}>
                                <Plus className="size-4" />
                                現場を追加
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    {sites.map((site) => (
                        <Card
                            key={site.id}
                            className="h-full border-neutral-200/80 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800"
                        >
                            <CardHeader className="space-y-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-xl">
                                            {site.name}
                                        </CardTitle>
                                        {site.address && (
                                            <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                                <MapPin className="size-4" />
                                                {site.address}
                                            </p>
                                        )}
                                    </div>
                                    {canManage && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link href={siteEdit(site.id)}>
                                                <Pencil className="size-4" />
                                                編集
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="flex h-full flex-col gap-4">
                                <p className="min-h-12 text-sm leading-6 text-muted-foreground">
                                    {site.notes || 'メモはありません。'}
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-auto w-full justify-start"
                                >
                                    <Link href={siteShow(site.id)}>
                                        <FileText className="size-4" />
                                        案内図 {site.guide_files.length} 件
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                    {sites.length === 0 && (
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground lg:col-span-2 2xl:col-span-3 dark:border-neutral-800">
                            現場案内図ライブラリはまだ登録されていません。
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
            href: siteIndex(),
        },
    ],
};
