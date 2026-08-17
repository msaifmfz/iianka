import { Link } from '@inertiajs/react';
import { ArrowUpRight, Inbox } from 'lucide-react';
import { show as receptionCaseShow } from '@/actions/App/Http/Controllers/ReceptionCaseController';
import { ReceptionStatusBadge } from '@/lib/reception';
import { cn } from '@/lib/utils';
import type { ReceptionScheduleSource } from '@/types';

export function ReceptionScheduleSourceBanner({
    source,
    className,
}: {
    source: ReceptionScheduleSource;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/70 dark:bg-amber-950/20',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                    <Inbox className="size-4" />
                </span>
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold">受付から作成</p>
                        <ReceptionStatusBadge status={source.status} />
                    </div>
                    <p className="truncate text-sm text-muted-foreground">
                        {source.company_name ?? '会社名未入力'} /{' '}
                        {source.site_name ?? '現場名未入力'}
                    </p>
                </div>
            </div>
            <Link
                href={receptionCaseShow(source.id)}
                className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-md text-sm font-semibold text-amber-900 underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:outline-none sm:self-auto dark:text-amber-100"
            >
                {source.case_number}
                <ArrowUpRight className="size-4" />
            </Link>
        </div>
    );
}
