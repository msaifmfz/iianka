import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import {
    store as businessScheduleStore,
    update as businessScheduleUpdate,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { FloatingBackButton } from '@/components/floating-back-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    AttendanceLeaveRecord,
    BusinessSchedule,
    ConstructionUser,
    ScheduleAvailability,
} from '@/types';

type Props = {
    schedule: BusinessSchedule | null;
    users: ConstructionUser[];
    generalContractorOptions: string[];
    contentOptions: string[];
    scheduleAvailability: ScheduleAvailability[];
    attendanceLeaveRecords: AttendanceLeaveRecord[];
};

type BusinessScheduleForm = {
    _method: 'put' | '';
    scheduled_on: string;
    schedule_number: string;
    starts_at: string;
    ends_at: string;
    time_note: string;
    personnel: string;
    location: string;
    general_contractor: string;
    person_in_charge: string;
    content: string;
    memo: string;
    assigned_user_ids: number[];
};

const timeNotePresets = ['本日中'];
const contentMemoryStorageKey = 'business-schedule-content-options';
const preferredTimeSlots = [
    ['08:00', '10:00'],
    ['10:00', '12:00'],
    ['13:00', '15:00'],
    ['15:00', '17:00'],
    ['08:00', '12:00'],
    ['13:00', '17:00'],
    ['08:00', '17:00'],
] as const;

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            <span>{label}</span>
            {children}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </label>
    );
}

function toggleNumber(values: number[], value: number) {
    return values.includes(value)
        ? values.filter((item) => item !== value)
        : [...values, value];
}

function timeToMinutes(time: string) {
    const [hours, minutes] = time.slice(0, 5).split(':').map(Number);

    return hours * 60 + minutes;
}

function timesOverlap(
    startsAt: string,
    endsAt: string,
    existingStartsAt: string,
    existingEndsAt: string,
) {
    return (
        timeToMinutes(startsAt) < timeToMinutes(existingEndsAt) &&
        timeToMinutes(endsAt) > timeToMinutes(existingStartsAt)
    );
}

function conflictsWithSchedules(
    startsAt: string,
    endsAt: string,
    schedules: ScheduleAvailability[],
) {
    if (
        !startsAt ||
        !endsAt ||
        timeToMinutes(startsAt) >= timeToMinutes(endsAt)
    ) {
        return false;
    }

    return schedules.some((schedule) =>
        timesOverlap(startsAt, endsAt, schedule.starts_at, schedule.ends_at),
    );
}

function matchingBusySchedules(
    schedules: ScheduleAvailability[],
    scheduledOn: string,
    assignedUserIds: number[],
) {
    if (assignedUserIds.length === 0) {
        return [];
    }

    return schedules.filter(
        (schedule) =>
            schedule.scheduled_on === scheduledOn &&
            schedule.user_ids.some((userId) =>
                assignedUserIds.includes(userId),
            ),
    );
}

function matchingLeaveRecords(
    records: AttendanceLeaveRecord[],
    scheduledOn: string,
    assignedUserIds: number[],
) {
    if (assignedUserIds.length === 0) {
        return [];
    }

    return records.filter(
        (record) =>
            record.work_date === scheduledOn &&
            assignedUserIds.includes(record.user_id),
    );
}

function availableTimeSlots(schedules: ScheduleAvailability[]) {
    return preferredTimeSlots.filter(
        ([startsAt, endsAt]) =>
            !conflictsWithSchedules(startsAt, endsAt, schedules),
    );
}

function normalizeAutocompleteOption(value: string) {
    return value.trim();
}

function mergeAutocompleteOptions(...groups: string[][]) {
    return groups
        .flat()
        .map(normalizeAutocompleteOption)
        .filter((value, index, values) => {
            return value !== '' && values.indexOf(value) === index;
        })
        .sort((left, right) => left.localeCompare(right, 'ja'));
}

function loadRememberedContentOptions() {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const storedValue = window.localStorage.getItem(
            contentMemoryStorageKey,
        );

        if (storedValue === null) {
            return [];
        }

        const parsedValue: unknown = JSON.parse(storedValue);

        return Array.isArray(parsedValue)
            ? parsedValue.filter(
                  (value): value is string => typeof value === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

function persistRememberedContentOptions(options: string[]) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        contentMemoryStorageKey,
        JSON.stringify(options),
    );
}

export default function BusinessScheduleForm({
    schedule,
    users,
    generalContractorOptions,
    contentOptions,
    scheduleAvailability,
    attendanceLeaveRecords,
}: Props) {
    const { data, setData, post, processing, errors } =
        useForm<BusinessScheduleForm>({
            _method: schedule ? 'put' : '',
            scheduled_on:
                schedule?.scheduled_on ?? new Date().toISOString().slice(0, 10),
            schedule_number: schedule?.schedule_number?.toString() ?? '',
            starts_at: schedule?.starts_at?.slice(0, 5) ?? '',
            ends_at: schedule?.ends_at?.slice(0, 5) ?? '',
            time_note: schedule?.time_note ?? '',
            personnel: schedule?.personnel ?? '',
            location: schedule?.location ?? '',
            general_contractor: schedule?.general_contractor ?? '',
            person_in_charge: schedule?.person_in_charge ?? '',
            content: schedule?.content ?? '',
            memo: schedule?.memo ?? '',
            assigned_user_ids:
                schedule?.assigned_users.map((user) => user.id) ?? [],
        });
    const [rememberedContentOptions, setRememberedContentOptions] = useState<
        string[]
    >(loadRememberedContentOptions);
    const busySchedules = matchingBusySchedules(
        scheduleAvailability,
        data.scheduled_on,
        data.assigned_user_ids,
    );
    const leaveRecords = matchingLeaveRecords(
        attendanceLeaveRecords,
        data.scheduled_on,
        data.assigned_user_ids,
    );
    const hasTimeConflict = conflictsWithSchedules(
        data.starts_at,
        data.ends_at,
        busySchedules,
    );
    const suggestedTimeSlots = availableTimeSlots(busySchedules);
    const mergedContentOptions = mergeAutocompleteOptions(
        contentOptions,
        rememberedContentOptions,
        data.content ? [data.content] : [],
    );

    function rememberContentOption(value: string) {
        const normalizedValue = normalizeAutocompleteOption(value);

        if (normalizedValue === '') {
            return;
        }

        setRememberedContentOptions((currentOptions) => {
            const nextOptions = mergeAutocompleteOptions(currentOptions, [
                normalizedValue,
            ]);

            persistRememberedContentOptions(nextOptions);

            return nextOptions;
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        rememberContentOption(data.content);

        post(
            schedule
                ? businessScheduleUpdate.url(schedule.id)
                : businessScheduleStore.url(),
        );
    }

    function handleGoBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(scheduleIndex({ query: { type: 'all' } }));
    }

    function selectTimeNotePreset(timeNote: string) {
        setData((values) => ({
            ...values,
            starts_at: '',
            ends_at: '',
            time_note: timeNote,
        }));
    }

    function setStartTime(startsAt: string) {
        setData((values) => ({
            ...values,
            starts_at: startsAt,
            time_note: timeNotePresets.includes(values.time_note)
                ? ''
                : values.time_note,
        }));
    }

    function setEndTime(endsAt: string) {
        setData((values) => ({
            ...values,
            ends_at: endsAt,
            time_note: timeNotePresets.includes(values.time_note)
                ? ''
                : values.time_note,
        }));
    }

    function setTimeRange(startsAt: string, endsAt: string) {
        setData((values) => ({
            ...values,
            starts_at: startsAt,
            ends_at: endsAt,
            time_note: timeNotePresets.includes(values.time_note)
                ? ''
                : values.time_note,
        }));
    }

    return (
        <>
            <Head title={schedule ? '業務予定編集' : '新規業務予定'} />
            <FloatingBackButton
                onClick={handleGoBack}
                className="bottom-5 md:bottom-6 xl:bottom-8"
            />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 pb-24 sm:p-4 sm:pb-24 md:p-6 md:pb-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Business Schedule
                        </p>
                        <h1 className="text-2xl font-bold">
                            {schedule ? '業務予定編集' : '新規業務予定'}
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-3xl border bg-white p-4 shadow-sm sm:p-5 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <section className="grid gap-4 md:grid-cols-3">
                        <Field label="日付" error={errors.scheduled_on}>
                            <Input
                                type="date"
                                value={data.scheduled_on}
                                onChange={(event) =>
                                    setData('scheduled_on', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="番号" error={errors.schedule_number}>
                            <Input
                                type="number"
                                min="1"
                                value={data.schedule_number}
                                onChange={(event) =>
                                    setData(
                                        'schedule_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="例: 1"
                            />
                        </Field>
                        <div className="rounded-2xl border p-4 md:col-span-3 dark:border-neutral-800">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        担当ユーザー
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        選択した担当ユーザーの予定をもとに空き時間を表示します。
                                    </p>
                                </div>
                                {data.assigned_user_ids.length > 0 && (
                                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                                        {data.assigned_user_ids.length}名選択中
                                    </span>
                                )}
                            </div>
                            <div className="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                {users.map((user) => (
                                    <label
                                        key={user.id}
                                        className={cn(
                                            'flex items-center gap-2 rounded-xl border p-3 text-sm transition',
                                            data.assigned_user_ids.includes(
                                                user.id,
                                            )
                                                ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                                                : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.assigned_user_ids.includes(
                                                user.id,
                                            )}
                                            onChange={() =>
                                                setData(
                                                    'assigned_user_ids',
                                                    toggleNumber(
                                                        data.assigned_user_ids,
                                                        user.id,
                                                    ),
                                                )
                                            }
                                        />
                                        {user.name}
                                    </label>
                                ))}
                            </div>
                            {errors.assigned_user_ids && (
                                <p className="mt-2 text-xs text-destructive">
                                    {errors.assigned_user_ids}
                                </p>
                            )}
                            {leaveRecords.length > 0 && (
                                <div className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
                                    <div className="flex items-center gap-2 font-semibold">
                                        <AlertTriangle className="size-4" />
                                        選択した日に休みの担当者がいます
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {leaveRecords.map((record) => (
                                            <span
                                                key={record.id}
                                                className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-rose-900 ring-1 ring-rose-200 dark:bg-neutral-950 dark:text-rose-100 dark:ring-rose-900"
                                            >
                                                {record.user_name}
                                                {record.note
                                                    ? `: ${record.note}`
                                                    : ''}
                                            </span>
                                        ))}
                                    </div>
                                    <p className="mt-2 text-xs">
                                        登録は続行できます。必要に応じて担当者を調整してください。
                                    </p>
                                </div>
                            )}
                        </div>
                        <Field label="開始時間" error={errors.starts_at}>
                            <Input
                                type="time"
                                value={data.starts_at}
                                onChange={(event) =>
                                    setStartTime(event.target.value)
                                }
                            />
                        </Field>
                        <Field label="終了時間" error={errors.ends_at}>
                            <Input
                                type="time"
                                value={data.ends_at}
                                onChange={(event) =>
                                    setEndTime(event.target.value)
                                }
                            />
                        </Field>
                        <div className="rounded-2xl border bg-neutral-50 p-4 md:col-span-3 dark:border-neutral-800 dark:bg-neutral-900/50">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        時間の空き状況
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {data.assigned_user_ids.length === 0
                                            ? '担当ユーザーを選択すると、その日の重複予定を確認できます。'
                                            : `${data.scheduled_on} の選択ユーザーの予定を表示しています。`}
                                    </p>
                                </div>
                                {data.assigned_user_ids.length > 0 &&
                                    (hasTimeConflict ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-100">
                                            <AlertTriangle className="size-3.5" />
                                            重複あり
                                        </span>
                                    ) : (
                                        data.starts_at &&
                                        data.ends_at && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-100">
                                                <CheckCircle2 className="size-3.5" />
                                                登録可能
                                            </span>
                                        )
                                    ))}
                            </div>

                            {busySchedules.length > 0 ? (
                                <div className="mt-4 grid gap-2 md:grid-cols-2">
                                    {busySchedules.map((busySchedule) => (
                                        <div
                                            key={`${busySchedule.type}-${busySchedule.id}`}
                                            className="rounded-xl bg-white p-3 text-sm ring-1 ring-neutral-200 dark:bg-neutral-950 dark:ring-neutral-800"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-semibold">
                                                    {busySchedule.time}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {busySchedule.user_names.join(
                                                        '、',
                                                    )}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-muted-foreground">
                                                {busySchedule.title}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                data.assigned_user_ids.length > 0 && (
                                    <p className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                                        この日の選択ユーザーには時間指定の予定がありません。
                                    </p>
                                )
                            )}

                            {data.assigned_user_ids.length > 0 && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold text-muted-foreground">
                                        空き時間クイック選択
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {suggestedTimeSlots.length > 0 ? (
                                            suggestedTimeSlots.map(
                                                ([startsAt, endsAt]) => (
                                                    <button
                                                        key={`${startsAt}-${endsAt}`}
                                                        type="button"
                                                        className="rounded-full bg-white px-3 py-1.5 text-xs font-semibold ring-1 ring-neutral-200 transition hover:bg-amber-50 hover:text-amber-900 dark:bg-neutral-950 dark:ring-neutral-800 dark:hover:bg-amber-950/40 dark:hover:text-amber-100"
                                                        onClick={() =>
                                                            setTimeRange(
                                                                startsAt,
                                                                endsAt,
                                                            )
                                                        }
                                                    >
                                                        {startsAt} - {endsAt}
                                                    </button>
                                                ),
                                            )
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                推奨枠はすべて既存予定と重なっています。予定一覧を見ながら手入力してください。
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                        <Field label="時間メモ" error={errors.time_note}>
                            <Input
                                value={data.time_note}
                                onChange={(event) =>
                                    setData('time_note', event.target.value)
                                }
                                placeholder="例: 本日中、午前中、時間未定"
                            />
                            <div className="flex flex-wrap gap-2">
                                {timeNotePresets.map((timeNote) => (
                                    <button
                                        key={timeNote}
                                        type="button"
                                        className="rounded-full border px-3 py-1 text-xs text-muted-foreground transition hover:bg-muted"
                                        onClick={() =>
                                            selectTimeNotePreset(timeNote)
                                        }
                                    >
                                        {timeNote}
                                    </button>
                                ))}
                            </div>
                        </Field>
                        <Field label="人員" error={errors.personnel}>
                            <Input
                                value={data.personnel}
                                onChange={(event) =>
                                    setData('personnel', event.target.value)
                                }
                                placeholder="例: 3名"
                            />
                        </Field>
                    </section>

                    <section className="grid gap-4 md:grid-cols-2">
                        <Field label="場所" error={errors.location}>
                            <Input
                                value={data.location}
                                onChange={(event) =>
                                    setData('location', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="ゼネコン会社"
                            error={errors.general_contractor}
                        >
                            <Input
                                list="business-general-contractor-options"
                                value={data.general_contractor}
                                onChange={(event) =>
                                    setData(
                                        'general_contractor',
                                        event.target.value,
                                    )
                                }
                            />
                            <datalist id="business-general-contractor-options">
                                {generalContractorOptions.map(
                                    (generalContractor) => (
                                        <option
                                            key={generalContractor}
                                            value={generalContractor}
                                        />
                                    ),
                                )}
                            </datalist>
                        </Field>
                        <Field label="担当" error={errors.person_in_charge}>
                            <Input
                                value={data.person_in_charge}
                                onChange={(event) =>
                                    setData(
                                        'person_in_charge',
                                        event.target.value,
                                    )
                                }
                                placeholder="例: 佐藤 / 先方担当者"
                            />
                        </Field>
                    </section>

                    <Field label="内容" error={errors.content}>
                        <Input
                            list="business-content-options"
                            value={data.content}
                            onChange={(event) =>
                                setData('content', event.target.value)
                            }
                            onBlur={(event) =>
                                rememberContentOption(event.target.value)
                            }
                            placeholder="例: 安全協議会、定時総会"
                        />
                        <datalist id="business-content-options">
                            {mergedContentOptions.map((content) => (
                                <option key={content} value={content} />
                            ))}
                        </datalist>
                    </Field>

                    <Field label="メモ" error={errors.memo}>
                        <textarea
                            className="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.memo}
                            onChange={(event) =>
                                setData('memo', event.target.value)
                            }
                        />
                    </Field>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? '保存中...' : '保存'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

BusinessScheduleForm.layout = {
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
