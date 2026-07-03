import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { index as archiveIndex } from '@/actions/App/Http/Controllers/ReceptionArchiveController';
import { ReceptionCaseList } from '@/components/reception-case-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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

const autoSearchDelay = 400;

function compactFilters(filters: Filters): QueryParams {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );
}

export default function ReceptionArchiveIndex({ cases, filters }: Props) {
    const [keyword, setKeyword] = useState(filters.keyword);
    const [completedFrom, setCompletedFrom] = useState(filters.completed_from);
    const [completedTo, setCompletedTo] = useState(filters.completed_to);
    const showQuery = {
        from: 'archive',
        keyword: filters.keyword || undefined,
        completed_from: filters.completed_from || undefined,
        completed_to: filters.completed_to || undefined,
    } satisfies QueryParams;

    useEffect(() => {
        const nextFilters = {
            keyword,
            completed_from: completedFrom,
            completed_to: completedTo,
        } satisfies Filters;

        if (
            nextFilters.keyword === filters.keyword &&
            nextFilters.completed_from === filters.completed_from &&
            nextFilters.completed_to === filters.completed_to
        ) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(archiveIndex.url(), compactFilters(nextFilters), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        }, autoSearchDelay);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [
        keyword,
        completedFrom,
        completedTo,
        filters.keyword,
        filters.completed_from,
        filters.completed_to,
    ]);

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
                                value={keyword}
                                onChange={(event) =>
                                    setKeyword(event.target.value)
                                }
                                placeholder="案件ID、会社名、現場名、受付内容"
                            />
                            <Input
                                aria-label="完了日開始"
                                type="date"
                                value={completedFrom}
                                onChange={(event) =>
                                    setCompletedFrom(event.target.value)
                                }
                            />
                            <Input
                                aria-label="完了日終了"
                                type="date"
                                value={completedTo}
                                onChange={(event) =>
                                    setCompletedTo(event.target.value)
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
