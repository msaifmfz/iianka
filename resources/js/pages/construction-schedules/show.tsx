import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, MapPin, Pencil, Users } from 'lucide-react';
import {
    edit as scheduleEdit,
    index as scheduleIndex,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { ConstructionSchedule } from '@/types';

type Props = {
    schedule: ConstructionSchedule;
    canManage: boolean;
};

const statusLabels: Record<ConstructionSchedule['status'], string> = {
    scheduled: '予定',
    confirmed: '確定',
    postponed: '延期',
    canceled: '中止',
};

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-2xl bg-neutral-50 p-4 dark:bg-neutral-900">
            <p className="text-sm text-muted-foreground">{label}</p>
            <div className="mt-1 font-medium">{value || '未設定'}</div>
        </div>
    );
}

export default function ConstructionScheduleShow({
    schedule,
    canManage,
}: Props) {
    const primaryGuideFile = schedule.guide_files[0] ?? null;
    const guideFilesHref =
        schedule.guide_files.length === 1 && primaryGuideFile !== null
            ? primaryGuideFile.url
            : '#guide-files';

    return (
        <>
            <Head title={`${schedule.location} - 予定詳細`} />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 pb-28 md:p-6 md:pb-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link
                            href={scheduleIndex({
                                query: {
                                    range: 'today',
                                    date: schedule.scheduled_on,
                                },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            予定表へ戻る
                        </Link>
                    </Button>
                    {canManage && (
                        <Button asChild>
                            <Link href={scheduleEdit(schedule.id)}>
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
                            <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                {statusLabels[schedule.status]}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 xl:px-8">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                            <Detail
                                label="集合場所"
                                value={schedule.meeting_place}
                            />
                            <Detail label="人員" value={schedule.personnel} />
                            <Detail
                                label="ゼネコン会社"
                                value={schedule.general_contractor}
                            />
                            <Detail
                                label="担当"
                                value={schedule.person_in_charge}
                            />
                            <Detail
                                label="現場ライブラリ"
                                value={schedule.site?.name}
                            />
                            <Detail
                                label="ナビ住所"
                                value={schedule.navigation_address}
                            />
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <p className="text-sm text-muted-foreground">
                                内容
                            </p>
                            <p className="mt-2 leading-7 whitespace-pre-line">
                                {schedule.content || '未設定'}
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

                        {schedule.google_maps_url && (
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Button
                                    asChild
                                    className="min-h-11 justify-center sm:justify-start"
                                >
                                    <a
                                        href={schedule.google_maps_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <MapPin className="size-4" />
                                        Google Mapで開く
                                    </a>
                                </Button>
                            </div>
                        )}

                        <section id="guide-files" className="space-y-3">
                            <h2 className="font-semibold">現場案内図</h2>
                            {schedule.guide_files.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground dark:border-neutral-800">
                                    案内図は登録されていません。
                                </p>
                            ) : (
                                <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                    {schedule.guide_files.map((file) => (
                                        <Button
                                            key={file.id}
                                            asChild
                                            variant="outline"
                                            className="justify-start"
                                        >
                                            <a
                                                href={file.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText className="size-4" />
                                                {file.name}
                                            </a>
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </section>
                    </CardContent>
                </Card>
            </div>

            <div className="fixed right-0 bottom-0 left-0 z-20 border-t border-neutral-200 bg-white/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-2xl backdrop-blur md:hidden dark:border-neutral-800 dark:bg-neutral-950/95">
                <div className="grid grid-cols-2 gap-2">
                    {schedule.google_maps_url ? (
                        <Button asChild className="min-h-11">
                            <a
                                href={schedule.google_maps_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MapPin className="size-4" />
                                ナビ
                            </a>
                        </Button>
                    ) : (
                        <Button className="min-h-11" disabled>
                            <MapPin className="size-4" />
                            ナビなし
                        </Button>
                    )}
                    {schedule.guide_files.length === 0 ? (
                        <Button variant="outline" className="min-h-11" disabled>
                            <FileText className="size-4" />
                            案内図 0
                        </Button>
                    ) : (
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={guideFilesHref}
                                target={
                                    schedule.guide_files.length === 1
                                        ? '_blank'
                                        : undefined
                                }
                                rel={
                                    schedule.guide_files.length === 1
                                        ? 'noreferrer'
                                        : undefined
                                }
                            >
                                <FileText className="size-4" />
                                案内図 {schedule.guide_files.length}
                            </a>
                        </Button>
                    )}
                </div>
                {canManage && (
                    <Button asChild variant="outline" className="mt-2 w-full">
                        <Link href={scheduleEdit(schedule.id)}>
                            <Pencil className="size-4" />
                            編集
                        </Link>
                    </Button>
                )}
            </div>
        </>
    );
}

ConstructionScheduleShow.layout = {
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
