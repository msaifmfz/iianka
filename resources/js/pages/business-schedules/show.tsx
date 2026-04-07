import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, Pencil, Users } from 'lucide-react';
import { edit as businessScheduleEdit } from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { BusinessSchedule } from '@/types';

type Props = {
    schedule: BusinessSchedule;
    canManage: boolean;
};

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-neutral-50 p-4 dark:bg-neutral-900">
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="mt-1 font-medium">{value || '未設定'}</div>
        </div>
    );
}

export default function BusinessScheduleShow({ schedule, canManage }: Props) {
    return (
        <>
            <Head title={`${schedule.location} - 業務予定詳細`} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link
                            href={scheduleIndex({
                                query: {
                                    range: 'today',
                                    date: schedule.scheduled_on,
                                    type: 'all',
                                },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            予定表へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={businessScheduleEdit(schedule.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>

                <Card className="xl:rounded-3xl">
                    <CardHeader className="xl:px-8">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {schedule.scheduled_on} / {schedule.time}
                                </p>
                                <CardTitle className="mt-2 text-3xl xl:text-4xl">
                                    {schedule.location}
                                </CardTitle>
                            </div>
                            <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-3 py-1 text-sm font-semibold text-violet-900 dark:bg-violet-950 dark:text-violet-200">
                                <BriefcaseBusiness className="size-4" />
                                業務
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                            <Detail label="人員" value={schedule.personnel} />
                            <Detail
                                label="ゼネコン会社"
                                value={schedule.general_contractor}
                            />
                            <Detail
                                label="担当"
                                value={schedule.person_in_charge}
                            />
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                内容
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {schedule.content}
                            </p>
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                メモ
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {schedule.memo || '未設定'}
                            </p>
                        </div>

                        {schedule.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {schedule.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BusinessScheduleShow.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
