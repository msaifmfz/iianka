import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    Files,
    FileText,
    Pencil,
    Phone,
    Plus,
    Save,
    Search,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    index as scheduleIndex,
    store as scheduleStore,
    update as scheduleUpdate,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    destroy as subcontractorDestroy,
    update as subcontractorUpdate,
} from '@/actions/App/Http/Controllers/ConstructionSubcontractorController';
import { FloatingBackButton } from '@/components/floating-back-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { businessDateString } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    ConstructionSchedule,
    ConstructionScheduleStatus,
    ConstructionSubcontractor,
    ConstructionUser,
    AttendanceLeaveRecord,
    ScheduleAvailability,
    SiteGuideFile,
} from '@/types';

type Props = {
    schedule: ConstructionSchedule | null;
    users: ConstructionUser[];
    subcontractors: ConstructionSubcontractor[];
    siteGuideFiles: SiteGuideFile[];
    generalContractorOptions: string[];
    scheduleAvailability: ScheduleAvailability[];
    attendanceLeaveRecords: AttendanceLeaveRecord[];
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
    general_contractor: string;
    person_in_charge: string;
    content: string;
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

type ExistingSubcontractorForm = {
    name: string;
    phone: string;
};

const statuses: { value: ConstructionScheduleStatus; label: string }[] = [
    { value: 'scheduled', label: '予定' },
    { value: 'confirmed', label: '確定' },
    { value: 'postponed', label: '延期' },
    { value: 'canceled', label: '中止' },
];

const timeNotePresets = ['本日中', '午前中', '午後中', '時間未定'];
const preferredTimeSlots = [
    ['08:00', '10:00'],
    ['10:00', '12:00'],
    ['13:00', '15:00'],
    ['15:00', '17:00'],
    ['08:00', '12:00'],
    ['13:00', '17:00'],
    ['08:00', '17:00'],
] as const;

const guideFileAccept =
    'application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.webp,.heic,.heif';

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

function phoneHref(phone: string) {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

function guideFileTypeLabel(file: SiteGuideFile) {
    if (file.mime_type?.includes('pdf')) {
        return 'PDF';
    }

    if (file.mime_type?.startsWith('image/')) {
        return '画像';
    }

    return 'ファイル';
}

function defaultGuideFileName(file: File) {
    const nameWithoutExtension = file.name.replace(/\.[^/.]+$/, '').trim();

    return nameWithoutExtension || file.name;
}

function fileSizeLabel(size: number) {
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }

    if (size >= 1024) {
        return `${Math.ceil(size / 1024)} KB`;
    }

    return `${size} B`;
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

export default function ConstructionScheduleForm({
    schedule,
    users,
    subcontractors,
    siteGuideFiles,
    generalContractorOptions,
    scheduleAvailability,
    attendanceLeaveRecords,
}: Props) {
    const [guideFileSearch, setGuideFileSearch] = useState('');
    const [subcontractorSearch, setSubcontractorSearch] = useState('');
    const [editingSubcontractorId, setEditingSubcontractorId] = useState<
        number | null
    >(null);
    const { data, setData, post, processing, progress, errors } =
        useForm<ScheduleForm>({
            _method: schedule ? 'put' : '',
            scheduled_on: schedule?.scheduled_on ?? businessDateString(),
            schedule_number: schedule?.schedule_number?.toString() ?? '',
            starts_at: schedule?.starts_at?.slice(0, 5) ?? '',
            ends_at: schedule?.ends_at?.slice(0, 5) ?? '',
            time_note: schedule?.time_note ?? '',
            status: schedule?.status ?? 'scheduled',
            meeting_place: schedule?.meeting_place ?? '',
            personnel: schedule?.personnel ?? '',
            location: schedule?.location ?? '',
            general_contractor: schedule?.general_contractor ?? '',
            person_in_charge: schedule?.person_in_charge ?? '',
            content: schedule?.content ?? '',
            navigation_address: schedule?.navigation_address ?? '',
            assigned_user_ids:
                schedule?.assigned_users.map((user) => user.id) ?? [],
            subcontractor_ids:
                schedule?.subcontractors.map(
                    (subcontractor) => subcontractor.id,
                ) ?? [],
            new_subcontractors: [],
            site_guide_file_ids: schedule?.selected_site_guide_file_ids ?? [],
            guide_files: [],
            guide_file_names: [],
        });
    const {
        data: editingSubcontractorData,
        setData: setEditingSubcontractorData,
        patch: patchSubcontractor,
        processing: processingSubcontractorUpdate,
        errors: subcontractorErrors,
        clearErrors: clearSubcontractorErrors,
        reset: resetEditingSubcontractor,
    } = useForm<ExistingSubcontractorForm>({
        name: '',
        phone: '',
    });

    const formErrors = errors as Record<string, string | undefined>;
    const subcontractorSearchTerm = subcontractorSearch
        .trim()
        .toLocaleLowerCase();
    const filteredSubcontractors =
        subcontractorSearchTerm === ''
            ? subcontractors
            : subcontractors.filter((subcontractor) =>
                  `${subcontractor.name} ${subcontractor.phone ?? ''}`
                      .toLocaleLowerCase()
                      .includes(subcontractorSearchTerm),
              );
    const guideFileSearchTerm = guideFileSearch.trim().toLocaleLowerCase();
    const filteredSiteGuideFiles =
        guideFileSearchTerm === ''
            ? siteGuideFiles
            : siteGuideFiles.filter((file) =>
                  `${file.name} ${guideFileTypeLabel(file)}`
                      .toLocaleLowerCase()
                      .includes(guideFileSearchTerm),
              );
    const selectedVisibleSiteGuideFileIds = filteredSiteGuideFiles
        .filter((file) => data.site_guide_file_ids.includes(file.id))
        .map((file) => file.id);
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

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(schedule ? scheduleUpdate.url(schedule.id) : scheduleStore.url(), {
            forceFormData: true,
        });
    }

    function handleGoBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(scheduleIndex());
    }

    function addGuideUploads(files: File[]) {
        if (files.length === 0) {
            return;
        }

        setData((values) => ({
            ...values,
            guide_files: [...values.guide_files, ...files],
            guide_file_names: [
                ...values.guide_file_names,
                ...files.map((file) => defaultGuideFileName(file)),
            ],
        }));
    }

    function removeGuideUpload(index: number) {
        setData((values) => ({
            ...values,
            guide_files: values.guide_files.filter(
                (_file, fileIndex) => fileIndex !== index,
            ),
            guide_file_names: values.guide_file_names.filter(
                (_name, nameIndex) => nameIndex !== index,
            ),
        }));
    }

    function addNewSubcontractor() {
        setData((values) => ({
            ...values,
            new_subcontractors: [
                ...values.new_subcontractors,
                { name: '', phone: '' },
            ],
        }));
    }

    function updateNewSubcontractor(
        index: number,
        field: 'name' | 'phone',
        value: string,
    ) {
        setData((values) => ({
            ...values,
            new_subcontractors: values.new_subcontractors.map(
                (subcontractor, subcontractorIndex) =>
                    subcontractorIndex === index
                        ? { ...subcontractor, [field]: value }
                        : subcontractor,
            ),
        }));
    }

    function removeNewSubcontractor(index: number) {
        setData((values) => ({
            ...values,
            new_subcontractors: values.new_subcontractors.filter(
                (_subcontractor, subcontractorIndex) =>
                    subcontractorIndex !== index,
            ),
        }));
    }

    function editSubcontractor(subcontractor: ConstructionSubcontractor) {
        clearSubcontractorErrors();
        setEditingSubcontractorId(subcontractor.id);
        setEditingSubcontractorData({
            name: subcontractor.name,
            phone: subcontractor.phone ?? '',
        });
    }

    function cancelSubcontractorEdit() {
        clearSubcontractorErrors();
        setEditingSubcontractorId(null);
        resetEditingSubcontractor();
    }

    function saveSubcontractorEdit(subcontractor: ConstructionSubcontractor) {
        patchSubcontractor(subcontractorUpdate.url(subcontractor.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingSubcontractorId(null);
                resetEditingSubcontractor();
            },
        });
    }

    function deleteSubcontractor(subcontractor: ConstructionSubcontractor) {
        if (
            !confirm(`${subcontractor.name} を今後の選択肢から削除しますか？`)
        ) {
            return;
        }

        router.delete(subcontractorDestroy.url(subcontractor.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (!schedule) {
                    setData(
                        'subcontractor_ids',
                        data.subcontractor_ids.filter(
                            (subcontractorId) =>
                                subcontractorId !== subcontractor.id,
                        ),
                    );
                }
            },
        });
    }

    function updateGuideUploadName(index: number, name: string) {
        setData((values) => ({
            ...values,
            guide_file_names: values.guide_file_names.map(
                (guideFileName, nameIndex) =>
                    nameIndex === index ? name : guideFileName,
            ),
        }));
    }

    function selectVisibleGuideFiles() {
        setData('site_guide_file_ids', [
            ...new Set([
                ...data.site_guide_file_ids,
                ...filteredSiteGuideFiles.map((file) => file.id),
            ]),
        ]);
    }

    function clearVisibleGuideFiles() {
        const visibleFileIds = new Set(
            filteredSiteGuideFiles.map((file) => file.id),
        );

        setData(
            'site_guide_file_ids',
            data.site_guide_file_ids.filter(
                (fileId) => !visibleFileIds.has(fileId),
            ),
        );
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
                                    <h2 className="font-semibold">スタッフ</h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        選択したスタッフの予定をもとに空き時間を表示します。
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
                        <div className="rounded-2xl border p-4 md:col-span-3 dark:border-neutral-800">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">下請け</h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        工事予定に入る下請けを選択し、電話番号をすぐ確認できます。
                                    </p>
                                </div>
                                {(data.subcontractor_ids.length > 0 ||
                                    data.new_subcontractors.length > 0) && (
                                    <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                                        選択 {data.subcontractor_ids.length} /
                                        追加 {data.new_subcontractors.length}
                                    </span>
                                )}
                            </div>

                            <div className="relative mt-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={subcontractorSearch}
                                    onChange={(event) =>
                                        setSubcontractorSearch(
                                            event.target.value,
                                        )
                                    }
                                    className="pl-9"
                                    placeholder="名前・電話番号で検索"
                                />
                            </div>

                            <div className="mt-3 max-h-80 overflow-y-auto rounded-lg border dark:border-neutral-800">
                                <div className="grid gap-2 p-2 sm:grid-cols-2">
                                    {filteredSubcontractors.map(
                                        (subcontractor) => {
                                            const isSelected =
                                                data.subcontractor_ids.includes(
                                                    subcontractor.id,
                                                );
                                            const isEditing =
                                                editingSubcontractorId ===
                                                subcontractor.id;

                                            return (
                                                <div
                                                    key={subcontractor.id}
                                                    className={cn(
                                                        'flex items-start justify-between gap-3 rounded-xl border p-3 text-sm transition',
                                                        isSelected
                                                            ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                                                            : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                                        isEditing &&
                                                            'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
                                                    )}
                                                >
                                                    {isEditing ? (
                                                        <div className="grid min-w-0 flex-1 gap-2">
                                                            <div className="grid gap-2 sm:grid-cols-2">
                                                                <Input
                                                                    value={
                                                                        editingSubcontractorData.name
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setEditingSubcontractorData(
                                                                            'name',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="名前"
                                                                    autoFocus
                                                                />
                                                                <Input
                                                                    value={
                                                                        editingSubcontractorData.phone
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setEditingSubcontractorData(
                                                                            'phone',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="電話番号（任意）"
                                                                />
                                                            </div>
                                                            {(subcontractorErrors.name ||
                                                                subcontractorErrors.phone) && (
                                                                <div className="grid gap-1 text-xs text-destructive sm:grid-cols-2">
                                                                    <span>
                                                                        {
                                                                            subcontractorErrors.name
                                                                        }
                                                                    </span>
                                                                    <span>
                                                                        {
                                                                            subcontractorErrors.phone
                                                                        }
                                                                    </span>
                                                                </div>
                                                            )}
                                                            <div className="flex flex-wrap gap-2">
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center gap-1 rounded-md bg-sky-700 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-sky-500 dark:text-sky-950 dark:hover:bg-sky-400"
                                                                    onClick={() =>
                                                                        saveSubcontractorEdit(
                                                                            subcontractor,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        processingSubcontractorUpdate
                                                                    }
                                                                >
                                                                    <Save className="size-3.5" />
                                                                    保存
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center gap-1 rounded-md border bg-background px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                    onClick={
                                                                        cancelSubcontractorEdit
                                                                    }
                                                                >
                                                                    <X className="size-3.5" />
                                                                    キャンセル
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-2">
                                                                <input
                                                                    type="checkbox"
                                                                    className="mt-1"
                                                                    checked={
                                                                        isSelected
                                                                    }
                                                                    onChange={() =>
                                                                        setData(
                                                                            'subcontractor_ids',
                                                                            toggleNumber(
                                                                                data.subcontractor_ids,
                                                                                subcontractor.id,
                                                                            ),
                                                                        )
                                                                    }
                                                                />
                                                                <span className="min-w-0">
                                                                    <span className="block truncate font-medium">
                                                                        {
                                                                            subcontractor.name
                                                                        }
                                                                    </span>
                                                                    {subcontractor.phone && (
                                                                        <a
                                                                            href={phoneHref(
                                                                                subcontractor.phone,
                                                                            )}
                                                                            className="mt-1 inline-flex items-center gap-1 text-xs text-sky-700 hover:underline dark:text-sky-300"
                                                                        >
                                                                            <Phone className="size-3.5" />
                                                                            {
                                                                                subcontractor.phone
                                                                            }
                                                                        </a>
                                                                    )}
                                                                </span>
                                                            </label>
                                                            <div className="flex shrink-0 flex-col gap-2">
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                    onClick={() =>
                                                                        editSubcontractor(
                                                                            subcontractor,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="size-3.5" />
                                                                    編集
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                    onClick={() =>
                                                                        deleteSubcontractor(
                                                                            subcontractor,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                    削除
                                                                </button>
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            );
                                        },
                                    )}
                                    {subcontractors.length === 0 && (
                                        <p className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground sm:col-span-2 dark:border-neutral-800">
                                            登録済みの下請けはまだありません。下の入力から追加できます。
                                        </p>
                                    )}
                                    {subcontractors.length > 0 &&
                                        filteredSubcontractors.length === 0 && (
                                            <p className="rounded-xl border border-dashed p-3 text-sm text-muted-foreground sm:col-span-2 dark:border-neutral-800">
                                                一致する下請けはありません。
                                            </p>
                                        )}
                                </div>
                            </div>

                            {errors.subcontractor_ids && (
                                <p className="mt-2 text-xs text-destructive">
                                    {errors.subcontractor_ids}
                                </p>
                            )}

                            <div className="mt-4 grid gap-3 rounded-lg border bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-sm font-medium">
                                        新しく追加する下請け
                                    </p>
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted"
                                        onClick={addNewSubcontractor}
                                    >
                                        <Plus className="size-4" />
                                        行を追加
                                    </button>
                                </div>

                                {data.new_subcontractors.length > 0 ? (
                                    <div className="grid gap-2">
                                        {data.new_subcontractors.map(
                                            (subcontractor, index) => (
                                                <div
                                                    key={index}
                                                    className="grid gap-2 rounded-lg border bg-background p-3 dark:border-neutral-800"
                                                >
                                                    <div className="grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                                                        <Input
                                                            value={
                                                                subcontractor.name
                                                            }
                                                            onChange={(event) =>
                                                                updateNewSubcontractor(
                                                                    index,
                                                                    'name',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="名前"
                                                        />
                                                        <Input
                                                            value={
                                                                subcontractor.phone
                                                            }
                                                            onChange={(event) =>
                                                                updateNewSubcontractor(
                                                                    index,
                                                                    'phone',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="電話番号（任意）"
                                                        />
                                                        <button
                                                            type="button"
                                                            className="inline-flex items-center justify-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                            onClick={() =>
                                                                removeNewSubcontractor(
                                                                    index,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                            削除
                                                        </button>
                                                    </div>
                                                    {(formErrors[
                                                        `new_subcontractors.${index}.name`
                                                    ] ||
                                                        formErrors[
                                                            `new_subcontractors.${index}.phone`
                                                        ]) && (
                                                        <div className="grid gap-1 text-xs text-destructive md:grid-cols-2">
                                                            <span>
                                                                {
                                                                    formErrors[
                                                                        `new_subcontractors.${index}.name`
                                                                    ]
                                                                }
                                                            </span>
                                                            <span>
                                                                {
                                                                    formErrors[
                                                                        `new_subcontractors.${index}.phone`
                                                                    ]
                                                                }
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        複数の下請けをまとめて追加できます。
                                    </p>
                                )}
                            </div>
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
                                            ? 'スタッフを選択すると、その日の重複予定を確認できます。'
                                            : `${data.scheduled_on} の選択スタッフの予定を表示しています。`}
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
                                        この日の選択スタッフには時間指定の予定がありません。
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
                        <Field label="予定か" error={errors.status}>
                            <select
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
                        </Field>
                        <Field label="人員" error={errors.personnel}>
                            <Input
                                value={data.personnel}
                                onChange={(event) =>
                                    setData('personnel', event.target.value)
                                }
                                placeholder="例: 5名 / A班"
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
                            label="集合場所（任意）"
                            error={errors.meeting_place}
                        >
                            <Input
                                value={data.meeting_place}
                                onChange={(event) =>
                                    setData('meeting_place', event.target.value)
                                }
                            />
                        </Field>
                        <Field
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
                        </Field>
                        <Field
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
                        </Field>
                        <Field
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
                        </Field>
                    </section>

                    <Field label="内容（任意）" error={errors.content}>
                        <textarea
                            className="min-h-32 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.content}
                            onChange={(event) =>
                                setData('content', event.target.value)
                            }
                        />
                    </Field>

                    <section className="grid gap-4">
                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        現場案内図
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        登録済みの案内図を探して選択、または新しいファイルをまとめて追加できます。
                                    </p>
                                </div>
                                {(data.site_guide_file_ids.length > 0 ||
                                    data.guide_files.length > 0) && (
                                    <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
                                        選択 {data.site_guide_file_ids.length} /
                                        追加 {data.guide_files.length}
                                    </span>
                                )}
                            </div>

                            <div className="mt-4 grid gap-3">
                                <div className="grid gap-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-medium">
                                            登録済みから選択
                                        </p>
                                        {filteredSiteGuideFiles.length > 0 && (
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    className="rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                                    disabled={
                                                        selectedVisibleSiteGuideFileIds.length ===
                                                        filteredSiteGuideFiles.length
                                                    }
                                                    onClick={
                                                        selectVisibleGuideFiles
                                                    }
                                                >
                                                    表示中をすべて選択
                                                </button>
                                                <button
                                                    type="button"
                                                    className="rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                                                    disabled={
                                                        selectedVisibleSiteGuideFileIds.length ===
                                                        0
                                                    }
                                                    onClick={
                                                        clearVisibleGuideFiles
                                                    }
                                                >
                                                    表示中を解除
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={guideFileSearch}
                                            onChange={(event) =>
                                                setGuideFileSearch(
                                                    event.target.value,
                                                )
                                            }
                                            className="pl-9"
                                            placeholder="表示名で検索"
                                        />
                                    </div>

                                    <div className="max-h-80 overflow-y-auto rounded-lg border dark:border-neutral-800">
                                        {siteGuideFiles.length === 0 ? (
                                            <p className="p-3 text-sm text-muted-foreground">
                                                登録済みの案内図はまだありません。下のアップロードから追加できます。
                                            </p>
                                        ) : filteredSiteGuideFiles.length ? (
                                            <div className="grid gap-2 p-2">
                                                {filteredSiteGuideFiles.map(
                                                    (file) => {
                                                        const isSelected =
                                                            data.site_guide_file_ids.includes(
                                                                file.id,
                                                            );
                                                        const inputId = `site-guide-file-${file.id}`;

                                                        return (
                                                            <div
                                                                key={file.id}
                                                                className={cn(
                                                                    'flex items-start gap-3 rounded-lg border p-3 transition',
                                                                    isSelected
                                                                        ? 'border-primary bg-primary/5'
                                                                        : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                                                )}
                                                            >
                                                                <label
                                                                    htmlFor={
                                                                        inputId
                                                                    }
                                                                    className="flex min-w-0 flex-1 cursor-pointer items-start gap-3 text-sm"
                                                                >
                                                                    <input
                                                                        id={
                                                                            inputId
                                                                        }
                                                                        type="checkbox"
                                                                        className="mt-1"
                                                                        checked={
                                                                            isSelected
                                                                        }
                                                                        onChange={() =>
                                                                            setData(
                                                                                'site_guide_file_ids',
                                                                                toggleNumber(
                                                                                    data.site_guide_file_ids,
                                                                                    file.id,
                                                                                ),
                                                                            )
                                                                        }
                                                                    />
                                                                    <span className="min-w-0 space-y-1">
                                                                        <span className="flex min-w-0 items-center gap-2 font-medium">
                                                                            <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                                            <span className="truncate">
                                                                                {
                                                                                    file.name
                                                                                }
                                                                            </span>
                                                                        </span>
                                                                        <span className="block text-xs text-muted-foreground">
                                                                            {guideFileTypeLabel(
                                                                                file,
                                                                            )}
                                                                        </span>
                                                                    </span>
                                                                </label>
                                                                <a
                                                                    href={
                                                                        file.url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                >
                                                                    <ExternalLink className="size-3.5" />
                                                                    確認
                                                                </a>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        ) : (
                                            <p className="p-3 text-sm text-muted-foreground">
                                                一致する案内図はありません。
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-3 rounded-lg border bg-neutral-50 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <Files className="size-4 shrink-0 text-muted-foreground" />
                                            <p className="text-sm font-medium">
                                                新しく追加するファイル
                                            </p>
                                        </div>
                                        <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm font-medium transition hover:bg-muted">
                                            <UploadCloud className="size-4" />
                                            ファイルを選択
                                            <input
                                                className="hidden"
                                                type="file"
                                                multiple
                                                accept={guideFileAccept}
                                                onChange={(event) => {
                                                    addGuideUploads(
                                                        Array.from(
                                                            event.currentTarget
                                                                .files ?? [],
                                                        ),
                                                    );
                                                    event.currentTarget.value =
                                                        '';
                                                }}
                                            />
                                        </label>
                                    </div>

                                    {data.guide_files.length > 0 ? (
                                        <div className="max-h-96 overflow-y-auto rounded-lg border bg-background dark:border-neutral-800">
                                            <div className="grid gap-2 p-2">
                                                {data.guide_files.map(
                                                    (file, index) => (
                                                        <div
                                                            key={`${file.name}-${file.lastModified}-${index}`}
                                                            className="grid gap-2 rounded-lg border p-3 dark:border-neutral-800"
                                                        >
                                                            <div className="flex min-w-0 items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-medium">
                                                                        {
                                                                            file.name
                                                                        }
                                                                    </p>
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {fileSizeLabel(
                                                                            file.size,
                                                                        )}
                                                                    </p>
                                                                    {formErrors[
                                                                        `guide_files.${index}`
                                                                    ] && (
                                                                        <p className="mt-1 text-xs text-destructive">
                                                                            {
                                                                                formErrors[
                                                                                    `guide_files.${index}`
                                                                                ]
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                                    onClick={() =>
                                                                        removeGuideUpload(
                                                                            index,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                    削除
                                                                </button>
                                                            </div>
                                                            <div className="grid gap-1.5">
                                                                <label
                                                                    htmlFor={`guide-file-name-${index}`}
                                                                    className="text-xs font-medium text-muted-foreground"
                                                                >
                                                                    表示名
                                                                </label>
                                                                <Input
                                                                    id={`guide-file-name-${index}`}
                                                                    required
                                                                    value={
                                                                        data
                                                                            .guide_file_names[
                                                                            index
                                                                        ] ?? ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateGuideUploadName(
                                                                            index,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="例: 搬入口案内図"
                                                                />
                                                                {formErrors[
                                                                    `guide_file_names.${index}`
                                                                ] && (
                                                                    <p className="text-xs text-destructive">
                                                                        {
                                                                            formErrors[
                                                                                `guide_file_names.${index}`
                                                                            ]
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            PDF / JPEG / PNG / WebP / HEIC /
                                            HEIF、50MBまで。複数ファイルをまとめて選ぶと、ファイル名から表示名を自動入力します。
                                        </p>
                                    )}
                                </div>
                            </div>
                            {data.guide_files.length > 0 && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {data.guide_files.length} ファイル選択中
                                </p>
                            )}
                            {errors.guide_files && (
                                <p className="mt-2 text-xs text-destructive">
                                    {errors.guide_files}
                                </p>
                            )}
                            {formErrors.guide_file_names && (
                                <p className="mt-2 text-xs text-destructive">
                                    {formErrors.guide_file_names}
                                </p>
                            )}
                        </div>
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
                            {processing ? '保存中...' : '保存'}
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
