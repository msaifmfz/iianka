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
    return (
        <>
            <Head title="現場案内図" />
            <div className="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Reusable site guide library
                        </p>
                        <h1 className="text-2xl font-bold">現場案内図</h1>
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

                <div className="grid gap-4 md:grid-cols-2">
                    {sites.map((site) => (
                        <Card key={site.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{site.name}</CardTitle>
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
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    {site.notes || 'メモはありません。'}
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full justify-start"
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
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground dark:border-neutral-800">
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
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '現場案内図',
            href: siteIndex(),
        },
    ],
};
