import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck2,
    ChevronLeft,
    ChevronRight,
    Eraser,
    Plane,
    UserCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    destroy as attendanceRecordDestroy,
    index as attendanceRecordIndex,
    store as attendanceRecordStore,
} from '@/actions/App/Http/Controllers/AttendanceRecordController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { businessDateString } from '@/lib/dates';
import type {
    AttendanceRecord,
    AttendanceStatus,
    ConstructionUser,
} from '@/types';

type AttendanceDay = {
    date: string;
    day: number;
    weekday: string;
    is_weekend: boolean;
    is_today: boolean;
};

type Props = {
    filters: {
        month: string;
        previous_month: string;
        next_month: string;
    };
    days: AttendanceDay[];
    users: ConstructionUser[];
    records: AttendanceRecord[];
    stats: {
        working: number;
        leave: number;
        unmarked: number;
    };
    canManage: boolean;
};

type AttendanceForm = {
    user_id: number | '';
    work_date: string;
    status: AttendanceStatus;
    note: string;
};

const japaneseWeekdayLabels: Record<string, string> = {
    日: '日',
    月: '月',
    火: '火',
    水: '水',
    木: '木',
    金: '金',
    土: '土',
};

const statusLabels: Record<AttendanceStatus, string> = {
    working: '出勤',
    leave: '休み',
};

function recordKey(userId: number, date: string) {
    return `${userId}-${date}`;
}

function dayLabel(day: AttendanceDay) {
    if (day.day === 1) {
        return `${new Date(`${day.date}T00:00:00`).getMonth() + 1}/${day.day}`;
    }

    return day.day.toString();
}

function monthLabel(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'long',
    }).format(new Date(`${date}T00:00:00`));
}

function dateLabel(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function attendancePeriodLabel(days: AttendanceDay[]) {
    const firstDay = days[0];
    const lastDay = days.at(-1);

    if (!firstDay || !lastDay) {
        return '';
    }

    return `${dateLabel(firstDay.date)} - ${dateLabel(lastDay.date)}`;
}

function shortDateLabel(date: string) {
    return new Intl.DateTimeFormat('ja-JP', {
        month: 'numeric',
        day: 'numeric',
        weekday: 'short',
    }).format(new Date(`${date}T00:00:00`));
}

function japaneseWeekdayName(day: AttendanceDay): string {
    return (
        japaneseWeekdayLabels[day.weekday] ??
        new Intl.DateTimeFormat('ja-JP', {
            weekday: 'long',
        }).format(new Date(`${day.date}T00:00:00`))
    );
}

function statusClass(status: AttendanceStatus | undefined) {
    if (status === 'leave') {
        return 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100';
    }

    if (status === 'working') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100';
    }

    return 'border-neutral-200 bg-white text-muted-foreground hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950 dark:hover:bg-neutral-900';
}

function isHiddenUser(user: ConstructionUser) {
    return user.is_hidden_from_workers === true;
}

function AttendanceCell({
    user,
    day,
    record,
    canManage,
    showWeekday = false,
    onSelect,
    onClear,
}: {
    user: ConstructionUser;
    day: AttendanceDay;
    record?: AttendanceRecord;
    canManage: boolean;
    showWeekday?: boolean;
    onSelect: (
        user: ConstructionUser,
        day: AttendanceDay,
        record?: AttendanceRecord,
    ) => void;
    onClear: (record: AttendanceRecord) => void;
}) {
    const todayClass = day.is_today
        ? 'ring-2 ring-amber-500 ring-offset-2 ring-offset-white dark:ring-amber-300 dark:ring-offset-neutral-950'
        : '';
    const content = (
        <>
            <span className="text-xs font-semibold">{dayLabel(day)}</span>
            {showWeekday ? (
                <span className="text-[10px] leading-none font-medium text-current opacity-75">
                    {day.is_today ? '今日' : japaneseWeekdayName(day)}
                </span>
            ) : null}
            <span className="text-[11px]">
                {record ? statusLabels[record.status] : '未'}
            </span>
        </>
    );

    if (!canManage) {
        return (
            <div
                className={`grid ${showWeekday ? 'h-16' : 'h-14'} place-items-center content-center gap-0.5 rounded-md border px-1 text-center ${statusClass(record?.status)} ${showWeekday ? todayClass : ''}`}
                title={record?.note ?? undefined}
            >
                {content}
            </div>
        );
    }

    return (
        <div className="relative">
            <button
                type="button"
                className={`grid ${showWeekday ? 'h-16' : 'h-14'} w-full place-items-center content-center gap-0.5 rounded-md border px-1 text-center transition ${statusClass(record?.status)} ${showWeekday ? todayClass : ''}`}
                onClick={() => onSelect(user, day, record)}
                title={record?.note ?? undefined}
            >
                {content}
            </button>
            {record ? (
                <button
                    type="button"
                    className="absolute -top-1 -right-1 grid size-5 place-items-center rounded-full bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-neutral-950"
                    onClick={() => onClear(record)}
                    aria-label="未設定に戻す"
                >
                    <Eraser className="size-3" />
                </button>
            ) : null}
        </div>
    );
}

export default function AttendanceRecordIndex({
    filters,
    days,
    users,
    records,
    canManage,
}: Props) {
    const [selectedRecord, setSelectedRecord] =
        useState<AttendanceRecord | null>(null);
    const [selectedUser, setSelectedUser] = useState<ConstructionUser | null>(
        null,
    );
    const { data, setData, post, processing, errors, reset } =
        useForm<AttendanceForm>({
            user_id: '',
            work_date: '',
            status: 'working',
            note: '',
        });
    const visibleUsers = useMemo(() => {
        return users.filter((user) => !isHiddenUser(user));
    }, [users]);
    const visibleUserIds = useMemo(() => {
        return new Set(visibleUsers.map((user) => user.id));
    }, [visibleUsers]);
    const visibleRecords = useMemo(() => {
        return records.filter(
            (record) =>
                visibleUserIds.has(record.user_id) &&
                !isHiddenUser(record.user),
        );
    }, [records, visibleUserIds]);
    const recordMap = useMemo(() => {
        return new Map(
            visibleRecords.map((record) => [
                recordKey(record.user_id, record.work_date),
                record,
            ]),
        );
    }, [visibleRecords]);
    const workingCountByUser = useMemo(() => {
        return visibleRecords.reduce((counts, record) => {
            if (record.status === 'working') {
                counts.set(
                    record.user_id,
                    (counts.get(record.user_id) ?? 0) + 1,
                );
            }

            return counts;
        }, new Map<number, number>());
    }, [visibleRecords]);
    const today = businessDateString();
    const leaveToday = visibleRecords.filter(
        (record) => record.status === 'leave' && record.work_date === today,
    );
    const periodLabel = attendancePeriodLabel(days);

    function openEditor(
        user: ConstructionUser,
        day: AttendanceDay,
        record?: AttendanceRecord,
    ) {
        setSelectedUser(user);
        setSelectedRecord(record ?? null);
        setData({
            user_id: user.id,
            work_date: day.date,
            status: record?.status ?? 'working',
            note: record?.note ?? '',
        });
    }

    function closeEditor() {
        setSelectedUser(null);
        setSelectedRecord(null);
        reset();
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(attendanceRecordStore.url(), {
            preserveScroll: true,
            onSuccess: closeEditor,
        });
    }

    function clearRecord(record: AttendanceRecord) {
        router.delete(attendanceRecordDestroy.url(record.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="出勤管理" />
            <div className="max-w-7xl space-y-3 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Attendance
                        </p>
                        <h1 className="text-2xl font-bold">出勤管理</h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={attendanceRecordIndex({
                                    query: { month: filters.previous_month },
                                })}
                            >
                                <ChevronLeft className="size-4" />
                                前月
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={attendanceRecordIndex({
                                    query: { month: filters.next_month },
                                })}
                            >
                                翌月
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-3 md:grid-cols-4">
                    <div className="rounded-lg border bg-white p-4 shadow-sm md:col-span-2 dark:border-neutral-800 dark:bg-neutral-950">
                        <p className="text-sm text-muted-foreground">対象月</p>
                        <div className="mt-2 flex items-center gap-3">
                            <CalendarCheck2 className="size-6 text-emerald-600" />
                            <div>
                                <p className="text-2xl font-bold">
                                    {monthLabel(filters.month)}
                                </p>
                                {periodLabel ? (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {periodLabel}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>

                {leaveToday.length > 0 ? (
                    <section className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
                        <div className="flex items-center gap-2 font-semibold">
                            <Plane className="size-4" />
                            今日休み
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {leaveToday.map((record) => (
                                <Badge
                                    key={record.id}
                                    variant="outline"
                                    className="bg-white dark:bg-neutral-950"
                                >
                                    {record.user.name}
                                    {record.note ? `: ${record.note}` : ''}
                                </Badge>
                            ))}
                        </div>
                    </section>
                ) : null}

                <section className="overflow-hidden rounded-lg border bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="border-b p-4 dark:border-neutral-800">
                        <h2 className="font-semibold">月間カレンダー</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {canManage
                                ? '日付を選択して出勤・休みを更新できます。'
                                : '管理者以外は閲覧のみです。'}
                        </p>
                    </div>

                    <div className="hidden overflow-x-auto lg:block">
                        <div
                            className="grid min-w-[1180px] gap-2 p-4"
                            style={{
                                gridTemplateColumns: `180px 72px repeat(${days.length}, minmax(38px, 1fr))`,
                            }}
                        >
                            <div className="sticky left-0 z-10 bg-white text-sm font-semibold dark:bg-neutral-950">
                                ユーザー
                            </div>
                            <div className="sticky left-[188px] z-10 bg-white text-center text-sm font-semibold dark:bg-neutral-950">
                                出勤日数
                            </div>
                            {days.map((day) => (
                                <div
                                    key={day.date}
                                    className={`text-center text-xs font-semibold ${day.is_weekend ? 'text-rose-600' : 'text-muted-foreground'}`}
                                >
                                    <div
                                        className={
                                            day.date === today
                                                ? 'grid justify-items-center rounded-md bg-amber-100 py-1 text-amber-950 dark:bg-amber-950 dark:text-amber-100'
                                                : 'grid justify-items-center'
                                        }
                                    >
                                        <span className="text-sm leading-none">
                                            {dayLabel(day)}
                                        </span>
                                        <span className="mt-1 text-[10px] leading-none font-medium">
                                            {japaneseWeekdayName(day)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                            {visibleUsers.map((user) => (
                                <div key={user.id} className="contents">
                                    <div className="sticky left-0 z-10 flex items-center gap-2 bg-white py-2 pr-3 dark:bg-neutral-950">
                                        <div className="grid size-9 shrink-0 place-items-center rounded-full bg-neutral-900 text-sm font-bold text-white dark:bg-white dark:text-neutral-950">
                                            {user.name
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">
                                                {user.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {user.email ?? 'メール未登録'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="sticky left-[188px] z-10 grid place-items-center bg-white py-2 dark:bg-neutral-950">
                                        <Badge
                                            variant="outline"
                                            className="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                                        >
                                            {workingCountByUser.get(user.id) ??
                                                0}
                                            日
                                        </Badge>
                                    </div>
                                    {days.map((day) => (
                                        <AttendanceCell
                                            key={day.date}
                                            user={user}
                                            day={day}
                                            record={recordMap.get(
                                                recordKey(user.id, day.date),
                                            )}
                                            canManage={canManage}
                                            onSelect={openEditor}
                                            onClear={clearRecord}
                                        />
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 p-4 lg:hidden">
                        {visibleUsers.map((user) => (
                            <article
                                key={user.id}
                                className="rounded-lg border p-3 dark:border-neutral-800"
                            >
                                <div className="flex items-center gap-2">
                                    <UserCheck className="size-4 text-emerald-600" />
                                    <h3 className="font-semibold">
                                        {user.name}
                                    </h3>
                                    <Badge
                                        variant="outline"
                                        className="ml-auto border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                                    >
                                        出勤{' '}
                                        {workingCountByUser.get(user.id) ?? 0}日
                                    </Badge>
                                </div>
                                <div className="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-7">
                                    {days.map((day) => (
                                        <AttendanceCell
                                            key={day.date}
                                            user={user}
                                            day={day}
                                            record={recordMap.get(
                                                recordKey(user.id, day.date),
                                            )}
                                            canManage={canManage}
                                            showWeekday
                                            onSelect={openEditor}
                                            onClear={clearRecord}
                                        />
                                    ))}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                {selectedUser ? (
                    <div className="fixed inset-0 z-50 grid place-items-end bg-black/40 p-3 sm:place-items-center">
                        <form
                            onSubmit={submit}
                            className="w-full max-w-md rounded-lg bg-white p-5 shadow-xl dark:bg-neutral-950"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {shortDateLabel(data.work_date)}
                                    </p>
                                    <h2 className="text-xl font-bold">
                                        {selectedUser.name}
                                    </h2>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={closeEditor}
                                >
                                    閉じる
                                </Button>
                            </div>

                            <div className="mt-5 grid grid-cols-2 gap-2">
                                {(
                                    ['working', 'leave'] as AttendanceStatus[]
                                ).map((status) => (
                                    <button
                                        key={status}
                                        type="button"
                                        className={`rounded-lg border px-4 py-3 text-sm font-semibold transition ${data.status === status ? statusClass(status) : statusClass(undefined)}`}
                                        onClick={() =>
                                            setData('status', status)
                                        }
                                    >
                                        {statusLabels[status]}
                                    </button>
                                ))}
                            </div>
                            {errors.status ? (
                                <p className="mt-2 text-xs text-destructive">
                                    {errors.status}
                                </p>
                            ) : null}

                            <label className="mt-4 grid gap-2 text-sm font-medium">
                                メモ
                                <Input
                                    value={data.note}
                                    onChange={(event) =>
                                        setData('note', event.target.value)
                                    }
                                    placeholder="例: 有給、午前休、現場直行"
                                />
                                {errors.note ? (
                                    <span className="text-xs text-destructive">
                                        {errors.note}
                                    </span>
                                ) : null}
                            </label>

                            <div className="mt-5 flex flex-wrap justify-between gap-2">
                                {selectedRecord ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            clearRecord(selectedRecord);
                                            closeEditor();
                                        }}
                                    >
                                        未設定に戻す
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <Button type="submit" disabled={processing}>
                                    保存
                                </Button>
                            </div>
                        </form>
                    </div>
                ) : null}
            </div>
        </>
    );
}

AttendanceRecordIndex.layout = {
    breadcrumbs: [
        {
            title: '出勤管理',
            href: attendanceRecordIndex(),
        },
    ],
};
