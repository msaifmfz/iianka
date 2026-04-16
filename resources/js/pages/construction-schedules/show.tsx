import { Head, Link, router } from '@inertiajs/react';
import { FileText, MapPin, Pencil, Phone, Trash2, Users } from 'lucide-react';
import {
    destroy as scheduleDestroy,
    edit as scheduleEdit,
    index as scheduleIndex,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { FloatingBackButton } from '@/components/floating-back-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ConstructionSchedule } from '@/types';

type Props = {
    schedule: ConstructionSchedule;
    canManage: boolean;
    returnTo: string | null;
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

function phoneHref(phone: string) {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

export default function ConstructionScheduleShow({
    schedule,
    canManage,
    returnTo,
}: Props) {
    const primaryGuideFile = schedule.guide_files[0] ?? null;
    const guideFilesHref =
        schedule.guide_files.length === 1 && primaryGuideFile !== null
            ? primaryGuideFile.url
            : '#guide-files';
    const fallbackReturnTo = scheduleIndex({
        query: {
            range: 'today',
            date: schedule.scheduled_on,
        },
    });

    function handleReturnToIndex() {
        if (typeof window !== 'undefined' && returnTo !== null) {
            router.visit(returnTo);

            return;
        }

        router.visit(fallbackReturnTo);
    }

    function deleteSchedule() {
        if (!confirm('この工事予定を削除しますか？')) {
            return;
        }

        router.delete(scheduleDestroy.url(schedule.id));
    }

    return (
        <>
            <Head title={`${schedule.location} - 予定詳細`} />
            <FloatingBackButton
                onClick={handleReturnToIndex}
                className="bottom-[calc(5.75rem+env(safe-area-inset-bottom))] md:bottom-6 xl:bottom-8"
            />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 pb-28 md:p-6 md:pb-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={scheduleEdit(schedule.id)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={deleteSchedule}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        </div>
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
                        {schedule.assigned_users.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Users className="size-4 text-muted-foreground" />
                                {schedule.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </div>
                        )}

                        {schedule.subcontractors.length > 0 && (
                            <div className="rounded-2xl border p-4 dark:border-neutral-800">
                                <p className="text-sm text-muted-foreground">
                                    下請け
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {schedule.subcontractors.map(
                                        (subcontractor) =>
                                            subcontractor.phone ? (
                                                <a
                                                    key={subcontractor.id}
                                                    href={phoneHref(
                                                        subcontractor.phone,
                                                    )}
                                                    className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition hover:bg-muted hover:text-sky-700 dark:border-neutral-800 dark:hover:text-sky-300"
                                                >
                                                    <Phone className="size-4 text-muted-foreground" />
                                                    <span>
                                                        {subcontractor.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {subcontractor.phone}
                                                    </span>
                                                </a>
                                            ) : (
                                                <span
                                                    key={subcontractor.id}
                                                    className="inline-flex items-center rounded-md border px-3 py-2 text-sm font-medium dark:border-neutral-800"
                                                >
                                                    {subcontractor.name}
                                                </span>
                                            ),
                                    )}
                                </div>
                            </div>
                        )}
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
                                label="現場担当者"
                                value={schedule.person_in_charge}
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
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        <Button asChild variant="outline" className="w-full">
                            <Link href={scheduleEdit(schedule.id)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={deleteSchedule}
                        >
                            <Trash2 className="size-4" />
                            削除
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

ConstructionScheduleShow.layout = {
    breadcrumbs: [
        {
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
