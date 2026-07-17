import { Head, Link } from '@inertiajs/react';
import { index as archiveIndex } from '@/actions/App/Http/Controllers/ReceptionArchiveController';
import { ReceptionCaseList } from '@/components/reception-case-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useUrlFilters } from '@/hooks/use-url-filters';
import type { ReceptionCase } from '@/types';
import type { QueryParams } from '@/wayfinder';

type Props = {
    cases: ReceptionCase[];
    filters: Filters;
};

type Filters = {
    keyword: string;
    completed_from: string;
    completed_to: string;
};

export default function ReceptionArchiveIndex({ cases, filters }: Props) {
    const { filters: localFilters, setFilter } = useUrlFilters(
        filters,
        archiveIndex.url(),
    );
    const showQuery = {
        from: 'archive',
        keyword: filters.keyword || undefined,
        completed_from: filters.completed_from || undefined,
        completed_to: filters.completed_to || undefined,
    } satisfies QueryParams;

    return (
        <>
            <Head title="アーカイブ画面" />
            <div
                data-reception-archive-page="true"
                className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6"
            >
                <div>
                    <p className="text-sm text-muted-foreground">
                        Reception Archive
                    </p>
                    <h1 className="text-2xl font-bold">アーカイブ画面</h1>
                </div>

                <Card>
                    <CardContent className="p-5">
                        <div className="grid gap-3 lg:grid-cols-[1fr_12rem_12rem]">
                            <Input
                                aria-label="検索キーワード"
                                value={localFilters.keyword}
                                onChange={(event) =>
                                    setFilter('keyword', event.target.value)
                                }
                                placeholder="案件ID、会社名、現場名、受付内容"
                            />
                            <Input
                                aria-label="完了日開始"
                                type="date"
                                value={localFilters.completed_from}
                                onChange={(event) =>
                                    setFilter(
                                        'completed_from',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                aria-label="完了日終了"
                                type="date"
                                value={localFilters.completed_to}
                                onChange={(event) =>
                                    setFilter(
                                        'completed_to',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        {(filters.keyword ||
                            filters.completed_from ||
                            filters.completed_to) && (
                            <Button
                                asChild
                                variant="link"
                                className="mt-2 px-0"
                            >
                                <Link href={archiveIndex()}>条件をクリア</Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <ReceptionCaseList
                    title="完了一覧"
                    cases={cases}
                    empty="完了した受付案件はありません。"
                    showQuery={showQuery}
                />
            </div>
        </>
    );
}
