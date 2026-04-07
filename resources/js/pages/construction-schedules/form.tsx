import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, FileText, UploadCloud } from 'lucide-react';
import {
    store as scheduleStore,
    update as scheduleUpdate,
    index as scheduleIndex,
} from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    ConstructionSchedule,
    ConstructionScheduleStatus,
    ConstructionSite,
    ConstructionUser,
    SiteGuideFile,
} from '@/types';

type Props = {
    schedule: ConstructionSchedule | null;
    users: ConstructionUser[];
    sites: ConstructionSite[];
    generalContractorOptions: string[];
};

type ScheduleForm = {
    _method: 'put' | '';
    construction_site_id: number | null;
    scheduled_on: string;
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
    site_guide_file_ids: number[];
    guide_files: File[];
};

const statuses: { value: ConstructionScheduleStatus; label: string }[] = [
    { value: 'scheduled', label: '予定' },
    { value: 'confirmed', label: '確定' },
    { value: 'postponed', label: '延期' },
    { value: 'canceled', label: '中止' },
];

const timeNotePresets = ['本日中'];

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

function guideFileTypeLabel(file: SiteGuideFile) {
    if (file.mime_type?.includes('pdf')) {
        return 'PDF';
    }

    if (file.mime_type?.startsWith('image/')) {
        return '画像';
    }

    return 'ファイル';
}

export default function ConstructionScheduleForm({
    schedule,
    users,
    sites,
    generalContractorOptions,
}: Props) {
    const { data, setData, post, processing, progress, errors } =
        useForm<ScheduleForm>({
            _method: schedule ? 'put' : '',
            construction_site_id: schedule?.site?.id ?? null,
            scheduled_on:
                schedule?.scheduled_on ?? new Date().toISOString().slice(0, 10),
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
            site_guide_file_ids: schedule?.selected_site_guide_file_ids ?? [],
            guide_files: [],
        });

    const selectedSite = sites.find(
        (site) => site.id === data.construction_site_id,
    );

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(schedule ? scheduleUpdate.url(schedule.id) : scheduleStore.url(), {
            forceFormData: true,
        });
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

    return (
        <>
            <Head title={schedule ? '予定編集' : '新規予定'} />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Construction Schedule
                        </p>
                        <h1 className="text-2xl font-bold">
                            {schedule ? '予定編集' : '新規予定'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={scheduleIndex()}>
                            <ArrowLeft className="size-4" />
                            予定表へ戻る
                        </Link>
                    </Button>
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
                        <Field label="集合場所" error={errors.meeting_place}>
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
                        <Field label="担当" error={errors.person_in_charge}>
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
                            label="ナビ（Google Map用住所）"
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
                        <Field
                            label="現場ライブラリ"
                            error={errors.construction_site_id}
                        >
                            <select
                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                value={data.construction_site_id ?? ''}
                                onChange={(event) => {
                                    const siteId = event.target.value
                                        ? Number(event.target.value)
                                        : null;
                                    setData((values) => ({
                                        ...values,
                                        construction_site_id: siteId,
                                        site_guide_file_ids: [],
                                    }));
                                }}
                            >
                                <option value="">選択しない</option>
                                {sites.map((site) => (
                                    <option key={site.id} value={site.id}>
                                        {site.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </section>

                    <Field label="内容" error={errors.content}>
                        <textarea
                            className="min-h-32 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.content}
                            onChange={(event) =>
                                setData('content', event.target.value)
                            }
                        />
                    </Field>

                    <section className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <h2 className="font-semibold">担当ユーザー</h2>
                            <div className="mt-3 grid gap-2">
                                {users.map((user) => (
                                    <label
                                        key={user.id}
                                        className="flex items-center gap-2 text-sm"
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
                        </div>

                        <div className="rounded-2xl border p-4 dark:border-neutral-800">
                            <h2 className="font-semibold">現場案内図</h2>
                            <div className="mt-3 grid gap-2">
                                {selectedSite?.guide_files.length ? (
                                    selectedSite.guide_files.map((file) => {
                                        const isSelected =
                                            data.site_guide_file_ids.includes(
                                                file.id,
                                            );
                                        const inputId = `site-guide-file-${file.id}`;

                                        return (
                                            <div
                                                key={file.id}
                                                className={cn(
                                                    'flex items-start gap-3 rounded-xl border p-3 transition',
                                                    isSelected
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                                                )}
                                            >
                                                <label
                                                    htmlFor={inputId}
                                                    className="flex min-w-0 flex-1 cursor-pointer items-start gap-3 text-sm"
                                                >
                                                    <input
                                                        id={inputId}
                                                        type="checkbox"
                                                        className="mt-1"
                                                        checked={isSelected}
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
                                                                {file.name}
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
                                                    href={file.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex shrink-0 items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                >
                                                    <ExternalLink className="size-3.5" />
                                                    確認
                                                </a>
                                            </div>
                                        );
                                    })
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        現場ライブラリを選択すると既存の案内図を選べます。
                                    </p>
                                )}
                            </div>
                            <label className="mt-4 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed p-5 text-center text-sm text-muted-foreground dark:border-neutral-800">
                                <UploadCloud className="size-6" />
                                PDF / 画像を追加アップロード
                                <input
                                    className="hidden"
                                    type="file"
                                    multiple
                                    accept="application/pdf,image/jpeg,image/png,image/webp"
                                    onChange={(event) =>
                                        setData(
                                            'guide_files',
                                            Array.from(
                                                event.currentTarget.files ?? [],
                                            ),
                                        )
                                    }
                                />
                            </label>
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
                        </div>
                    </section>

                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            className="w-full"
                        />
                    )}

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

ConstructionScheduleForm.layout = {
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
