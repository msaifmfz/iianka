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
            <div className="mx-auto max-w-4xl space-y-5 p-4 md:p-6">
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

                <Card>
                    <CardHeader>
                        <CardTitle className="text-3xl">{site.name}</CardTitle>
                        {site.address && (
                            <p className="text-sm text-muted-foreground">
                                {site.address}
                            </p>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {site.notes && (
                            <p className="leading-7 whitespace-pre-line">
                                {site.notes}
                            </p>
                        )}
                        <section className="space-y-3">
                            <h2 className="font-semibold">登録ファイル</h2>
                            {site.guide_files.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground dark:border-neutral-800">
                                    ファイルは登録されていません。
                                </p>
                            ) : (
                                <div className="grid gap-2 md:grid-cols-2">
                                    {site.guide_files.map((file) => (
                                        <Button
                                            key={file.id}
                                            asChild
                                            variant="outline"
                                            className="justify-start"
                                        >
                                            <a
                                                href={file.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText className="size-4" />
                                                {file.name}
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
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '現場案内図',
            href: siteIndex(),
        },
    ],
};
