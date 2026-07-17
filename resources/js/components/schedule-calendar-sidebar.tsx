import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import type { RefObject } from 'react';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import {
    adjacentBusinessMonth,
    businessDateString,
    businessMonthTitle,
    parseBusinessDate,
} from '@/lib/dates';
import {
    allScheduleTypesSelected,
    formatScheduleDate,
    scheduleQuery,
    toggleScheduleType,
} from '@/lib/schedule-index';
import type {
    CalendarDay,
    ScheduleIndexFilters,
    ScheduleTypeFilter,
} from '@/lib/schedule-index';

export type CalendarScope = 'all' | 'mine';

export type CalendarScopeOption = {
    value: CalendarScope;
    label: string;
    description: string;
    count: number;
};

function adjacentYearDate(selectedDate: string, offset: number) {
    const date = parseBusinessDate(selectedDate);

    return businessDateString(
        new Date(
            Date.UTC(date.getUTCFullYear() + offset, date.getUTCMonth(), 1, 12),
        ),
    );
}

function yearDate(selectedDate: string, year: number) {
    const date = parseBusinessDate(selectedDate);

    return businessDateString(
        new Date(Date.UTC(year, date.getUTCMonth(), 1, 12)),
    );
}

function surroundingYears(selectedDate: string) {
    const year = parseBusinessDate(selectedDate).getUTCFullYear();

    return Array.from({ length: 9 }, (_, index) => year - 4 + index);
}

type MonthDay = {
    date: string;
    label: number;
    count: number;
    constructionCount: number;
    businessCount: number;
    internalNoticeCount: number;
    cleaningDutyCount: number;
    isSelected: boolean;
    isToday: boolean;
    isCurrentMonth: boolean;
    isSunday: boolean;
};

function monthDays(selectedDate: string, calendarDays: CalendarDay[]) {
    const date = parseBusinessDate(selectedDate);
    const first = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1, 12),
    );
    const last = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0, 12),
    );
    const visibleStart = new Date(first);
    const visibleEnd = new Date(last);
    const counts = new Map(calendarDays.map((day) => [day.date, day]));
    const today = businessDateString(new Date());

    visibleStart.setUTCDate(first.getUTCDate() - first.getUTCDay());
    visibleEnd.setUTCDate(last.getUTCDate() + (6 - last.getUTCDay()));

    const days: MonthDay[] = [];
    const current = new Date(visibleStart);

    while (current <= visibleEnd) {
        const key = businessDateString(current);
        const count = counts.get(key);
        days.push({
            date: key,
            label: current.getUTCDate(),
            count: count?.count ?? 0,
            constructionCount: count?.construction_count ?? 0,
            businessCount: count?.business_count ?? 0,
            internalNoticeCount: count?.internal_notice_count ?? 0,
            cleaningDutyCount: count?.cleaning_duty_count ?? 0,
            isSelected: key === selectedDate,
            isToday: key === today,
            isCurrentMonth: current.getUTCMonth() === date.getUTCMonth(),
            isSunday: current.getUTCDay() === 0,
        });
        current.setUTCDate(current.getUTCDate() + 1);
    }

    return days;
}

function calendarDayClass(day: MonthDay) {
    if (day.isSelected) {
        return 'bg-amber-500 text-white';
    }

    if (!day.isCurrentMonth) {
        return day.isSunday
            ? 'bg-rose-50/60 text-rose-300 hover:bg-rose-100 dark:bg-rose-950/20 dark:text-rose-800 dark:hover:bg-rose-950/40'
            : 'bg-neutral-50 text-neutral-400 hover:bg-neutral-100 dark:bg-neutral-950 dark:text-neutral-600 dark:hover:bg-neutral-900';
    }

    if (day.isSunday) {
        return 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-200 dark:hover:bg-rose-950';
    }

    if (day.isToday) {
        return 'bg-amber-50 text-amber-900 ring-1 ring-amber-300 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-100 dark:ring-amber-800 dark:hover:bg-amber-950/60';
    }

    return 'bg-neutral-100 hover:bg-amber-100 dark:bg-neutral-900 dark:hover:bg-amber-950';
}

function RangeLink({
    label,
    range,
    filters,
    className = '',
}: {
    label: string;
    range: ScheduleIndexFilters['range'];
    filters: ScheduleIndexFilters;
    className?: string;
}) {
    const active = filters.range === range;

    return (
        <Link
            href={scheduleIndex({
                query: scheduleQuery(filters, {
                    range,
                }),
            })}
            className={`rounded-full px-4 py-2 text-sm font-medium transition ${active ? 'bg-neutral-950 text-white dark:bg-white dark:text-neutral-950' : 'bg-white/80 text-neutral-700 ring-1 ring-neutral-200 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-800'} ${className}`}
            preserveScroll
        >
            {label}
        </Link>
    );
}

function TypeLink({
    label,
    type,
    filters,
}: {
    label: string;
    type: ScheduleTypeFilter;
    filters: ScheduleIndexFilters;
}) {
    const active =
        type === 'all'
            ? allScheduleTypesSelected(filters.type)
            : filters.type.includes(type);

    return (
        <Link
            href={scheduleIndex({
                query: scheduleQuery(filters, {
                    type: toggleScheduleType(filters.type, type),
                }),
            })}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${active ? 'bg-amber-500 text-white' : 'bg-white/80 text-neutral-700 ring-1 ring-neutral-200 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-800'}`}
            preserveScroll
        >
            {label}
        </Link>
    );
}

type Props = {
    filters: ScheduleIndexFilters;
    scheduleNavigation: {
        previous_date: string | null;
        next_date: string | null;
    };
    calendarDays: CalendarDay[];
    calendarScope: CalendarScope;
    calendarScopeOptions: CalendarScopeOption[];
    onChangeCalendarScope: (scope: CalendarScope) => void;
    hasSelectedUserFilter: boolean;
    calendarAreaRef: RefObject<HTMLDivElement | null>;
};

/** Month calendar, type/range filters, and scope toggle for the schedule index. */
export function ScheduleCalendarSidebar({
    filters,
    scheduleNavigation,
    calendarDays,
    calendarScope,
    calendarScopeOptions,
    onChangeCalendarScope,
    hasSelectedUserFilter,
    calendarAreaRef,
}: Props) {
    const days = monthDays(filters.date, calendarDays);
    const previousMonthDate = adjacentBusinessMonth(filters.date, -1);
    const nextMonthDate = adjacentBusinessMonth(filters.date, 1);
    const previousYearDate = adjacentYearDate(filters.date, -1);
    const nextYearDate = adjacentYearDate(filters.date, 1);
    const previousDecadeDate = adjacentYearDate(filters.date, -10);
    const nextDecadeDate = adjacentYearDate(filters.date, 10);
    const selectedYear = parseBusinessDate(filters.date).getUTCFullYear();
    const yearOptions = surroundingYears(filters.date);
    const monthTitle = businessMonthTitle(filters.date);

    return (
        <aside className="order-1 space-y-4 xl:order-1">
            <div className="rounded-3xl border bg-white/85 p-5 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Unified Schedule
                        </p>
                        <h1 className="text-2xl font-bold">予定表</h1>
                    </div>
                    <CalendarDays className="size-8 text-amber-600" />
                </div>
                <p className="mt-3 text-sm text-muted-foreground">
                    {formatScheduleDate(filters.starts_on)} -{' '}
                    {formatScheduleDate(filters.ends_on)}
                </p>
                <div className="mt-4 grid grid-cols-2 gap-2">
                    {scheduleNavigation.previous_date === null ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="justify-start rounded-full"
                            disabled
                        >
                            <ChevronLeft className="size-4" />
                            前の予定日
                        </Button>
                    ) : (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="justify-start rounded-full"
                        >
                            <Link
                                href={scheduleIndex({
                                    query: scheduleQuery(filters, {
                                        range: 'today',
                                        date: scheduleNavigation.previous_date,
                                    }),
                                })}
                                preserveScroll
                            >
                                <ChevronLeft className="size-4" />
                                前の予定日
                            </Link>
                        </Button>
                    )}
                    {scheduleNavigation.next_date === null ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="justify-end rounded-full"
                            disabled
                        >
                            次の予定日
                            <ChevronRight className="size-4" />
                        </Button>
                    ) : (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="justify-end rounded-full"
                        >
                            <Link
                                href={scheduleIndex({
                                    query: scheduleQuery(filters, {
                                        range: 'today',
                                        date: scheduleNavigation.next_date,
                                    }),
                                })}
                                preserveScroll
                            >
                                次の予定日
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    )}
                </div>
                <div
                    ref={calendarAreaRef}
                    className="mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-800"
                >
                    <div className="flex flex-wrap gap-2">
                        <TypeLink label="すべて" type="all" filters={filters} />
                        <TypeLink
                            label="工事"
                            type="construction"
                            filters={filters}
                        />
                        <TypeLink
                            label="業務予定"
                            type="business"
                            filters={filters}
                        />
                        <TypeLink
                            label="業務連絡"
                            type="internal_notice"
                            filters={filters}
                        />
                        <TypeLink
                            label="掃除当番"
                            type="cleaning_duty"
                            filters={filters}
                        />
                    </div>
                    <div className="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <span className="size-2 rounded-full bg-orange-500" />
                            工事
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <span className="size-2 rounded-full bg-violet-500" />
                            業務予定
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <span className="size-2 rounded-full bg-sky-500" />
                            業務連絡
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            掃除当番
                        </span>
                    </div>
                    <div className="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-1 dark:border-neutral-800 dark:bg-neutral-900/70">
                        <div className="grid grid-cols-2 gap-1">
                            {calendarScopeOptions.map((option) => {
                                const selected = calendarScope === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() =>
                                            onChangeCalendarScope(option.value)
                                        }
                                        className={`rounded-md px-3 py-2.5 text-left transition focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-50 focus-visible:outline-none dark:focus-visible:ring-offset-neutral-950 ${selected ? 'bg-white text-neutral-950 shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-950 dark:text-white dark:ring-neutral-800' : 'text-muted-foreground hover:bg-white/70 hover:text-neutral-900 dark:hover:bg-neutral-950/70 dark:hover:text-white'}`}
                                        aria-pressed={selected}
                                    >
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-semibold">
                                                {option.label}
                                            </span>
                                            <span
                                                className={`rounded-md px-2 py-0.5 text-[10px] font-bold ${selected ? 'bg-amber-500 text-white dark:bg-amber-400 dark:text-neutral-950' : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200'}`}
                                            >
                                                {option.count}件
                                            </span>
                                        </span>
                                        <span className="mt-1 block text-xs">
                                            {option.description}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    <div className="mt-5 flex items-center justify-between gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="rounded-full"
                        >
                            <Link
                                href={scheduleIndex({
                                    query: scheduleQuery(filters, {
                                        range: 'month',
                                        date: previousMonthDate,
                                    }),
                                })}
                                preserveScroll
                                aria-label="前月へ"
                            >
                                <ChevronLeft className="size-4" />
                                前月
                            </Link>
                        </Button>
                        <div className="flex flex-col items-center gap-2">
                            <details className="group relative">
                                <summary
                                    className="flex cursor-pointer list-none items-center justify-center gap-1 rounded-full px-3 py-1.5 text-center font-semibold transition hover:bg-neutral-100 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:outline-none dark:hover:bg-neutral-900 dark:focus-visible:ring-offset-neutral-950 [&::-webkit-details-marker]:hidden"
                                    aria-label="年を選択"
                                >
                                    <span>{monthTitle}</span>
                                    <ChevronDown className="size-4 transition group-open:rotate-180" />
                                </summary>
                                <div className="absolute left-1/2 z-20 mt-2 w-[min(18rem,calc(100vw-2rem))] -translate-x-1/2 rounded-2xl border bg-white p-3 shadow-xl dark:border-neutral-800 dark:bg-neutral-950">
                                    <p className="text-center text-xs font-medium text-muted-foreground">
                                        年を選択
                                    </p>
                                    <div className="mt-3 grid grid-cols-4 gap-2">
                                        {[
                                            {
                                                label: '10年前',
                                                date: previousDecadeDate,
                                            },
                                            {
                                                label: '前年',
                                                date: previousYearDate,
                                            },
                                            {
                                                label: '翌年',
                                                date: nextYearDate,
                                            },
                                            {
                                                label: '10年後',
                                                date: nextDecadeDate,
                                            },
                                        ].map((item) => (
                                            <Link
                                                key={item.label}
                                                href={scheduleIndex({
                                                    query: {
                                                        range: 'month',
                                                        date: item.date,
                                                        type: filters.type,
                                                        user_ids:
                                                            filters.user_ids,
                                                    },
                                                })}
                                                className="rounded-xl bg-neutral-100 px-2 py-2 text-center text-xs font-medium transition hover:bg-amber-100 dark:bg-neutral-900 dark:hover:bg-amber-950"
                                                preserveScroll
                                            >
                                                {item.label}
                                            </Link>
                                        ))}
                                    </div>
                                    <div className="mt-3 grid grid-cols-3 gap-2">
                                        {yearOptions.map((year) => (
                                            <Link
                                                key={year}
                                                href={scheduleIndex({
                                                    query: {
                                                        range: 'month',
                                                        date: yearDate(
                                                            filters.date,
                                                            year,
                                                        ),
                                                        type: filters.type,
                                                        user_ids:
                                                            filters.user_ids,
                                                    },
                                                })}
                                                className={`rounded-xl px-3 py-2 text-center text-sm font-semibold transition ${year === selectedYear ? 'bg-amber-500 text-white' : 'bg-neutral-100 hover:bg-amber-100 dark:bg-neutral-900 dark:hover:bg-amber-950'}`}
                                                preserveScroll
                                                aria-current={
                                                    year === selectedYear
                                                        ? 'date'
                                                        : undefined
                                                }
                                            >
                                                {year}年
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </details>
                            <Button
                                asChild
                                variant="secondary"
                                size="sm"
                                className="rounded-full"
                            >
                                <Link
                                    href={scheduleIndex({
                                        query: {
                                            range: 'today',
                                            type: filters.type,
                                            user_ids: filters.user_ids,
                                        },
                                    })}
                                    preserveScroll
                                    aria-label="今日へ移動"
                                >
                                    今日へ
                                </Link>
                            </Button>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="rounded-full"
                        >
                            <Link
                                href={scheduleIndex({
                                    query: scheduleQuery(filters, {
                                        range: 'month',
                                        date: nextMonthDate,
                                    }),
                                })}
                                preserveScroll
                                aria-label="翌月へ"
                            >
                                翌月
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                    <div className="mt-3 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                        {['日', '月', '火', '水', '木', '金', '土'].map(
                            (day) => (
                                <span
                                    key={day}
                                    className={
                                        day === '日'
                                            ? 'font-semibold text-rose-600 dark:text-rose-300'
                                            : undefined
                                    }
                                >
                                    {day}
                                </span>
                            ),
                        )}
                    </div>
                    <div className="mt-2 grid grid-cols-7 gap-1">
                        {days.map((day) => (
                            <Link
                                key={day.date}
                                href={scheduleIndex({
                                    query: scheduleQuery(filters, {
                                        range: 'today',
                                        date: day.date,
                                    }),
                                })}
                                className={`relative flex aspect-square items-center justify-center rounded-xl text-sm font-medium transition ${calendarDayClass(day)}`}
                                preserveScroll
                                aria-current={
                                    day.isSelected ? 'date' : undefined
                                }
                                aria-label={`${day.date} (${day.count}件)`}
                            >
                                {day.label}
                                {day.count > 0 && (
                                    <span
                                        className={`absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-bold ${day.isSelected ? 'bg-white text-amber-700' : day.isCurrentMonth ? 'bg-gray-500 text-white' : 'bg-neutral-400 text-white dark:bg-neutral-600'}`}
                                    >
                                        {day.count}
                                    </span>
                                )}
                                {(day.constructionCount > 0 ||
                                    day.businessCount > 0 ||
                                    day.internalNoticeCount > 0 ||
                                    day.cleaningDutyCount > 0) && (
                                    <span className="absolute bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5">
                                        {day.constructionCount > 0 && (
                                            <span className="size-1.5 rounded-full bg-orange-500" />
                                        )}
                                        {day.businessCount > 0 && (
                                            <span className="size-1.5 rounded-full bg-violet-500" />
                                        )}
                                        {day.internalNoticeCount > 0 && (
                                            <span className="size-1.5 rounded-full bg-sky-500" />
                                        )}
                                        {day.cleaningDutyCount > 0 && (
                                            <span className="size-1.5 rounded-full bg-emerald-500" />
                                        )}
                                    </span>
                                )}
                            </Link>
                        ))}
                    </div>
                </div>
                <div className="sticky top-3 z-10 rounded-2xl bg-white/85 p-2 py-3 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/85">
                    <div className="flex gap-2">
                        <RangeLink
                            label="24時間表示"
                            range="today"
                            filters={filters}
                            className="flex-1 text-center"
                        />
                        <RangeLink
                            label="週表示"
                            range="week"
                            filters={filters}
                            className="flex-1 text-center"
                        />
                        <RangeLink
                            label="月表示"
                            range="month"
                            filters={filters}
                            className="flex-1 text-center"
                        />
                    </div>
                    {!hasSelectedUserFilter && (
                        <p className="px-2 pt-2 text-xs text-muted-foreground">
                            優先表示: 自分の担当、業務連絡、未確認の現場対応
                        </p>
                    )}
                </div>
            </div>
        </aside>
    );
}
