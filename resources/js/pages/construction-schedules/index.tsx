import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileText,
    Hammer,
    MapPin,
    Pencil,
    Plus,
    Users,
} from 'lucide-react';
import {
    create as businessScheduleCreate,
    edit as businessScheduleEdit,
    show as businessScheduleShow,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import {
    index as scheduleIndex,
    show as scheduleShow,
    create as scheduleCreate,
    edit as scheduleEdit,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { ConstructionSchedule, ScheduleEvent } from '@/types';

type CalendarDay = {
    date: string;
    count: number;
    construction_count: number;
    business_count: number;
};

type Filters = {
    range: 'today' | 'week' | 'month';
    type: 'all' | 'construction' | 'business';
    date: string;
    starts_on: string;
    ends_on: string;
};

type ScheduleNavigation = {
    previous_date: string | null;
    next_date: string | null;
};

type Props = {
    filters: Filters;
    calendarDays: CalendarDay[];
    scheduleNavigation: ScheduleNavigation;
    mySchedules: ScheduleEvent[];
    teamSchedules: ScheduleEvent[];
    canManage: boolean;
};

const statusLabels: Record<ConstructionSchedule['status'], string> = {
    scheduled: '予定',
    confirmed: '確定',
    postponed: '延期',
    canceled: '中止',
};

const statusClasses: Record<ConstructionSchedule['status'], string> = {
    scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    confirmed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    postponed:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    canceled: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200',
};

function formatDate(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    }).format(new Date(`${date}T00:00:00`));
}

function formatInputDate(date: Date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function adjacentMonthDate(selectedDate: string, offset: number) {
    const date = new Date(`${selectedDate}T00:00:00`);

    return formatInputDate(
        new Date(date.getFullYear(), date.getMonth() + offset, 1),
    );
}

function adjacentYearDate(selectedDate: string, offset: number) {
    const date = new Date(`${selectedDate}T00:00:00`);

    return formatInputDate(
        new Date(date.getFullYear() + offset, date.getMonth(), 1),
    );
}

function yearDate(selectedDate: string, year: number) {
    const date = new Date(`${selectedDate}T00:00:00`);

    return formatInputDate(new Date(year, date.getMonth(), 1));
}

function surroundingYears(selectedDate: string) {
    const year = new Date(`${selectedDate}T00:00:00`).getFullYear();

    return Array.from({ length: 9 }, (_, index) => year - 4 + index);
}

type MonthDay = {
    date: string;
    label: number;
    count: number;
    constructionCount: number;
    businessCount: number;
    isSelected: boolean;
    isToday: boolean;
    isCurrentMonth: boolean;
    isSunday: boolean;
};

function monthDays(selectedDate: string, calendarDays: CalendarDay[]) {
    const date = new Date(`${selectedDate}T00:00:00`);
    const first = new Date(date.getFullYear(), date.getMonth(), 1);
    const last = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    const visibleStart = new Date(first);
    const visibleEnd = new Date(last);
    const counts = new Map(calendarDays.map((day) => [day.date, day]));

    visibleStart.setDate(first.getDate() - first.getDay());
    visibleEnd.setDate(last.getDate() + (6 - last.getDay()));

    const days: MonthDay[] = [];
    const current = new Date(visibleStart);

    while (current <= visibleEnd) {
        const key = formatInputDate(current);
        const count = counts.get(key);
        days.push({
            date: key,
            label: current.getDate(),
            count: count?.count ?? 0,
            constructionCount: count?.construction_count ?? 0,
            businessCount: count?.business_count ?? 0,
            isSelected: key === selectedDate,
            isToday: key === formatInputDate(new Date()),
            isCurrentMonth: current.getMonth() === date.getMonth(),
            isSunday: current.getDay() === 0,
        });
        current.setDate(current.getDate() + 1);
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

function scheduleQuery(filters: Filters, query: Partial<Filters>) {
    return {
        range: query.range ?? filters.range,
        date: query.date ?? filters.date,
        type: query.type ?? filters.type,
    };
}

function RangeLink({
    label,
    range,
    filters,
    className = '',
}: {
    label: string;
    range: Filters['range'];
    filters: Filters;
    className?: string;
}) {
    const active = filters.range === range;

    return (
        <Link
            href={scheduleIndex({
                query: scheduleQuery(filters, { range }),
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
    type: Filters['type'];
    filters: Filters;
}) {
    const active = filters.type === type;

    return (
        <Link
            href={scheduleIndex({
                query: scheduleQuery(filters, { type }),
            })}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${active ? 'bg-amber-500 text-white' : 'bg-white/80 text-neutral-700 ring-1 ring-neutral-200 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-800'}`}
            preserveScroll
        >
            {label}
        </Link>
    );
}

function ScheduleCard({
    schedule,
    canManage,
}: {
    schedule: ScheduleEvent;
    canManage: boolean;
}) {
    const scheduleDetail =
        schedule.type === 'construction'
            ? scheduleShow(schedule.id)
            : businessScheduleShow(schedule.id);
    const scheduleEditHref =
        schedule.type === 'construction'
            ? scheduleEdit(schedule.id)
            : businessScheduleEdit(schedule.id);
    const typeBadge =
        schedule.type === 'construction'
            ? 'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200'
            : 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200';

    return (
        <Card className="gap-0 overflow-hidden border-neutral-200 bg-white/95 py-0 shadow-sm transition hover:border-neutral-300 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-950/85 dark:hover:border-neutral-700">
            <Link
                href={scheduleDetail}
                className="block rounded-xl transition hover:bg-neutral-50/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:hover:bg-neutral-900/40"
                aria-label={`${schedule.location}の予定詳細を見る`}
            >
                <CardHeader className="gap-3 p-4 md:p-6">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-sm text-muted-foreground">
                                {formatDate(schedule.scheduled_on)}
                            </p>
                            <CardTitle className="mt-1 text-xl leading-tight">
                                {schedule.location}
                            </CardTitle>
                            <p className="mt-2 text-xs font-medium text-sky-700 dark:text-sky-300">
                                詳細を見る
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            <span
                                className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ${typeBadge}`}
                            >
                                {schedule.type === 'construction' ? (
                                    <Hammer className="size-3.5" />
                                ) : (
                                    <BriefcaseBusiness className="size-3.5" />
                                )}
                                {schedule.type === 'construction'
                                    ? '工事'
                                    : '業務'}
                            </span>
                            {schedule.type === 'construction' && (
                                <span
                                    className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClasses[schedule.status]}`}
                                >
                                    {statusLabels[schedule.status]}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                            {schedule.time}
                        </span>
                        {schedule.general_contractor && (
                            <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                                {schedule.general_contractor}
                            </span>
                        )}
                        {schedule.person_in_charge && (
                            <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                                担当: {schedule.person_in_charge}
                            </span>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="space-y-4 p-4 pt-0 md:p-6 md:pt-0">
                    <div className="grid gap-2 text-sm md:grid-cols-2 md:gap-3">
                        {schedule.type === 'construction' && (
                            <div className="rounded-xl bg-neutral-50 p-3 dark:bg-neutral-900">
                                <p className="text-muted-foreground">
                                    集合場所
                                </p>
                                <p className="mt-1 font-medium">
                                    {schedule.meeting_place}
                                </p>
                            </div>
                        )}
                        <div className="rounded-xl bg-neutral-50 p-3 dark:bg-neutral-900">
                            <p className="text-muted-foreground">人員</p>
                            <p className="mt-1 font-medium">
                                {schedule.personnel || '未設定'}
                            </p>
                        </div>
                    </div>
                    <p className="line-clamp-3 text-sm leading-6 md:line-clamp-none">
                        {schedule.content}
                    </p>
                    {schedule.type === 'business' && schedule.memo && (
                        <p className="line-clamp-2 rounded-xl bg-violet-50 p-3 text-sm leading-6 text-violet-950 dark:bg-violet-950/30 dark:text-violet-100">
                            {schedule.memo}
                        </p>
                    )}
                    {schedule.assigned_users.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Users className="size-4" />
                            {schedule.assigned_users
                                .map((user) => user.name)
                                .join('、')}
                        </div>
                    )}
                </CardContent>
            </Link>
            <CardContent className="p-4 pt-0 md:p-6 md:pt-0">
                <div
                    className={
                        schedule.type === 'construction'
                            ? 'grid gap-2 sm:grid-cols-3'
                            : 'grid gap-2 sm:grid-cols-2'
                    }
                >
                    {schedule.type === 'construction' && (
                        <Button asChild className="min-h-11 justify-center">
                            <a
                                href={schedule.google_maps_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MapPin className="size-4" />
                                ナビ
                            </a>
                        </Button>
                    )}
                    <Button
                        asChild
                        variant="outline"
                        className="min-h-11 justify-center sm:justify-start"
                    >
                        <Link href={scheduleDetail}>
                            <FileText className="size-4" />
                            {schedule.type === 'construction'
                                ? `詳細・案内図 (${schedule.guide_files.length})`
                                : '詳細'}
                        </Link>
                    </Button>
                    {canManage && (
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 justify-center sm:justify-start"
                        >
                            <Link href={scheduleEditHref}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ScheduleSection({
    title,
    empty,
    schedules,
    canManage,
}: {
    title: string;
    empty: string;
    schedules: ScheduleEvent[];
    canManage: boolean;
}) {
    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">{title}</h2>
                <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-neutral-700 ring-1 ring-neutral-200 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-800">
                    {schedules.length}件
                </span>
            </div>
            {schedules.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-neutral-300 bg-white/70 p-8 text-center text-sm text-muted-foreground dark:border-neutral-800 dark:bg-neutral-950/60">
                    {empty}
                </div>
            ) : (
                <div className="space-y-3">
                    {schedules.map((schedule) => (
                        <ScheduleCard
                            key={`${schedule.type}-${schedule.id}`}
                            schedule={schedule}
                            canManage={canManage}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

export default function ConstructionSchedulesIndex({
    filters,
    calendarDays,
    scheduleNavigation,
    mySchedules,
    teamSchedules,
    canManage,
}: Props) {
    const days = monthDays(filters.date, calendarDays);
    const previousMonthDate = adjacentMonthDate(filters.date, -1);
    const nextMonthDate = adjacentMonthDate(filters.date, 1);
    const previousYearDate = adjacentYearDate(filters.date, -1);
    const nextYearDate = adjacentYearDate(filters.date, 1);
    const previousDecadeDate = adjacentYearDate(filters.date, -10);
    const nextDecadeDate = adjacentYearDate(filters.date, 10);
    const selectedYear = new Date(`${filters.date}T00:00:00`).getFullYear();
    const yearOptions = surroundingYears(filters.date);
    const monthTitle = new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
    }).format(new Date(`${filters.date}T00:00:00`));

    return (
        <>
            <Head title="予定表" />
            <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.18),_transparent_30%),linear-gradient(135deg,_rgba(250,250,249,1),_rgba(239,246,255,0.85))] p-3 md:p-6 dark:bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.18),_transparent_30%),linear-gradient(135deg,_rgba(10,10,10,1),_rgba(23,23,23,1))]">
                <div className="mx-auto grid max-w-7xl gap-5 xl:grid-cols-[320px_1fr]">
                    <aside className="order-1 space-y-4 xl:order-1">
                        <div className="rounded-3xl border bg-white/85 p-5 shadow-sm backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Unified Schedule
                                    </p>
                                    <h1 className="text-2xl font-bold">
                                        予定表
                                    </h1>
                                </div>
                                <CalendarDays className="size-8 text-amber-600" />
                            </div>
                            <p className="mt-3 text-sm text-muted-foreground">
                                {formatDate(filters.starts_on)} -{' '}
                                {formatDate(filters.ends_on)}
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
                            {canManage && (
                                <div className="mt-5 grid gap-2">
                                    <Button asChild className="w-full">
                                        <Link href={scheduleCreate()}>
                                            <Plus className="size-4" />
                                            新規工事予定
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={businessScheduleCreate()}>
                                            <BriefcaseBusiness className="size-4" />
                                            新規業務予定
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            <div className="mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                <div className="flex flex-wrap gap-2">
                                    <TypeLink
                                        label="すべて"
                                        type="all"
                                        filters={filters}
                                    />
                                    <TypeLink
                                        label="工事"
                                        type="construction"
                                        filters={filters}
                                    />
                                    <TypeLink
                                        label="業務"
                                        type="business"
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
                                        業務
                                    </span>
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
                                                            href={scheduleIndex(
                                                                {
                                                                    query: {
                                                                        range: 'month',
                                                                        date: item.date,
                                                                        type: filters.type,
                                                                    },
                                                                },
                                                            )}
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
                                                            href={scheduleIndex(
                                                                {
                                                                    query: {
                                                                        range: 'month',
                                                                        date: yearDate(
                                                                            filters.date,
                                                                            year,
                                                                        ),
                                                                        type: filters.type,
                                                                    },
                                                                },
                                                            )}
                                                            className={`rounded-xl px-3 py-2 text-center text-sm font-semibold transition ${year === selectedYear ? 'bg-amber-500 text-white' : 'bg-neutral-100 hover:bg-amber-100 dark:bg-neutral-900 dark:hover:bg-amber-950'}`}
                                                            preserveScroll
                                                            aria-current={
                                                                year ===
                                                                selectedYear
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
                                    {[
                                        '日',
                                        '月',
                                        '火',
                                        '水',
                                        '木',
                                        '金',
                                        '土',
                                    ].map((day) => (
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
                                    ))}
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
                                                day.isSelected
                                                    ? 'date'
                                                    : undefined
                                            }
                                            aria-label={`${day.date} (${day.count}件)`}
                                        >
                                            {day.label}
                                            {day.count > 0 && (
                                                <span
                                                    className={`absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-bold ${day.isSelected ? 'bg-white text-amber-700' : day.isCurrentMonth ? 'bg-sky-500 text-white' : 'bg-neutral-400 text-white dark:bg-neutral-600'}`}
                                                >
                                                    {day.count}
                                                </span>
                                            )}
                                            {day.constructionCount > 0 &&
                                                day.businessCount > 0 && (
                                                    <span className="absolute bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5">
                                                        <span className="size-1.5 rounded-full bg-orange-500" />
                                                        <span className="size-1.5 rounded-full bg-violet-500" />
                                                    </span>
                                                )}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </aside>

                    <main className="order-2 space-y-5 xl:order-2 xl:space-y-6">
                        <div className="flex gap-2">
                            <RangeLink
                                label="今日"
                                range="today"
                                filters={filters}
                                className="flex-1 text-center"
                            />
                            <RangeLink
                                label="今週"
                                range="week"
                                filters={filters}
                                className="flex-1 text-center"
                            />
                            <RangeLink
                                label="今月"
                                range="month"
                                filters={filters}
                                className="flex-1 text-center"
                            />
                        </div>
                        <ScheduleSection
                            title="自分の予定"
                            empty="この期間に割り当てられた予定はありません。"
                            schedules={mySchedules}
                            canManage={canManage}
                        />
                        <ScheduleSection
                            title="全員の予定"
                            empty="この期間の他メンバーの予定はありません。"
                            schedules={teamSchedules}
                            canManage={canManage}
                        />
                    </main>
                </div>
            </div>
        </>
    );
}

ConstructionSchedulesIndex.layout = {
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
