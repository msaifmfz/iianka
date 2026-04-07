import { Head, Link, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Save,
    XCircle,
} from 'lucide-react';
import {
    index as voucherIndex,
    update as voucherUpdate,
} from '@/actions/App/Http/Controllers/ConstructionScheduleVoucherController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { dashboard } from '@/routes';
import type { VoucherConfirmationSchedule } from '@/types';

type CheckedFilter = 'all' | 'unchecked' | 'checked';

type Props = {
    filters: {
        checked: CheckedFilter;
        date: string;
        starts_on: string;
        ends_on: string;
    };
    summary: {
        total: number;
        checked: number;
        unchecked: number;
    };
    schedules: VoucherConfirmationSchedule[];
    canManage: boolean;
};

type VoucherForm = {
    voucher_checked: boolean;
    voucher_note: string;
};

const checkedLabels: Record<CheckedFilter, string> = {
    all: 'すべて',
    unchecked: '未確認',
    checked: '確認済み',
};

function formatDate(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        month: 'numeric',
        day: 'numeric',
        weekday: 'short',
    }).format(new Date(`${date}T00:00:00`));
}

function formatDateTime(dateTime: string | null) {
    if (dateTime === null) {
        return null;
    }

    return new Intl.DateTimeFormat('ja-JP', {
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(dateTime.replace(' ', 'T')));
}

function adjacentMonthDate(date: string, offset: number) {
    const value = new Date(`${date}T00:00:00`);
    value.setMonth(value.getMonth() + offset);

    return value.toISOString().slice(0, 10);
}

function filterQuery(
    filters: Props['filters'],
    query: Partial<Pick<Props['filters'], 'date' | 'checked'>>,
) {
    return {
        date: query.date ?? filters.date,
        checked: query.checked ?? filters.checked,
    };
}

function FilterLink({
    label,
    checked,
    filters,
}: {
    label: string;
    checked: CheckedFilter;
    filters: Props['filters'];
}) {
    const active = filters.checked === checked;

    return (
        <Button
            asChild
            variant={active ? 'default' : 'outline'}
            size="sm"
            className="rounded-full"
        >
            <Link
                href={voucherIndex({
                    query: filterQuery(filters, { checked }),
                })}
                preserveScroll
            >
                {label}
            </Link>
        </Button>
    );
}

function StatusBadge({ schedule }: { schedule: VoucherConfirmationSchedule }) {
    if (schedule.voucher_checked) {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                <CheckCircle2 className="size-3.5" />
                確認済み
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-200">
            <XCircle className="size-3.5" />
            未確認
        </span>
    );
}

function VoucherScheduleCard({
    schedule,
    canManage,
}: {
    schedule: VoucherConfirmationSchedule;
    canManage: boolean;
}) {
    const { data, setData, patch, processing, errors } = useForm<VoucherForm>({
        voucher_checked: schedule.voucher_checked,
        voucher_note: schedule.voucher_note ?? '',
    });
    const checkedAt = formatDateTime(schedule.voucher_checked_at);

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        patch(voucherUpdate.url(schedule.id), {
            preserveScroll: true,
        });
    }

    return (
        <Card className="overflow-hidden">
            <CardHeader className="gap-4 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0 space-y-2">
                    <StatusBadge schedule={schedule} />
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>{formatDate(schedule.scheduled_on)}</span>
                        <span>{schedule.time}</span>
                    </div>
                    <CardTitle className="text-xl">
                        {schedule.location}
                    </CardTitle>
                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        {schedule.general_contractor && (
                            <span>ゼネコン: {schedule.general_contractor}</span>
                        )}
                        {schedule.person_in_charge && (
                            <span>担当: {schedule.person_in_charge}</span>
                        )}
                        {schedule.assigned_users.length > 0 && (
                            <span>
                                予定担当:{' '}
                                {schedule.assigned_users
                                    .map((user) => user.name)
                                    .join('、')}
                            </span>
                        )}
                    </div>
                </div>
                {checkedAt && schedule.voucher_checked_by && (
                    <p className="shrink-0 rounded-2xl bg-neutral-100 px-3 py-2 text-xs text-muted-foreground dark:bg-neutral-900">
                        {schedule.voucher_checked_by.name} が {checkedAt} に確認
                    </p>
                )}
            </CardHeader>
            <CardContent className="space-y-4">
                {schedule.content && (
                    <p className="rounded-2xl border bg-neutral-50 p-3 text-sm leading-6 whitespace-pre-line dark:border-neutral-800 dark:bg-neutral-900/60">
                        {schedule.content}
                    </p>
                )}

                <form onSubmit={submit} className="grid gap-4">
                    <label className="flex items-center gap-3 rounded-2xl border p-4 text-sm font-medium dark:border-neutral-800">
                        <Checkbox
                            checked={data.voucher_checked}
                            disabled={!canManage || processing}
                            onCheckedChange={(checked) =>
                                setData('voucher_checked', checked === true)
                            }
                        />
                        <span>
                            確認済み
                            {!canManage && (
                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                    管理者のみ編集できます
                                </span>
                            )}
                        </span>
                    </label>

                    <label className="grid gap-2 text-sm font-medium">
                        <span>管理者メモ</span>
                        {canManage ? (
                            <textarea
                                className="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm"
                                value={data.voucher_note}
                                onChange={(event) =>
                                    setData('voucher_note', event.target.value)
                                }
                                placeholder="例: 日付・現場名を確認してください。"
                                disabled={processing}
                            />
                        ) : (
                            <div className="min-h-16 rounded-md border bg-neutral-50 px-3 py-2 text-sm leading-6 whitespace-pre-line text-muted-foreground dark:border-neutral-800 dark:bg-neutral-900">
                                {schedule.voucher_note || 'メモはありません。'}
                            </div>
                        )}
                        {errors.voucher_note && (
                            <span className="text-xs text-destructive">
                                {errors.voucher_note}
                            </span>
                        )}
                    </label>

                    {canManage && (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Save className="size-4" />
                                {processing ? '保存中...' : '保存'}
                            </Button>
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}

export default function VoucherConfirmationsIndex({
    filters,
    summary,
    schedules,
    canManage,
}: Props) {
    const previousMonthDate = adjacentMonthDate(filters.date, -1);
    const nextMonthDate = adjacentMonthDate(filters.date, 1);
    const monthTitle = new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
    }).format(new Date(`${filters.date}T00:00:00`));

    return (
        <>
            <Head title="伝票確認" />
            <div className="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="rounded-3xl border bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Voucher Confirmation
                            </p>
                            <h1 className="mt-1 flex items-center gap-2 text-2xl font-bold">
                                <ClipboardCheck className="size-7 text-amber-600" />
                                伝票確認
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {formatDate(filters.starts_on)} -{' '}
                                {formatDate(filters.ends_on)}
                            </p>
                        </div>
                        <div className="grid grid-cols-3 gap-2 text-center text-sm">
                            <div className="rounded-2xl bg-neutral-100 px-4 py-3 dark:bg-neutral-900">
                                <p className="text-xs text-muted-foreground">
                                    表示
                                </p>
                                <p className="font-semibold">{summary.total}</p>
                            </div>
                            <div className="rounded-2xl bg-emerald-100 px-4 py-3 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                <p className="text-xs">確認済み</p>
                                <p className="font-semibold">
                                    {summary.checked}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-rose-100 px-4 py-3 text-rose-800 dark:bg-rose-950 dark:text-rose-200">
                                <p className="text-xs">未確認</p>
                                <p className="font-semibold">
                                    {summary.unchecked}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                        <div className="flex flex-wrap gap-2">
                            {(
                                Object.keys(checkedLabels) as CheckedFilter[]
                            ).map((checked) => (
                                <FilterLink
                                    key={checked}
                                    label={checkedLabels[checked]}
                                    checked={checked}
                                    filters={filters}
                                />
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="rounded-full"
                            >
                                <Link
                                    href={voucherIndex({
                                        query: filterQuery(filters, {
                                            date: previousMonthDate,
                                        }),
                                    })}
                                    preserveScroll
                                >
                                    <ChevronLeft className="size-4" />
                                    前月
                                </Link>
                            </Button>
                            <div className="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-2 text-sm font-semibold dark:bg-neutral-900">
                                <CalendarDays className="size-4" />
                                {monthTitle}
                            </div>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="rounded-full"
                            >
                                <Link
                                    href={voucherIndex({
                                        query: filterQuery(filters, {
                                            date: nextMonthDate,
                                        }),
                                    })}
                                    preserveScroll
                                >
                                    翌月
                                    <ChevronRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                {schedules.length === 0 ? (
                    <div className="rounded-3xl border border-dashed p-10 text-center text-muted-foreground dark:border-neutral-800">
                        {filters.checked === 'all'
                            ? 'この月の工事予定はありません。'
                            : `${checkedLabels[filters.checked]}の工事予定はありません。`}
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {schedules.map((schedule) => (
                            <VoucherScheduleCard
                                key={schedule.id}
                                schedule={schedule}
                                canManage={canManage}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

VoucherConfirmationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: '伝票確認',
            href: voucherIndex(),
        },
    ],
};
