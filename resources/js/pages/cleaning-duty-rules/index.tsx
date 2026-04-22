import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Pencil, Plus, Users } from 'lucide-react';
import {
    create as cleaningDutyRuleCreate,
    edit as cleaningDutyRuleEdit,
} from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CleaningDutyRule } from '@/types';

type Props = {
    rules: CleaningDutyRule[];
    canManage: boolean;
};

export default function CleaningDutyRuleIndex({ rules, canManage }: Props) {
    return (
        <>
            <Head title="掃除当番設定" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <section className="rounded-2xl border bg-white p-5 shadow-sm sm:p-6 xl:p-7 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div className="max-w-3xl space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Cleaning Duty Rules
                            </p>
                            <h1 className="text-2xl font-bold tracking-tight xl:text-3xl">
                                掃除当番設定
                            </h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                曜日ごとの掃除当番と担当者を管理します。
                            </p>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="rounded-2xl border bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/70">
                                <p className="text-xs font-medium text-muted-foreground">
                                    登録設定
                                </p>
                                <p className="mt-1 text-2xl font-semibold">
                                    {rules.length}
                                </p>
                            </div>
                            <div className="grid gap-2 sm:flex sm:items-center">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full sm:w-auto"
                                >
                                    <Link href={scheduleIndex()}>
                                        <ArrowLeft className="size-4" />
                                        予定表へ戻る
                                    </Link>
                                </Button>
                                {canManage && (
                                    <Button
                                        asChild
                                        className="w-full sm:w-auto"
                                    >
                                        <Link href={cleaningDutyRuleCreate()}>
                                            <Plus className="size-4" />
                                            新規設定
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {rules.length === 0 ? (
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3 dark:border-neutral-800">
                            掃除当番設定はまだありません。
                        </div>
                    ) : (
                        rules.map((rule) => (
                            <Card
                                key={rule.id}
                                className="rounded-2xl border-neutral-200/80 shadow-sm dark:border-neutral-800"
                            >
                                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="text-sm text-muted-foreground">
                                            {rule.weekday_label}
                                        </p>
                                        <CardTitle className="mt-1 flex min-w-0 items-start gap-2 text-lg leading-6">
                                            <ClipboardList className="size-5 text-emerald-600" />
                                            <span className="min-w-0 break-words">
                                                {rule.label}
                                            </span>
                                        </CardTitle>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <span
                                            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${rule.is_active ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'}`}
                                        >
                                            {rule.is_active ? '有効' : '無効'}
                                        </span>
                                        {canManage && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="grow sm:grow-0"
                                            >
                                                <Link
                                                    href={cleaningDutyRuleEdit(
                                                        rule.id,
                                                    )}
                                                >
                                                    <Pencil className="size-4" />
                                                    編集
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    {rule.location && (
                                        <p>
                                            <span className="text-muted-foreground">
                                                場所:
                                            </span>{' '}
                                            {rule.location}
                                        </p>
                                    )}
                                    {rule.notes && (
                                        <p className="leading-6 whitespace-pre-line">
                                            {rule.notes}
                                        </p>
                                    )}
                                    {rule.assigned_users.length > 0 && (
                                        <div className="flex flex-wrap items-start gap-2 text-muted-foreground">
                                            <Users className="size-4" />
                                            <span className="min-w-0 flex-1 break-words">
                                                {rule.assigned_users
                                                    .map((user) => user.name)
                                                    .join('、')}
                                            </span>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

CleaningDutyRuleIndex.layout = {
    breadcrumbs: [
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
