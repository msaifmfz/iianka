import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, FileText, Pencil } from 'lucide-react';
import {
    edit as guideEdit,
    index as guideIndex,
} from '@/actions/App/Http/Controllers/ConstructionSiteController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { SiteGuideFile } from '@/types';

type Props = {
    guideFile: SiteGuideFile;
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

export default function ConstructionSiteShow({ guideFile, canManage }: Props) {
    return (
        <>
            <Head title={`${guideFile.name} - 現場案内図`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link href={guideIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={guideEdit(guideFile.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="rounded-2xl border-neutral-200/80 shadow-sm dark:border-neutral-800">
                    <CardHeader className="space-y-3 xl:px-8 xl:pt-8">
                        <p className="text-sm text-muted-foreground">
                            Site guide library
                        </p>
                        <CardTitle className="flex min-w-0 items-center gap-3 text-2xl xl:text-3xl">
                            <FileText className="size-6 shrink-0 text-muted-foreground" />
                            <span className="truncate">{guideFile.name}</span>
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {guideFileTypeLabel(guideFile)}
                        </p>
                    </CardHeader>
                    <CardContent className="xl:px-8 xl:pb-8">
                        <Button asChild className="min-h-11">
                            <a
                                href={guideFile.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink className="size-4" />
                                ファイルを開く
                            </a>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ConstructionSiteShow.layout = {
    breadcrumbs: [
        {
            title: '現場案内図',
            href: guideIndex(),
        },
    ],
};
