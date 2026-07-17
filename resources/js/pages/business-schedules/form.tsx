import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    store as businessScheduleStore,
    update as businessScheduleUpdate,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { FloatingBackButton } from '@/components/floating-back-button';
import FormField from '@/components/form-field';
import { ScheduleAvailabilityPanel } from '@/components/schedule-availability-panel';
import { ScheduleStaffPicker } from '@/components/schedule-staff-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useScheduleTimeFields } from '@/hooks/use-schedule-time-fields';
import { businessDateString } from '@/lib/dates';
import { goBackToReturnTo } from '@/lib/return-to';
import {
    availableTimeSlots,
    conflictsWithSchedules,
    matchingBusySchedules,
    matchingLeaveRecords,
} from '@/lib/schedule-availability';
import type {
    AttendanceLeaveRecord,
    BusinessSchedule,
    ConstructionUser,
    ScheduleAvailability,
} from '@/types';

type Props = {
    schedule: BusinessSchedule | null;
    returnTo?: string | null;
    initialScheduledOn?: string | null;
    initialStartsAt?: string | null;
    initialEndsAt?: string | null;
    initialAssignedUserIds?: number[];
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
    returnTo,
    initialScheduledOn,
    initialStartsAt,
    initialEndsAt,
    initialAssignedUserIds = [],
    users,
    generalContractorOptions,
    contentOptions,
    scheduleAvailability,
    attendanceLeaveRecords,
}: Props) {
    const { url } = usePage();
    const { data, setData, post, processing, errors } =
        useForm<BusinessScheduleForm>({
            _method: schedule ? 'put' : '',
            scheduled_on:
                schedule?.scheduled_on ??
                initialScheduledOn ??
                businessDateString(),
            schedule_number: schedule?.schedule_number?.toString() ?? '',
            starts_at:
                schedule?.starts_at?.slice(0, 5) ?? initialStartsAt ?? '',
            ends_at: schedule?.ends_at?.slice(0, 5) ?? initialEndsAt ?? '',
            time_note: schedule?.time_note ?? '',
            personnel: schedule?.personnel ?? '',
            location: schedule?.location ?? '',
            general_contractor: schedule?.general_contractor ?? '',
            person_in_charge: schedule?.person_in_charge ?? '',
            content: schedule?.content ?? '',
            memo: schedule?.memo ?? '',
            assigned_user_ids:
                schedule?.assigned_users.map((user) => user.id) ??
                initialAssignedUserIds,
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
    const submitLabel = schedule ? '業務予定を修正' : '業務予定を作成';
    const processingLabel = schedule
        ? '業務予定を修正中...'
        : '業務予定を作成中...';

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

        const options =
            returnTo === null || returnTo === undefined
                ? undefined
                : { query: { return_to: returnTo } };

        post(
            schedule
                ? businessScheduleUpdate.url(schedule.id, options)
                : businessScheduleStore.url(options),
        );
    }

    function handleGoBack() {
        goBackToReturnTo(
            url,
            returnTo,
            scheduleIndex({ query: { type: 'all' } }),
        );
    }

    const { selectTimeNotePreset, setStartTime, setEndTime, setTimeRange } =
        useScheduleTimeFields(setData, timeNotePresets);

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
                        <FormField
                            label="日付"
                            required
                            error={errors.scheduled_on}
                        >
                            <Input
                                type="date"
                                required
                                value={data.scheduled_on}
                                onChange={(event) =>
                                    setData('scheduled_on', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField label="番号" error={errors.schedule_number}>
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
                        </FormField>
                        <ScheduleStaffPicker
                            users={users}
                            assignedUserIds={data.assigned_user_ids}
                            leaveRecords={leaveRecords}
                            error={errors.assigned_user_ids}
                            onChangeAssignedUserIds={(userIds) =>
                                setData('assigned_user_ids', userIds)
                            }
                        />
                        <FormField label="開始時間" error={errors.starts_at}>
                            <Input
                                type="time"
                                value={data.starts_at}
                                onChange={(event) =>
                                    setStartTime(event.target.value)
                                }
                            />
                        </FormField>
                        <FormField label="終了時間" error={errors.ends_at}>
                            <Input
                                type="time"
                                value={data.ends_at}
                                onChange={(event) =>
                                    setEndTime(event.target.value)
                                }
                            />
                        </FormField>
                        <ScheduleAvailabilityPanel
                            scheduledOn={data.scheduled_on}
                            assignedUserCount={data.assigned_user_ids.length}
                            busySchedules={busySchedules}
                            hasTimeConflict={hasTimeConflict}
                            hasTimeRange={Boolean(
                                data.starts_at && data.ends_at,
                            )}
                            suggestedTimeSlots={suggestedTimeSlots}
                            onSelectTimeSlot={setTimeRange}
                        />
                        <FormField label="時間メモ" error={errors.time_note}>
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
                        </FormField>
                        <FormField label="人員" error={errors.personnel}>
                            <Input
                                value={data.personnel}
                                onChange={(event) =>
                                    setData('personnel', event.target.value)
                                }
                                placeholder="例: 3名"
                            />
                        </FormField>
                    </section>

                    <section className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="場所"
                            required
                            error={errors.location}
                        >
                            <Input
                                required
                                value={data.location}
                                onChange={(event) =>
                                    setData('location', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
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
                        </FormField>
                        <FormField label="担当" error={errors.person_in_charge}>
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
                        </FormField>
                    </section>

                    <FormField label="内容" required error={errors.content}>
                        <Input
                            list="business-content-options"
                            required
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
                    </FormField>

                    <FormField label="メモ" error={errors.memo}>
                        <textarea
                            className="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.memo}
                            onChange={(event) =>
                                setData('memo', event.target.value)
                            }
                        />
                    </FormField>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? processingLabel : submitLabel}
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
            title: '予定表',
            href: scheduleIndex(),
        },
    ],
};
