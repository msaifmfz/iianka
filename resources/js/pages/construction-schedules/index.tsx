import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileText,
    Hammer,
    Hash,
    Megaphone,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import {
    create as businessScheduleCreate,
    destroy as businessScheduleDestroy,
    edit as businessScheduleEdit,
    show as businessScheduleShow,
    updateNumber as businessScheduleUpdateNumber,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { edit as cleaningDutyRuleEdit } from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import {
    destroy as scheduleDestroy,
    index as scheduleIndex,
    show as scheduleShow,
    create as scheduleCreate,
    edit as scheduleEdit,
    updateNumber as scheduleUpdateNumber,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    create as internalNoticeCreate,
    destroy as internalNoticeDestroy,
    edit as internalNoticeEdit,
    show as internalNoticeShow,
} from '@/actions/App/Http/Controllers/InternalNoticeController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type {
    ConstructionSchedule,
    ConstructionUser,
    ScheduleEvent,
} from '@/types';

type CalendarDay = {
    date: string;
    count: number;
    construction_count: number;
    business_count: number;
    internal_notice_count: number;
    cleaning_duty_count: number;
};

type ScheduleType =
    | 'construction'
    | 'business'
    | 'internal_notice'
    | 'cleaning_duty';

type ScheduleTypeFilter = 'all' | ScheduleType;

type Filters = {
    range: 'today' | 'week' | 'month';
    type: ScheduleType[];
    date: string;
    starts_on: string;
    ends_on: string;
    user_ids: number[];
};

type ScheduleNavigation = {
    previous_date: string | null;
    next_date: string | null;
};

type Props = {
    filters: Filters;
    todayDate: string;
    calendarDays: CalendarDay[];
    scheduleNavigation: ScheduleNavigation;
    mySchedules: ScheduleEvent[];
    teamSchedules: ScheduleEvent[];
    selectedUserSchedules: ScheduleEvent[];
    workerSummary: {
        assigned_count: number;
        notice_count: number;
        pending_voucher_count: number;
        status_change_count: number;
    };
    userOptions: ConstructionUser[];
};

const emptyWorkerSummary: Props['workerSummary'] = {
    assigned_count: 0,
    notice_count: 0,
    pending_voucher_count: 0,
    status_change_count: 0,
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

const scheduleTypes: ScheduleType[] = [
    'construction',
    'business',
    'internal_notice',
    'cleaning_duty',
];

const scheduleTypePriority: Record<ScheduleType, number> = {
    construction: 0,
    business: 0,
    internal_notice: 1,
    cleaning_duty: 2,
};

const scheduleIndexScrollStorageKey = 'construction-schedules:index-scroll:';

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

function phoneHref(phone: string) {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

function adjacentMonthDate(selectedDate: string, offset: number) {
    const date = new Date(`${selectedDate}T00:00:00`);

    return formatInputDate(
        new Date(date.getFullYear(), date.getMonth() + offset, 1),
    );
}

function allScheduleTypesSelected(types: ScheduleType[]) {
    return scheduleTypes.every((type) => types.includes(type));
}

function toggleScheduleType(
    selectedTypes: ScheduleType[],
    type: ScheduleTypeFilter,
) {
    if (type === 'all') {
        return scheduleTypes;
    }

    const nextTypes = selectedTypes.includes(type)
        ? selectedTypes.filter((selectedType) => selectedType !== type)
        : [...selectedTypes, type];

    return nextTypes.length > 0 ? nextTypes : [type];
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
    internalNoticeCount: number;
    cleaningDutyCount: number;
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
            internalNoticeCount: count?.internal_notice_count ?? 0,
            cleaningDutyCount: count?.cleaning_duty_count ?? 0,
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
        user_ids: query.user_ids ?? filters.user_ids,
    };
}

function filterSchedulesByType(
    schedules: ScheduleEvent[],
    selectedTypes: ScheduleType[],
) {
    return schedules.filter((schedule) =>
        selectedTypes.includes(schedule.type),
    );
}

function sortSchedulesByPriority(schedules: ScheduleEvent[]) {
    return [...schedules].sort(
        (left, right) =>
            scheduleTypePriority[left.type] - scheduleTypePriority[right.type],
    );
}

function scheduleKey(schedule: ScheduleEvent) {
    return `${schedule.type}-${schedule.id}-${schedule.scheduled_on}`;
}

function scheduleNumberClasses(schedule: ScheduleEvent) {
    if (schedule.type === 'business') {
        return 'bg-violet-500 text-white shadow-sm ring-4 ring-violet-100 dark:ring-violet-950';
    }

    return 'bg-amber-500 text-white shadow-sm ring-4 ring-amber-100 dark:ring-amber-950';
}

function scheduleDeleteConfirmation(schedule: ScheduleEvent) {
    if (schedule.type === 'construction') {
        return 'この工事予定を削除しますか？';
    }

    if (schedule.type === 'business') {
        return 'この業務予定を削除しますか？';
    }

    if (schedule.type === 'internal_notice') {
        return 'この業務連絡を削除しますか？';
    }

    return null;
}

type ScheduleNumberForm = {
    schedule_number: string;
};

function ScheduleNumberBadge({
    schedule,
    value,
}: {
    schedule: ScheduleEvent;
    value: string | number;
}) {
    return (
        <div
            className={`flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl ${scheduleNumberClasses(schedule)}`}
        >
            <Hash className="size-4" />
            <span className="text-xl leading-none font-black">{value}</span>
        </div>
    );
}

function InlineScheduleNumberEditor({
    schedule,
}: {
    schedule: Extract<ScheduleEvent, { type: 'construction' | 'business' }>;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, clearErrors, reset } =
        useForm<ScheduleNumberForm>({
            schedule_number: schedule.schedule_number?.toString() ?? '',
        });
    const updateAction =
        schedule.type === 'construction'
            ? scheduleUpdateNumber
            : businessScheduleUpdateNumber;

    function handleOpenChange(nextOpen: boolean) {
        setOpen(nextOpen);

        if (nextOpen) {
            clearErrors();
            setData(
                'schedule_number',
                schedule.schedule_number?.toString() ?? '',
            );

            return;
        }

        reset();
        clearErrors();
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        patch(updateAction.url(schedule.id), {
            preserveScroll: true,
            onSuccess: () => {
                handleOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="rounded-2xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-label={`番号${schedule.schedule_number ?? '未設定'}を変更`}
                >
                    <div className="relative">
                        <ScheduleNumberBadge
                            schedule={schedule}
                            value={schedule.schedule_number ?? '?'}
                        />
                        <span className="absolute -right-1 -bottom-1 rounded-full bg-neutral-950 px-1.5 py-0.5 text-[10px] font-semibold text-white shadow-sm dark:bg-white dark:text-neutral-950">
                            編集
                        </span>
                    </div>
                </button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>番号を変更</DialogTitle>
                    <DialogDescription>
                        同じ日付の工事予定・業務予定と番号は重複できません。
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <label
                            htmlFor={`schedule-number-${schedule.type}-${schedule.id}`}
                            className="text-sm font-medium"
                        >
                            番号
                        </label>
                        <Input
                            id={`schedule-number-${schedule.type}-${schedule.id}`}
                            type="number"
                            min={1}
                            inputMode="numeric"
                            autoFocus
                            value={data.schedule_number}
                            onChange={(event) =>
                                setData('schedule_number', event.target.value)
                            }
                            placeholder="未設定"
                        />
                        <p className="text-xs text-muted-foreground">
                            空欄で保存すると番号を未設定に戻せます。
                        </p>
                        {errors.schedule_number && (
                            <p className="text-xs text-destructive">
                                {errors.schedule_number}
                            </p>
                        )}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            キャンセル
                        </Button>
                        <Button type="submit" disabled={processing}>
                            保存
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
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
    filters: Filters;
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

function isHiddenUser(user: ConstructionUser) {
    return user.is_hidden_from_workers === true;
}

function UserFilterPanel({
    users,
    filters,
}: {
    users: ConstructionUser[];
    filters: Filters;
}) {
    const visibleUsers = users.filter((user) => !isHiddenUser(user));

    if (visibleUsers.length === 0) {
        return null;
    }

    function toggleUser(userId: number) {
        return filters.user_ids.includes(userId)
            ? filters.user_ids.filter(
                  (selectedUserId) => selectedUserId !== userId,
              )
            : [...filters.user_ids, userId];
    }

    return (
        <div className="mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-sm font-semibold">ユーザー別予定</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        管理者は複数ユーザーの予定をまとめて表示できます。
                    </p>
                </div>
                {filters.user_ids.length > 0 && (
                    <Link
                        href={scheduleIndex({
                            query: scheduleQuery(filters, { user_ids: [] }),
                        })}
                        className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold transition hover:bg-neutral-200 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        preserveScroll
                    >
                        解除
                    </Link>
                )}
            </div>
            <div className="mt-3 grid gap-2">
                {visibleUsers.map((user) => {
                    const selected = filters.user_ids.includes(user.id);

                    return (
                        <Link
                            key={user.id}
                            href={scheduleIndex({
                                query: scheduleQuery(filters, {
                                    user_ids: toggleUser(user.id),
                                }),
                            })}
                            className={`rounded-xl border px-3 py-2 text-sm transition ${selected ? 'border-amber-300 bg-amber-50 font-semibold text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100' : 'border-neutral-200 hover:bg-neutral-100 dark:border-neutral-800 dark:hover:bg-neutral-900'}`}
                            preserveScroll
                        >
                            {user.name}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

function ScheduleCard({
    schedule,
    canManage,
    currentUserId,
    returnTo,
}: {
    schedule: ScheduleEvent;
    canManage: boolean;
    currentUserId: number;
    returnTo: string;
}) {
    const scheduleDetail =
        schedule.type === 'construction'
            ? scheduleShow(schedule.id, {
                  query: {
                      return_to: returnTo,
                  },
              })
            : schedule.type === 'business'
              ? businessScheduleShow(schedule.id, {
                    query: {
                        return_to: returnTo,
                    },
                })
              : schedule.type === 'internal_notice'
                ? internalNoticeShow(schedule.id, {
                      query: {
                          return_to: returnTo,
                      },
                  })
                : null;
    const scheduleEditHref =
        schedule.type === 'construction'
            ? scheduleEdit(schedule.id)
            : schedule.type === 'business'
              ? businessScheduleEdit(schedule.id)
              : schedule.type === 'internal_notice'
                ? internalNoticeEdit(schedule.id)
                : cleaningDutyRuleEdit(schedule.rule_id);
    const scheduleDeleteHref =
        schedule.type === 'construction'
            ? scheduleDestroy.url(schedule.id)
            : schedule.type === 'business'
              ? businessScheduleDestroy.url(schedule.id)
              : schedule.type === 'internal_notice'
                ? internalNoticeDestroy.url(schedule.id)
                : null;
    const typeBadge = {
        construction:
            'bg-orange-100 text-orange-900 dark:bg-orange-950 dark:text-orange-200',
        business:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
        internal_notice:
            'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
        cleaning_duty:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    }[schedule.type];
    const title =
        schedule.type === 'construction' || schedule.type === 'business'
            ? schedule.location
            : schedule.title;
    const detailHint =
        schedule.type === 'cleaning_duty' ? '固定当番' : '詳細を見る';
    const typeLabel = {
        construction: '工事',
        business: '業務予定',
        internal_notice: '業務連絡',
        cleaning_duty: '掃除当番',
    }[schedule.type];
    const typeIcon = {
        construction: Hammer,
        business: BriefcaseBusiness,
        internal_notice: Megaphone,
        cleaning_duty: ClipboardList,
    }[schedule.type];
    const TypeIcon = typeIcon;
    const scheduleNumber =
        schedule.type === 'construction' || schedule.type === 'business'
            ? (schedule.schedule_number ?? '?')
            : null;
    const isAssignedToCurrentUser = schedule.assigned_users.some(
        (user) => user.id === currentUserId,
    );
    const editableNumberSchedule =
        canManage &&
        (schedule.type === 'construction' || schedule.type === 'business')
            ? schedule
            : null;
    function deleteSchedule() {
        const confirmation = scheduleDeleteConfirmation(schedule);

        if (scheduleDeleteHref === null || confirmation === null) {
            return;
        }

        if (!confirm(confirmation)) {
            return;
        }

        router.delete(scheduleDeleteHref, {
            preserveScroll: true,
        });
    }

    const headerContent = (
        <div className="min-w-0">
            {schedule.assigned_users.length > 0 && (
                <div className="text-md mb-1 flex flex-wrap items-center gap-2 rounded-2xl font-semibold text-amber-900 dark:text-amber-100">
                    <Users className="size-4" />
                    <span className="font-medium">
                        {schedule.assigned_users
                            .map((user) => user.name)
                            .join('、')}
                    </span>
                </div>
            )}
            {schedule.type === 'construction' &&
                schedule.subcontractors.length > 0 && (
                    <div className="mb-1 flex flex-wrap items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <span className="rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-semibold dark:bg-neutral-900">
                            下請け
                        </span>
                        {schedule.subcontractors.map((subcontractor) =>
                            subcontractor.phone ? (
                                <a
                                    key={subcontractor.id}
                                    href={phoneHref(subcontractor.phone)}
                                    className="inline-flex items-center gap-1 rounded-md px-1 py-0.5 hover:bg-neutral-100 hover:text-sky-700 dark:hover:bg-neutral-900 dark:hover:text-sky-300"
                                >
                                    <Phone className="size-3.5" />
                                    {subcontractor.name}
                                </a>
                            ) : (
                                <span
                                    key={subcontractor.id}
                                    className="inline-flex items-center rounded-md px-1 py-0.5"
                                >
                                    {subcontractor.name}
                                </span>
                            ),
                        )}
                    </div>
                )}
            <p className="text-md text-muted-foreground">
                {formatDate(schedule.scheduled_on)}
            </p>
            <CardTitle className="text-md mt-1 leading-tight">
                {title}
            </CardTitle>
            <p className="mt-2 text-xs font-medium text-sky-700 dark:text-sky-300">
                {detailHint}
            </p>
        </div>
    );

    const cardBody = (
        <>
            <CardHeader className="gap-3 p-4 md:p-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        {scheduleNumber !== null &&
                            (editableNumberSchedule !== null ? (
                                <InlineScheduleNumberEditor
                                    schedule={editableNumberSchedule}
                                />
                            ) : (
                                <ScheduleNumberBadge
                                    schedule={schedule}
                                    value={scheduleNumber}
                                />
                            ))}
                        {scheduleDetail === null ? (
                            headerContent
                        ) : (
                            <Link
                                href={scheduleDetail}
                                className="block min-w-0 rounded-xl transition hover:bg-neutral-50/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:hover:bg-neutral-900/40"
                                aria-label={`${title}の予定詳細を見る`}
                            >
                                {headerContent}
                            </Link>
                        )}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold ${typeBadge}`}
                        >
                            <TypeIcon className="size-3.5" />
                            {typeLabel}
                        </span>
                        {schedule.type === 'construction' && (
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClasses[schedule.status]}`}
                            >
                                {statusLabels[schedule.status]}
                            </span>
                        )}
                        {isAssignedToCurrentUser && (
                            <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                                {schedule.type === 'internal_notice'
                                    ? '自分宛て'
                                    : '自分'}
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex flex-wrap gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                    <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                        {schedule.time}
                    </span>
                    {schedule.type === 'business' &&
                        schedule.general_contractor && (
                            <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                                {schedule.general_contractor}
                            </span>
                        )}
                    {schedule.type === 'business' &&
                        schedule.person_in_charge && (
                            <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                                現場担当者: {schedule.person_in_charge}
                            </span>
                        )}
                    {(schedule.type === 'internal_notice' ||
                        schedule.type === 'cleaning_duty') &&
                        schedule.location && (
                            <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                                {schedule.location}
                            </span>
                        )}
                    {schedule.type === 'cleaning_duty' && (
                        <span className="rounded-full bg-neutral-100 px-3 py-1 dark:bg-neutral-900">
                            {schedule.weekday_label}
                        </span>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4 p-4 pt-0 md:p-6 md:pt-0">
                <div className="grid gap-2 text-sm md:grid-cols-2 md:gap-3">
                    {schedule.type === 'construction' && (
                        <div className="rounded-xl bg-neutral-50 p-3 dark:bg-neutral-900">
                            <p className="text-muted-foreground">集合場所</p>
                            <p className="mt-1 font-medium">
                                {schedule.meeting_place || '未設定'}
                            </p>
                        </div>
                    )}
                    {(schedule.type === 'construction' ||
                        schedule.type === 'business') && (
                        <div className="rounded-xl bg-neutral-50 p-3 dark:bg-neutral-900">
                            <p className="text-muted-foreground">人員</p>
                            <p className="mt-1 font-medium">
                                {schedule.personnel || '未設定'}
                            </p>
                        </div>
                    )}
                </div>
                {schedule.content && (
                    <p className="line-clamp-3 text-sm leading-6 md:line-clamp-none">
                        {schedule.content}
                    </p>
                )}
                {(schedule.type === 'business' ||
                    schedule.type === 'internal_notice' ||
                    schedule.type === 'cleaning_duty') &&
                    schedule.memo && (
                        <p className="line-clamp-2 rounded-xl bg-neutral-50 p-3 text-sm leading-6 text-neutral-900 dark:bg-neutral-900 dark:text-neutral-100">
                            {schedule.memo}
                        </p>
                    )}
            </CardContent>
        </>
    );

    return (
        <Card
            className={`gap-0 overflow-hidden py-0 shadow-sm transition hover:shadow-md ${isAssignedToCurrentUser ? 'border-amber-200 bg-amber-50/70 hover:border-amber-300 dark:border-amber-900/60 dark:bg-amber-950/10 dark:hover:border-amber-800' : 'border-neutral-200 bg-white/95 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-950/85 dark:hover:border-neutral-700'}`}
        >
            <div>{cardBody}</div>
            <CardContent className="p-4 pt-0 md:p-6 md:pt-0">
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    {schedule.type === 'construction' &&
                        schedule.google_maps_url && (
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
                    {scheduleDetail !== null && (
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 justify-center sm:justify-start"
                        >
                            <Link href={scheduleDetail}>
                                <FileText className="size-4" />
                                詳細
                                {/* {schedule.type === 'construction' */}
                                {/*     ? `詳細・案内図 (${schedule.guide_files.length})` */}
                                {/*     : '詳細'} */}
                            </Link>
                        </Button>
                    )}
                    {canManage && (
                        <>
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
                            {scheduleDeleteHref !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11 justify-center sm:justify-start"
                                    onClick={deleteSchedule}
                                >
                                    <Trash2 className="size-4" />
                                    削除
                                </Button>
                            )}
                        </>
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
    currentUserId,
    returnTo,
    id,
    sectionRef,
}: {
    title: string;
    empty: string;
    schedules: ScheduleEvent[];
    canManage: boolean;
    currentUserId: number;
    returnTo: string;
    id?: string;
    sectionRef?: RefObject<HTMLElement | null>;
}) {
    return (
        <section
            id={id}
            ref={sectionRef}
            tabIndex={id ? -1 : undefined}
            className="scroll-mt-24 space-y-3"
        >
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
                            key={scheduleKey(schedule)}
                            schedule={schedule}
                            canManage={canManage}
                            currentUserId={currentUserId}
                            returnTo={returnTo}
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
    selectedUserSchedules,
    workerSummary = emptyWorkerSummary,
    userOptions,
}: Props) {
    const page = usePage();
    const {
        auth: { user },
    } = page.props;
    const canManage = user.is_admin === true;
    const returnTo = page.url;
    const myScheduleSectionRef = useRef<HTMLElement | null>(null);
    const calendarAreaRef = useRef<HTMLDivElement | null>(null);
    const floatingActionRef = useRef<HTMLButtonElement | null>(null);
    const [isFloatingActionAboveCalendar, setIsFloatingActionAboveCalendar] =
        useState(false);
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
    const hasSelectedUserFilter = canManage && filters.user_ids.length > 0;
    const filteredMySchedules = sortSchedulesByPriority(
        filterSchedulesByType(mySchedules, filters.type),
    );
    const filteredTeamSchedules = sortSchedulesByPriority(
        filterSchedulesByType(teamSchedules, filters.type),
    );
    const filteredSelectedUserSchedules = sortSchedulesByPriority(
        filterSchedulesByType(selectedUserSchedules, filters.type),
    );
    const myScheduleKeys = new Set(filteredMySchedules.map(scheduleKey));
    const filteredOtherSchedules = filteredTeamSchedules.filter(
        (schedule) => !myScheduleKeys.has(scheduleKey(schedule)),
    );
    const nextSchedule = filteredMySchedules[0] ?? null;
    const selectedTypeLabels = [
        allScheduleTypesSelected(filters.type) ? 'すべて' : null,
        filters.type.includes('construction') ? '工事' : null,
        filters.type.includes('business') ? '業務予定' : null,
        filters.type.includes('internal_notice') ? '業務連絡' : null,
        filters.type.includes('cleaning_duty') ? '掃除当番' : null,
    ].filter(Boolean);
    const scrollToMySchedule = () => {
        myScheduleSectionRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    };
    const createActions = [
        {
            href: scheduleCreate(),
            icon: Hammer,
            title: '施工予定',
            variant: 'default' as const,
        },
        {
            href: businessScheduleCreate(),
            icon: BriefcaseBusiness,
            title: '業務予定',
            variant: 'outline' as const,
        },
        {
            href: internalNoticeCreate(),
            icon: Megaphone,
            title: '業務連絡',
            variant: 'outline' as const,
        },
    ];

    useEffect(() => {
        let frameId = 0;
        let pendingFrameId = 0;
        const storageKey = `${scheduleIndexScrollStorageKey}${returnTo}`;

        const restoreScrollPosition = () => {
            const storedScrollPosition =
                window.sessionStorage.getItem(storageKey);

            if (storedScrollPosition === null) {
                return;
            }

            const scrollPosition = Number.parseFloat(storedScrollPosition);

            if (Number.isNaN(scrollPosition)) {
                return;
            }

            window.scrollTo({
                top: scrollPosition,
                behavior: 'auto',
            });
        };

        const saveScrollPosition = () => {
            window.sessionStorage.setItem(storageKey, `${window.scrollY}`);
        };

        const requestScrollSave = () => {
            window.cancelAnimationFrame(pendingFrameId);
            pendingFrameId = window.requestAnimationFrame(saveScrollPosition);
        };

        frameId = window.requestAnimationFrame(restoreScrollPosition);
        requestScrollSave();
        window.addEventListener('scroll', requestScrollSave, { passive: true });

        return () => {
            window.cancelAnimationFrame(frameId);
            window.cancelAnimationFrame(pendingFrameId);
            window.removeEventListener('scroll', requestScrollSave);
        };
    }, [returnTo]);

    useEffect(() => {
        if (!canManage) {
            return;
        }

        let frameId = 0;

        const updateFloatingActionState = () => {
            const calendarArea = calendarAreaRef.current;
            const floatingAction = floatingActionRef.current;

            if (calendarArea === null || floatingAction === null) {
                return;
            }

            const calendarRect = calendarArea.getBoundingClientRect();
            const floatingActionRect = floatingAction.getBoundingClientRect();
            const floatingActionCenterY =
                floatingActionRect.top + floatingActionRect.height / 2;

            setIsFloatingActionAboveCalendar(
                floatingActionCenterY < calendarRect.top,
            );
        };

        const requestUpdate = () => {
            window.cancelAnimationFrame(frameId);
            frameId = window.requestAnimationFrame(updateFloatingActionState);
        };

        requestUpdate();

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);

        return () => {
            window.cancelAnimationFrame(frameId);
            window.removeEventListener('scroll', requestUpdate);
            window.removeEventListener('resize', requestUpdate);
        };
    }, [canManage]);

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
                            <div
                                ref={calendarAreaRef}
                                className="mt-5 border-t border-neutral-200 pt-4 dark:border-neutral-800"
                            >
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
                                                                        user_ids:
                                                                            filters.user_ids,
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
                                                                        user_ids:
                                                                            filters.user_ids,
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
                                                        user_ids:
                                                            filters.user_ids,
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
                                                    className={`absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-bold ${day.isSelected ? 'bg-white text-amber-700' : day.isCurrentMonth ? 'bg-gray-500 text-white' : 'bg-neutral-400 text-white dark:bg-neutral-600'}`}
                                                >
                                                    {day.count}
                                                </span>
                                            )}
                                            {[
                                                day.constructionCount > 0 && (
                                                    <span
                                                        key="construction"
                                                        className="size-1.5 rounded-full bg-orange-500"
                                                    />
                                                ),
                                                day.businessCount > 0 && (
                                                    <span
                                                        key="business"
                                                        className="size-1.5 rounded-full bg-violet-500"
                                                    />
                                                ),
                                                day.internalNoticeCount > 0 && (
                                                    <span
                                                        key="internal_notice"
                                                        className="size-1.5 rounded-full bg-sky-500"
                                                    />
                                                ),
                                                day.cleaningDutyCount > 0 && (
                                                    <span
                                                        key="cleaning_duty"
                                                        className="size-1.5 rounded-full bg-emerald-500"
                                                    />
                                                ),
                                            ].filter(Boolean).length > 1 && (
                                                <span className="absolute bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5">
                                                    {[
                                                        day.constructionCount >
                                                            0 && (
                                                            <span
                                                                key="construction"
                                                                className="size-1.5 rounded-full bg-orange-500"
                                                            />
                                                        ),
                                                        day.businessCount >
                                                            0 && (
                                                            <span
                                                                key="business"
                                                                className="size-1.5 rounded-full bg-violet-500"
                                                            />
                                                        ),
                                                        day.internalNoticeCount >
                                                            0 && (
                                                            <span
                                                                key="internal_notice"
                                                                className="size-1.5 rounded-full bg-sky-500"
                                                            />
                                                        ),
                                                        day.cleaningDutyCount >
                                                            0 && (
                                                            <span
                                                                key="cleaning_duty"
                                                                className="size-1.5 rounded-full bg-emerald-500"
                                                            />
                                                        ),
                                                    ]}
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
                                        優先表示:
                                        自分の担当、業務連絡、未確認の現場対応
                                    </p>
                                )}
                            </div>
                        </div>
                    </aside>

                    <main className="order-2 space-y-5 xl:order-2 xl:space-y-6">
                        {!hasSelectedUserFilter && (
                            <section className="rounded-3xl border border-amber-200 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-amber-900/60 dark:bg-neutral-950/80">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="space-y-2">
                                        <p className="text-2xl font-bold text-amber-700 dark:text-amber-300">
                                            次の動きは
                                        </p>
                                        <p className="max-w-2xl text-sm text-muted-foreground">
                                            {formatDate(filters.starts_on)} から{' '}
                                            {formatDate(filters.ends_on)}{' '}
                                            の表示中。
                                            {selectedTypeLabels.join(' / ')}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <span className="rounded-full bg-neutral-950 px-3 py-1 text-xs font-semibold text-white dark:bg-white dark:text-neutral-950">
                                            自分の担当{' '}
                                            {workerSummary.assigned_count}件
                                        </span>
                                        <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-900 dark:bg-sky-950 dark:text-sky-100">
                                            業務連絡{' '}
                                            {workerSummary.notice_count}件
                                        </span>
                                        {/* <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-900 dark:bg-rose-950 dark:text-rose-100"> */}
                                        {/*     未確認伝票 {workerSummary.pending_voucher_count}件 */}
                                        {/* </span> */}
                                        {/* <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100"> */}
                                        {/*     変更注意{' '} */}
                                        {/*     {workerSummary.status_change_count} */}
                                        {/*     件 */}
                                        {/* </span> */}
                                    </div>
                                </div>

                                <div className="mt-4 grid gap-3 lg:grid-cols-[1.4fr_1fr]">
                                    <button
                                        type="button"
                                        onClick={scrollToMySchedule}
                                        className="group rounded-2xl border border-amber-200 bg-linear-to-br from-amber-50 via-white to-sky-50 p-4 text-left text-neutral-950 shadow-md ring-1 ring-amber-200/80 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:ring-amber-300 focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none active:translate-y-0 active:scale-[0.99] dark:border-amber-900/70 dark:from-amber-950/40 dark:via-neutral-900 dark:to-sky-950/30 dark:text-white dark:ring-amber-800/70 dark:hover:ring-amber-700"
                                        aria-label="自分の予定の詳細までスクロール"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-xs font-semibold tracking-[0.16em] text-amber-700 uppercase dark:text-amber-200">
                                                Next Up
                                            </p>
                                            <div className="flex items-center gap-2">
                                                <span className="rounded-full bg-amber-500 px-2.5 py-1 text-[10px] font-bold tracking-[0.16em] text-white uppercase shadow-sm dark:bg-amber-400 dark:text-neutral-950">
                                                    Check
                                                </span>
                                                <span className="flex size-8 items-center justify-center rounded-full bg-white/80 text-amber-700 ring-1 ring-amber-200 transition-transform duration-200 group-hover:translate-y-0.5 dark:bg-neutral-950/70 dark:text-amber-200 dark:ring-amber-900/80">
                                                    <ChevronDown className="size-4" />
                                                </span>
                                            </div>
                                        </div>
                                        {nextSchedule ? (
                                            <div className="mt-3 space-y-2">
                                                <div className="flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-600 dark:text-white/75">
                                                    <span>
                                                        {formatDate(
                                                            nextSchedule.scheduled_on,
                                                        )}
                                                    </span>
                                                    <span>
                                                        {nextSchedule.time}
                                                    </span>
                                                </div>
                                                <p className="text-xl font-semibold text-neutral-950 dark:text-white">
                                                    {nextSchedule.type ===
                                                        'construction' ||
                                                    nextSchedule.type ===
                                                        'business'
                                                        ? nextSchedule.location
                                                        : nextSchedule.title}
                                                </p>
                                                <p className="text-sm text-neutral-700 dark:text-white/75">
                                                    {nextSchedule.assigned_users
                                                        .map(
                                                            (assignedUser) =>
                                                                assignedUser.name,
                                                        )
                                                        .join('、') ||
                                                        '担当なし'}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="mt-3 text-sm text-neutral-700 dark:text-white/75">
                                                この期間に自分の予定はありません。
                                            </p>
                                        )}
                                        <p className="mt-4 text-xs font-medium text-amber-700/90 dark:text-amber-200/90">
                                            タップして「自分の予定」の詳細へ
                                        </p>
                                    </button>
                                </div>
                            </section>
                        )}

                        {canManage && (
                            <UserFilterPanel
                                users={userOptions}
                                filters={filters}
                            />
                        )}
                        {hasSelectedUserFilter ? (
                            <ScheduleSection
                                title="選択スタッフの予定"
                                empty="この期間に選択スタッフの予定はありません。"
                                schedules={filteredSelectedUserSchedules}
                                canManage={canManage}
                                currentUserId={user.id}
                                returnTo={returnTo}
                            />
                        ) : (
                            <>
                                <ScheduleSection
                                    id="my-schedules"
                                    sectionRef={myScheduleSectionRef}
                                    title="自分の予定"
                                    empty="この期間に割り当てられた予定はありません。"
                                    schedules={filteredMySchedules}
                                    canManage={canManage}
                                    currentUserId={user.id}
                                    returnTo={returnTo}
                                />
                                <ScheduleSection
                                    title="全体の予定"
                                    empty="この期間に自分以外の予定はありません。"
                                    schedules={filteredOtherSchedules}
                                    canManage={canManage}
                                    currentUserId={user.id}
                                    returnTo={returnTo}
                                />
                            </>
                        )}
                    </main>
                </div>
            </div>
            {canManage && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            ref={floatingActionRef}
                            size="icon"
                            className={`fixed right-4 bottom-5 z-50 size-14 rounded-full border border-white/70 bg-amber-500 text-white shadow-xl shadow-amber-500/25 transition-all duration-200 hover:scale-[1.03] hover:bg-amber-400 hover:shadow-2xl hover:shadow-amber-500/30 focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background xl:right-8 xl:bottom-8 ${isFloatingActionAboveCalendar ? 'opacity-70 hover:opacity-100' : 'opacity-100'}`}
                            aria-label="新規追加"
                            title="新規追加"
                        >
                            <Plus className="size-6" />
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-xl">
                        <DialogHeader>
                            <DialogTitle>新規作成</DialogTitle>
                            <DialogDescription>
                                追加したい内容を選択してください。
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {createActions.map((action) => {
                                const ActionIcon = action.icon;

                                return (
                                    <Button
                                        key={action.title}
                                        asChild
                                        variant={action.variant}
                                        className="h-auto min-h-32 rounded-2xl px-4 py-4"
                                    >
                                        <Link
                                            href={action.href}
                                            className="flex h-full flex-col items-start justify-between gap-4 text-left"
                                        >
                                            <span className="flex size-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-200">
                                                <ActionIcon className="size-5" />
                                            </span>
                                            <span className="min-w-0 space-y-1 self-stretch">
                                                <span className="block font-semibold wrap-anywhere">
                                                    {action.title}
                                                </span>
                                            </span>
                                        </Link>
                                    </Button>
                                );
                            })}
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

ConstructionSchedulesIndex.layout = {
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
