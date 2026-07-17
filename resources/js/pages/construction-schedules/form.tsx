import { Head, useForm, usePage } from '@inertiajs/react';
import {} from 'lucide-react';
import {
    index as scheduleIndex,
    store as scheduleStore,
    update as scheduleUpdate,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { FloatingBackButton } from '@/components/floating-back-button';
import FormField from '@/components/form-field';
import { ScheduleAvailabilityPanel } from '@/components/schedule-availability-panel';
import { ScheduleContentEditor } from '@/components/schedule-content-editor';
import { ScheduleStaffPicker } from '@/components/schedule-staff-picker';
import { SiteGuideFilePicker } from '@/components/site-guide-file-picker';
import { SubcontractorPicker } from '@/components/subcontractor-picker';
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
import { dashboard } from '@/routes';
import type {
    ConstructionSchedule,
    ConstructionScheduleStatus,
    ConstructionSubcontractor,
    ConstructionUser,
    AttendanceLeaveRecord,
    ScheduleAvailability,
    SiteGuideFile,
    StockOption,
} from '@/types';

type Props = {
    schedule: ConstructionSchedule | null;
    returnTo?: string | null;
    initialScheduledOn?: string | null;
    initialStartsAt?: string | null;
    initialEndsAt?: string | null;
    initialAssignedUserIds?: number[];
    users: ConstructionUser[];
    subcontractors: ConstructionSubcontractor[];
    siteGuideFiles: SiteGuideFile[];
    generalContractorOptions: string[];
    scheduleAvailability: ScheduleAvailability[];
    attendanceLeaveRecords: AttendanceLeaveRecord[];
    stockOptions: StockOption[];
};

type ScheduleForm = {
    _method: 'put' | '';
    scheduled_on: string;
    schedule_number: string;
    starts_at: string;
    ends_at: string;
    time_note: string;
    status: ConstructionScheduleStatus;
    meeting_place: string;
    personnel: string;
    location: string;
    site_region: string;
    general_contractor: string;
    person_in_charge: string;
    content: string;
    content_version: string;
    carry_out_note: string;
    navigation_address: string;
    assigned_user_ids: number[];
    subcontractor_ids: number[];
    new_subcontractors: {
        name: string;
        phone: string;
    }[];
    site_guide_file_ids: number[];
    guide_files: File[];
    guide_file_names: string[];
};

const statuses: { value: ConstructionScheduleStatus; label: string }[] = [
    { value: 'scheduled', label: '予定' },
    { value: 'confirmed', label: '確定' },
    { value: 'postponed', label: '延期' },
    { value: 'canceled', label: '中止' },
];

const siteRegionOptions = [
    '北海道',
    '青森県',
    '岩手県',
    '宮城県',
    '秋田県',
    '山形県',
    '福島県',
    '茨城県',
    '栃木県',
    '群馬県',
    '埼玉県',
    '千葉県',
    '東京都',
    '神奈川県',
    '新潟県',
    '富山県',
    '石川県',
    '福井県',
    '山梨県',
    '長野県',
    '岐阜県',
    '静岡県',
    '愛知県',
    '三重県',
    '滋賀県',
    '京都府',
    '大阪府',
    '兵庫県',
    '奈良県',
    '和歌山県',
    '鳥取県',
    '島根県',
    '岡山県',
    '広島県',
    '山口県',
    '徳島県',
    '香川県',
    '愛媛県',
    '高知県',
    '福岡県',
    '佐賀県',
    '長崎県',
    '熊本県',
    '大分県',
    '宮崎県',
    '鹿児島県',
    '沖縄県',
] as const;

const timeNotePresets = ['本日中', '午前中', '午後中', '時間未定'];
export default function ConstructionScheduleForm({
    schedule,
    returnTo,
    initialScheduledOn,
    initialStartsAt,
    initialEndsAt,
    initialAssignedUserIds = [],
    users,
    subcontractors,
    siteGuideFiles,
    generalContractorOptions,
    scheduleAvailability,
    attendanceLeaveRecords,
    stockOptions,
}: Props) {
    const { url } = usePage();
    const { data, setData, post, processing, progress, errors } =
        useForm<ScheduleForm>({
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
            status: schedule?.status ?? 'scheduled',
            meeting_place: schedule?.meeting_place ?? '',
            personnel: schedule?.personnel ?? '',
            location: schedule?.location ?? '',
            site_region: schedule ? (schedule.site_region ?? '') : '滋賀県',
            general_contractor: schedule?.general_contractor ?? '',
            person_in_charge: schedule?.person_in_charge ?? '',
            content: schedule?.content ?? '',
            content_version: schedule?.content_version?.toString() ?? '',
            carry_out_note: schedule?.carry_out_note ?? '',
            navigation_address: schedule?.navigation_address ?? '',
            assigned_user_ids:
                schedule?.assigned_users.map((user) => user.id) ??
                initialAssignedUserIds,
            subcontractor_ids:
                schedule?.subcontractors.map(
                    (subcontractor) => subcontractor.id,
                ) ?? [],
            new_subcontractors: [],
            site_guide_file_ids: schedule?.selected_site_guide_file_ids ?? [],
            guide_files: [],
            guide_file_names: [],
        });
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
    const submitLabel = schedule ? '工事予定を修正' : '工事予定を作成';
    const processingLabel = schedule
        ? '工事予定を修正中...'
        : '工事予定を作成中...';

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options =
            returnTo === null || returnTo === undefined
                ? undefined
                : { query: { return_to: returnTo } };

        post(
            schedule
                ? scheduleUpdate.url(schedule.id, options)
                : scheduleStore.url(options),
            {
                forceFormData: true,
            },
        );
    }

    function handleGoBack() {
        goBackToReturnTo(url, returnTo, scheduleIndex());
    }

    const { selectTimeNotePreset, setStartTime, setEndTime, setTimeRange } =
        useScheduleTimeFields(setData, timeNotePresets);

    return (
        <>
            <Head title={schedule ? '予定編集' : '新規予定'} />
            <FloatingBackButton
                onClick={handleGoBack}
                className="bottom-5 md:bottom-6 xl:bottom-8"
            />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 pb-24 sm:p-4 sm:pb-24 md:p-6 md:pb-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Construction Schedule
                        </p>
                        <h1 className="text-2xl font-bold">
                            {schedule ? '予定編集' : '新規予定'}
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
                        <SubcontractorPicker
                            subcontractors={subcontractors}
                            selectedIds={data.subcontractor_ids}
                            onChangeSelectedIds={(ids) =>
                                setData('subcontractor_ids', ids)
                            }
                            newSubcontractors={data.new_subcontractors}
                            onChangeNewSubcontractors={(next) =>
                                setData('new_subcontractors', next)
                            }
                            errors={errors}
                            onDeleted={(subcontractorId) => {
                                if (!schedule) {
                                    setData(
                                        'subcontractor_ids',
                                        data.subcontractor_ids.filter(
                                            (id) => id !== subcontractorId,
                                        ),
                                    );
                                }
                            }}
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
                        <FormField
                            label="予定か"
                            required
                            error={errors.status}
                        >
                            <select
                                required
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                value={data.status}
                                onChange={(event) =>
                                    setData(
                                        'status',
                                        event.target
                                            .value as ConstructionScheduleStatus,
                                    )
                                }
                            >
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField label="人員" error={errors.personnel}>
                            <Input
                                value={data.personnel}
                                onChange={(event) =>
                                    setData('personnel', event.target.value)
                                }
                                placeholder="例: 5名 / A班"
                            />
                        </FormField>
                    </section>

                    <section className="grid gap-4 md:grid-cols-3">
                        <FormField
                            label="現場名"
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
                        <FormField label="現場地域" error={errors.site_region}>
                            <select
                                className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                value={data.site_region}
                                onChange={(event) =>
                                    setData('site_region', event.target.value)
                                }
                            >
                                <option value="">選択なし</option>
                                {siteRegionOptions.map((siteRegion) => (
                                    <option key={siteRegion} value={siteRegion}>
                                        {siteRegion}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField
                            label="集合場所（任意）"
                            error={errors.meeting_place}
                        >
                            <Input
                                value={data.meeting_place}
                                onChange={(event) =>
                                    setData('meeting_place', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="ゼネコン会社"
                            error={errors.general_contractor}
                        >
                            <Input
                                list="general-contractor-options"
                                value={data.general_contractor}
                                onChange={(event) =>
                                    setData(
                                        'general_contractor',
                                        event.target.value,
                                    )
                                }
                            />
                            <datalist id="general-contractor-options">
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
                        <FormField
                            label="現場担当者"
                            error={errors.person_in_charge}
                        >
                            <Input
                                value={data.person_in_charge}
                                onChange={(event) =>
                                    setData(
                                        'person_in_charge',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="ナビ（Google Map用住所・任意）"
                            error={errors.navigation_address}
                        >
                            <Input
                                value={data.navigation_address}
                                onChange={(event) =>
                                    setData(
                                        'navigation_address',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </section>

                    <FormField
                        as="div"
                        labelId="schedule-content-label"
                        label="内容（任意）"
                        error={errors.content}
                    >
                        <ScheduleContentEditor
                            defaultValue={schedule?.content ?? ''}
                            onChange={(content) => setData('content', content)}
                            stocks={stockOptions}
                            ariaLabelledBy="schedule-content-label"
                        />
                    </FormField>

                    <FormField
                        label="持ち出し（任意）"
                        error={errors.carry_out_note}
                    >
                        <textarea
                            className="min-h-32 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.carry_out_note}
                            onChange={(event) =>
                                setData('carry_out_note', event.target.value)
                            }
                        />
                    </FormField>

                    <section className="grid gap-4">
                        <SiteGuideFilePicker
                            siteGuideFiles={siteGuideFiles}
                            selectedIds={data.site_guide_file_ids}
                            onChangeSelectedIds={(ids) =>
                                setData('site_guide_file_ids', ids)
                            }
                            uploads={data.guide_files}
                            uploadNames={data.guide_file_names}
                            onChangeUploads={(files, names) =>
                                setData((values) => ({
                                    ...values,
                                    guide_files: files,
                                    guide_file_names: names,
                                }))
                            }
                            errors={errors}
                        />
                    </section>

                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            className="w-full"
                        />
                    )}

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        {hasTimeConflict && (
                            <p className="text-sm font-medium text-destructive">
                                スタッフの既存予定と重複しています。
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={processing || hasTimeConflict}
                        >
                            {processing ? processingLabel : submitLabel}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ConstructionScheduleForm.layout = {
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
