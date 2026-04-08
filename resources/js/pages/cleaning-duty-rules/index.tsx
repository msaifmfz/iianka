import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Pencil, Plus, Users } from 'lucide-react';
import {
    create as cleaningDutyRuleCreate,
    edit as cleaningDutyRuleEdit,
} from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { CleaningDutyRule } from '@/types';

type Props = {
    rules: CleaningDutyRule[];
};

export default function CleaningDutyRuleIndex({ rules }: Props) {
    return (
        <>
            <Head title="掃除当番設定" />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Cleaning Duty Rules
                        </p>
                        <h1 className="text-2xl font-bold">掃除当番設定</h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={scheduleIndex()}>
                                <ArrowLeft className="size-4" />
                                予定表へ戻る
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={cleaningDutyRuleCreate()}>
                                <Plus className="size-4" />
                                新規設定
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3">
                    {rules.length === 0 ? (
                        <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground dark:border-neutral-800">
                            掃除当番設定はまだありません。
                        </div>
                    ) : (
                        rules.map((rule) => (
                            <Card key={rule.id} className="shadow-sm">
                                <CardHeader className="flex flex-row items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {rule.weekday_label}
                                        </p>
                                        <CardTitle className="mt-1 flex items-center gap-2">
                                            <ClipboardList className="size-5 text-emerald-600" />
                                            {rule.label}
                                        </CardTitle>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`rounded-full px-3 py-1 text-xs font-semibold ${rule.is_active ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-neutral-100 text-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'}`}
                                        >
                                            {rule.is_active ? '有効' : '無効'}
                                        </span>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
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
                                        <div className="flex flex-wrap items-center gap-2 text-muted-foreground">
                                            <Users className="size-4" />
                                            {rule.assigned_users
                                                .map((user) => user.name)
                                                .join('、')}
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
            title: 'メニュー',
            href: dashboard(),
        },
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
