import { Link, router } from '@inertiajs/react';
import { CalendarDays, Clock3, Eye, Flag, Hash, UserRound } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import { show as receptionCaseShow } from '@/actions/App/Http/Controllers/ReceptionCaseController';
import receptionPriorityUpdate from '@/actions/App/Http/Controllers/ReceptionCasePriorityController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import {
    formatReceptionDate,
    formatReceptionDateTime,
    ReceptionPriorityBadge,
    ReceptionStatusBadge,
    useReceptionMeta,
} from '@/lib/reception';
import { cn } from '@/lib/utils';
import type { ReceptionCase, ReceptionCasePriority } from '@/types';
import type { QueryParams } from '@/wayfinder';

function PriorityQuickSelect({
    receptionCase,
}: {
    receptionCase: ReceptionCase;
}) {
    const { priorityOptions } = useReceptionMeta();
    const [priority, setPriority] = useState<ReceptionCasePriority>(
        receptionCase.priority,
    );
    const [isSaving, setIsSaving] = useState(false);

    if (!receptionCase.can.update_priority) {
        return null;
    }

    function updatePriority(event: ChangeEvent<HTMLSelectElement>) {
        const nextPriority = event.target.value as ReceptionCasePriority;

        setPriority(nextPriority);

        if (nextPriority === receptionCase.priority || isSaving) {
            return;
        }

        setIsSaving(true);

        router.patch(
            receptionPriorityUpdate.url(receptionCase.id),
            { priority: nextPriority },
            {
                preserveScroll: true,
                onError: () => setPriority(receptionCase.priority),
                onFinish: () => setIsSaving(false),
            },
        );
    }

    return (
        <label className="inline-flex items-center gap-2">
            <Flag className="size-4 text-muted-foreground" />
            <NativeSelect
                aria-label={`${receptionCase.case_number} 優先度`}
                className="h-8 w-auto min-w-20 px-2"
                disabled={isSaving}
                value={priority}
                onChange={updatePriority}
            >
                {priorityOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </NativeSelect>
        </label>
    );
}

export function ReceptionCaseList({
    title,
    cases,
    empty,
    showQuery,
    showScheduledOn = true,
    showLastActivityAt = true,
    children,
}: {
    title: string;
    cases: ReceptionCase[];
    empty: string;
    showQuery?: QueryParams;
    showScheduledOn?: boolean;
    showLastActivityAt?: boolean;
    children?: (receptionCase: ReceptionCase) => ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardTitle>{title}</CardTitle>
                <Badge variant="secondary">{cases.length}件</Badge>
            </CardHeader>
            <CardContent className="space-y-3">
                {cases.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                        {empty}
                    </div>
                ) : (
                    cases.map((receptionCase) => (
                        <div
                            key={receptionCase.id}
                            data-reception-case-id={receptionCase.id}
                            data-reception-case-item="true"
                            className={cn(
                                'rounded-lg border bg-background p-4 transition hover:border-amber-300 dark:hover:border-amber-700',
                                receptionCase.is_unseen &&
                                    'border-amber-300 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/20',
                            )}
                        >
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <ReceptionStatusBadge
                                            status={receptionCase.status}
                                        />
                                        <ReceptionPriorityBadge
                                            priority={receptionCase.priority}
                                        />
                                        {receptionCase.is_unseen && (
                                            <Badge>新着/更新</Badge>
                                        )}
                                        <span className="inline-flex items-center gap-1 text-sm font-semibold text-muted-foreground">
                                            <Hash className="size-3.5" />
                                            {receptionCase.case_number}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="truncate text-base font-semibold">
                                            {receptionCase.company_name ??
                                                '会社名未入力'}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {receptionCase.site_name ??
                                                '現場名未入力'}{' '}
                                            /{' '}
                                            {receptionCase.document_type
                                                ?.name ?? '案件書類未選択'}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <CalendarDays className="size-3.5" />
                                            期限{' '}
                                            {formatReceptionDate(
                                                receptionCase.due_on,
                                            )}
                                        </span>
                                        {showScheduledOn &&
                                            receptionCase.scheduled_on && (
                                                <span className="inline-flex items-center gap-1">
                                                    <CalendarDays className="size-3.5" />
                                                    予定日{' '}
                                                    {formatReceptionDate(
                                                        receptionCase.scheduled_on,
                                                    )}
                                                </span>
                                            )}
                                        <span className="inline-flex items-center gap-1">
                                            <UserRound className="size-3.5" />
                                            {receptionCase.assigned_user
                                                ?.name ?? '担当者未設定'}
                                        </span>
                                        {showLastActivityAt &&
                                            receptionCase.last_activity_at && (
                                                <span className="inline-flex items-center gap-1">
                                                    <Clock3 className="size-3.5" />
                                                    最終更新{' '}
                                                    {formatReceptionDateTime(
                                                        receptionCase.last_activity_at,
                                                    )}
                                                </span>
                                            )}
                                        {receptionCase.last_seen_at && (
                                            <span className="inline-flex items-center gap-1">
                                                <Eye className="size-3.5" />
                                                最終閲覧{' '}
                                                {formatReceptionDateTime(
                                                    receptionCase.last_seen_at,
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row lg:justify-end">
                                    <PriorityQuickSelect
                                        key={`${receptionCase.id}-${receptionCase.priority}`}
                                        receptionCase={receptionCase}
                                    />
                                    {children?.(receptionCase)}
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="sm:min-w-24"
                                    >
                                        <Link
                                            href={receptionCaseShow(
                                                receptionCase.id,
                                                showQuery
                                                    ? { query: showQuery }
                                                    : undefined,
                                            )}
                                        >
                                            開く
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
