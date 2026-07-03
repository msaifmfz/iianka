import { Head, Link } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';
import { create as receptionCreate } from '@/actions/App/Http/Controllers/ReceptionCaseController';
import { index as receptionCasesIndex } from '@/actions/App/Http/Controllers/ReceptionCaseController';
import { ReceptionCaseList } from '@/components/reception-case-list';
import { Button } from '@/components/ui/button';
import type { ReceptionCase } from '@/types';
import type { QueryParams } from '@/wayfinder';

type Props = {
    drafts: ReceptionCase[];
    assignedTasks: ReceptionCase[];
};

export default function ReceptionHome({ drafts, assignedTasks }: Props) {
    const homeShowQuery = { from: 'home' } satisfies QueryParams;

    return (
        <>
            <Head title="受付ホーム" />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Reception Home
                        </p>
                        <h1 className="text-2xl font-bold">受付ホーム</h1>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild variant="outline">
                            <Link href={receptionCasesIndex()}>
                                <ListChecks className="size-4" />
                                やること一覧
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={receptionCreate()}>
                                <Plus className="size-4" />
                                受付画面
                            </Link>
                        </Button>
                    </div>
                </div>

                <ReceptionCaseList
                    title="今日やること"
                    cases={assignedTasks}
                    empty="担当中の受付案件はありません。"
                    showQuery={homeShowQuery}
                />

                <ReceptionCaseList
                    title="My 受付下書き"
                    cases={drafts}
                    empty="受付下書きはありません。"
                    showQuery={homeShowQuery}
                />
            </div>
        </>
    );
}
