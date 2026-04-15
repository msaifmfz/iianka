import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { businessDateString } from '@/lib/dates';
import { index as overviewIndex } from '@/routes/schedule-overview';

type CalendarDay = {
    date: string;
    construction_count: number;
    business_count: number;
    unconfirmed_voucher_count: number;
    schedule_count: number;
};

type Month = {
    starts_on: string;
    ends_on: string;
    calendar_starts_on: string;
    calendar_ends_on: string;
};

type Props = {
    filters: {
        date: string;
    };
    todayDate: string;
    month: Month;
    calendarDays: CalendarDay[];
};

type CalendarCell = CalendarDay & {
    label: number;
    weekday: number;
    isSelected: boolean;
    isToday: boolean;
    isCurrentMonth: boolean;
};

const weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];

function parseDate(date: string) {
    return new Date(`${date}T00:00:00`);
}

function formatInputDate(date: Date) {
    return businessDateString(date);
}

function adjacentMonthDate(selectedDate: string, offset: number) {
    const date = parseDate(selectedDate);

    return formatInputDate(
        new Date(date.getFullYear(), date.getMonth() + offset, 1),
    );
}

function monthTitle(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
    }).format(parseDate(date));
}

function detailDate(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    }).format(parseDate(date));
}

function calendarCells(
    selectedDate: string,
    todayDate: string,
    month: Month,
    calendarDays: CalendarDay[],
): CalendarCell[] {
    const monthStart = parseDate(month.starts_on);
    const counts = new Map(calendarDays.map((day) => [day.date, day]));
    const cells: CalendarCell[] = [];
    const current = parseDate(month.calendar_starts_on);
    const end = parseDate(month.calendar_ends_on);

    while (current <= end) {
        const date = formatInputDate(current);
        const day = counts.get(date) ?? {
            date,
            construction_count: 0,
            business_count: 0,
            unconfirmed_voucher_count: 0,
            schedule_count: 0,
        };

        cells.push({
            ...day,
            label: current.getDate(),
            weekday: current.getDay(),
            isSelected: date === selectedDate,
            isToday: date === todayDate,
            isCurrentMonth: current.getMonth() === monthStart.getMonth(),
        });
        current.setDate(current.getDate() + 1);
    }

    return cells;
}

function overviewQuery(date: string) {
    return {
        query: { date },
    };
}

function maxScheduleCount(days: CalendarCell[]) {
    return Math.max(...days.map((day) => day.schedule_count), 0);
}

function heatLevel(day: CalendarCell, maxCount: number) {
    if (day.schedule_count <= 0 || maxCount <= 0) {
        return 0;
    }

    if (maxCount <= 4) {
        return Math.min(day.schedule_count, 4);
    }

    const ratio = day.schedule_count / maxCount;

    if (ratio <= 0.25) {
        return 1;
    }

    if (ratio <= 0.5) {
        return 2;
    }

    if (ratio <= 0.75) {
        return 3;
    }

    return 4;
}

function heatLabel(level: number) {
    switch (level) {
        case 1:
            return '少なめ';
        case 2:
            return '通常';
        case 3:
            return '多め';
        case 4:
            return '非常に多い';
        default:
            return '予定なし';
    }
}

function heatCellClass(level: number) {
    switch (level) {
        case 1:
            return 'border-blue-200 bg-blue-50 text-blue-950 hover:border-blue-300 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-50 dark:hover:border-blue-800 dark:hover:bg-blue-950/50';
        case 2:
            return 'border-blue-300 bg-blue-100 text-blue-950 hover:border-blue-400 hover:bg-blue-200/70 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-50 dark:hover:border-blue-700 dark:hover:bg-blue-900/50';
        case 3:
            return 'border-blue-400 bg-blue-200 text-blue-950 hover:border-blue-500 hover:bg-blue-300/80 dark:border-blue-700 dark:bg-blue-900/60 dark:text-blue-50 dark:hover:border-blue-600 dark:hover:bg-blue-800/60';
        case 4:
            return 'border-blue-600 bg-blue-300 text-blue-950 hover:border-blue-700 hover:bg-blue-400/80 dark:border-blue-500 dark:bg-blue-800/70 dark:text-blue-50 dark:hover:border-blue-400 dark:hover:bg-blue-700/70';
        default:
            return 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950 dark:hover:border-neutral-700 dark:hover:bg-neutral-900';
    }
}

function dayCellClass(day: CalendarCell, maxCount: number) {
    const level = heatLevel(day, maxCount);
    const heatClass = heatCellClass(level);

    if (day.isSelected) {
        return `${heatClass} border-neutral-200 shadow-md ring-1 ring-neutral-200 dark:border-white dark:ring-white`;
    }

    if (!day.isCurrentMonth) {
        if (level > 0) {
            return `${heatClass} opacity-65`;
        }

        return 'border-neutral-200 bg-neutral-50 text-neutral-400 hover:border-neutral-300 hover:bg-white dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-600 dark:hover:border-neutral-700 dark:hover:bg-neutral-950';
    }

    return heatClass;
}

function metricTone(tone: 'neutral' | 'danger' = 'neutral') {
    if (tone === 'danger') {
        return 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100';
    }

    return '';
}

function MetricPill({
    label,
    value,
    tone = 'neutral',
    shortLabel,
    className = '',
}: {
    label: string;
    value: number | string;
    tone?: 'neutral' | 'danger';
    shortLabel?: string;
    className?: string;
}) {
    return (
        <span
            className={`inline-flex min-h-6 items-center justify-between gap-1 rounded-md px-1 py-0.5 text-[10px] leading-none font-semibold sm:min-h-7 sm:gap-2 sm:px-2.5 sm:py-1 sm:text-xs ${metricTone(tone)} ${className}`}
        >
            {shortLabel ? (
                <>
                    <span className="hidden sm:inline">{label}</span>
                    <span className="sm:hidden">{shortLabel}</span>
                </>
            ) : (
                <span>{label}</span>
            )}
            <span className="tabular-nums">{value}</span>
        </span>
    );
}

function confirmedVoucherCount(day: CalendarDay) {
    return Math.max(day.construction_count - day.unconfirmed_voucher_count, 0);
}

function voucherConfirmationValue(day: CalendarDay) {
    return `${confirmedVoucherCount(day)}/${day.construction_count}`;
}

export default function ScheduleOverviewIndex({
    filters,
    todayDate,
    month,
    calendarDays,
}: Props) {
    const selectedDay =
        calendarDays.find((day) => day.date === filters.date) ?? null;
    const cells = calendarCells(filters.date, todayDate, month, calendarDays);
    const busiestScheduleCount = maxScheduleCount(cells);
    const previousMonthDate = adjacentMonthDate(filters.date, -1);
    const nextMonthDate = adjacentMonthDate(filters.date, 1);
    const selectedDetail = selectedDay ?? {
        date: filters.date,
        construction_count: 0,
        business_count: 0,
        unconfirmed_voucher_count: 0,
        schedule_count: 0,
    };

    return (
        <>
            <Head title="予定カレンダー" />
            <main className="min-h-screen bg-neutral-100 p-3 text-neutral-950 md:p-6 dark:bg-neutral-950 dark:text-neutral-50">
                <div className="mx-auto flex max-w-7xl flex-col gap-4">
                    <header className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-start gap-3">
                                <span className="rounded-lg bg-teal-100 p-3 text-teal-800 dark:bg-teal-950 dark:text-teal-100">
                                    <CalendarDays className="size-6" />
                                </span>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Company Schedule
                                    </p>
                                    <h1 className="text-2xl font-bold tracking-normal">
                                        予定カレンダー
                                    </h1>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-md"
                                >
                                    <Link
                                        href={overviewIndex(
                                            overviewQuery(previousMonthDate),
                                        )}
                                    >
                                        <ChevronLeft className="size-4" />
                                        前月
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="secondary"
                                    className="rounded-md"
                                >
                                    <Link
                                        href={overviewIndex(
                                            overviewQuery(todayDate),
                                        )}
                                    >
                                        今日
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-md"
                                >
                                    <Link
                                        href={overviewIndex(
                                            overviewQuery(nextMonthDate),
                                        )}
                                    >
                                        翌月
                                        <ChevronRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </header>

                    <div className="grid gap-4 xl:grid-cols-[1fr_320px]">
                        <section className="rounded-lg border border-neutral-200 bg-white p-3 md:p-4 dark:border-neutral-800 dark:bg-neutral-950">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h2 className="text-xl font-bold">
                                    {monthTitle(filters.date)}
                                </h2>
                                <div
                                    className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                    aria-label="予定の多さ"
                                >
                                    予定の多さ:
                                    <span>少</span>
                                    {[0, 1, 2, 3, 4].map((level) => (
                                        <span
                                            key={level}
                                            className={`size-5 rounded-md border ${heatCellClass(level)}`}
                                            aria-label={heatLabel(level)}
                                        />
                                    ))}
                                    <span>多</span>
                                </div>
                            </div>

                            <div className="mt-4">
                                <div className="w-full">
                                    <div className="grid grid-cols-7 gap-0.5 text-center text-xs font-semibold text-muted-foreground sm:gap-1">
                                        {weekdayLabels.map((weekday, index) => (
                                            <span
                                                key={weekday}
                                                className={
                                                    index === 0
                                                        ? 'text-red-600 dark:text-red-300'
                                                        : undefined
                                                }
                                            >
                                                {weekday}
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-2 grid grid-cols-7 gap-0.5 sm:gap-1">
                                        {cells.map((day) => (
                                            <Link
                                                key={day.date}
                                                href={overviewIndex(
                                                    overviewQuery(day.date),
                                                )}
                                                className={`min-h-[5.75rem] rounded-lg p-1 transition focus-visible:ring-2 focus-visible:ring-neutral-950 focus-visible:ring-offset-2 focus-visible:outline-none sm:min-h-28 sm:p-2 dark:focus-visible:ring-white dark:focus-visible:ring-offset-neutral-950 ${dayCellClass(day, busiestScheduleCount)}`}
                                                aria-current={
                                                    day.isSelected
                                                        ? 'date'
                                                        : undefined
                                                }
                                                aria-label={`${day.date}: 混雑度${heatLabel(heatLevel(day, busiestScheduleCount))}、工事${day.construction_count}件、業務予定${day.business_count}件、伝票${voucherConfirmationValue(day)}、未確認伝票${day.unconfirmed_voucher_count}件`}
                                                preserveScroll
                                            >
                                                <span className="flex items-center justify-between gap-1">
                                                    <span
                                                        className={`flex size-6 items-center justify-center rounded-full text-xs font-bold tabular-nums sm:size-7 sm:text-sm ${day.isToday ? 'bg-neutral-800 text-white dark:bg-white dark:text-neutral-950' : ''}`}
                                                    >
                                                        {day.isToday
                                                            ? '今'
                                                            : day.label}
                                                    </span>
                                                </span>

                                                <span className="flex flex-col sm:mt-3">
                                                    <MetricPill
                                                        label="工"
                                                        value={
                                                            day.construction_count
                                                        }
                                                        shortLabel="工"
                                                    />
                                                    <MetricPill
                                                        label="業"
                                                        value={
                                                            day.business_count
                                                        }
                                                        shortLabel="業"
                                                    />
                                                    <span className="flex items-center gap-1">
                                                        <MetricPill
                                                            label="伝"
                                                            value={voucherConfirmationValue(
                                                                day,
                                                            )}
                                                            tone={
                                                                day.unconfirmed_voucher_count >
                                                                0
                                                                    ? 'danger'
                                                                    : 'neutral'
                                                            }
                                                            shortLabel="伝"
                                                            className="min-w-0 flex-1"
                                                        />
                                                    </span>
                                                </span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <aside className="rounded-lg border border-neutral-200 bg-white p-4 xl:sticky xl:top-4 xl:self-start dark:border-neutral-800 dark:bg-neutral-950">
                            <p className="text-sm text-muted-foreground">
                                選択日
                            </p>
                            <h2 className="mt-1 text-2xl font-bold">
                                {detailDate(selectedDetail.date)}
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                工事{selectedDetail.construction_count}
                                件、業務予定
                                {selectedDetail.business_count}
                                件、未確認伝票
                                {selectedDetail.unconfirmed_voucher_count}件
                            </p>

                            <div className="mt-5 grid gap-2">
                                <MetricPill
                                    label="工事"
                                    value={selectedDetail.construction_count}
                                />
                                <MetricPill
                                    label="業務予定"
                                    value={selectedDetail.business_count}
                                />
                                <MetricPill
                                    label="未確認伝票"
                                    value={
                                        selectedDetail.unconfirmed_voucher_count
                                    }
                                    tone={
                                        selectedDetail.unconfirmed_voucher_count >
                                        0
                                            ? 'danger'
                                            : 'neutral'
                                    }
                                />
                            </div>

                            <Button
                                asChild
                                className="mt-6 w-full rounded-md"
                                variant="outline"
                            >
                                <Link
                                    href={scheduleIndex({
                                        query: {
                                            range: 'today',
                                            date: selectedDetail.date,
                                            type: ['construction', 'business'],
                                        },
                                    })}
                                >
                                    予定一覧で確認
                                </Link>
                            </Button>
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}

ScheduleOverviewIndex.layout = {
    breadcrumbs: [
        {
            title: '予定カレンダー',
            href: overviewIndex(),
        },
    ],
};
