import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Pencil, Users } from 'lucide-react';
import { edit as cleaningDutyRuleEdit } from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { FloatingBackButton } from '@/components/floating-back-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CleaningDutyRule } from '@/types';

type Props = {
    rule: CleaningDutyRule;
    canManage: boolean;
    returnTo: string | null;
    scheduledOn: string | null;
};

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-neutral-50 p-4 dark:bg-neutral-900">
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="mt-1 font-medium">{value || '未設定'}</div>
        </div>
    );
}

export default function CleaningDutyRuleShow({
    rule,
    canManage,
    returnTo,
    scheduledOn,
}: Props) {
    const fallbackReturnTo = scheduleIndex({
        query: {
            range: 'today',
            ...(scheduledOn === null ? {} : { date: scheduledOn }),
            type: 'cleaning_duty',
        },
    });

    function handleReturnToIndex() {
        if (typeof window !== 'undefined' && returnTo !== null) {
            router.visit(returnTo);

            return;
        }

        router.visit(fallbackReturnTo);
    }

    return (
        <>
            <Head title={`${rule.label} - 掃除当番詳細`} />
            <FloatingBackButton onClick={handleReturnToIndex} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 pb-24 md:p-6 md:pb-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={cleaningDutyRuleEdit(rule.id)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <Card className="xl:rounded-3xl">
                    <CardHeader className="xl:px-8">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {scheduledOn ?? rule.weekday_label}
                                    {scheduledOn === null
                                        ? ''
                                        : ` / ${rule.weekday_label}`}
                                </p>
                                <CardTitle className="mt-2 text-3xl xl:text-4xl">
                                    {rule.label}
                                </CardTitle>
                            </div>
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                <ClipboardList className="size-4" />
                                掃除当番
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        {rule.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {rule.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                            <Detail label="場所" value={rule.location} />
                            <Detail
                                label="状態"
                                value={rule.is_active ? '有効' : '無効'}
                            />
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                内容
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {rule.notes || '未設定'}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CleaningDutyRuleShow.layout = {
    breadcrumbs: [
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
