import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    store as businessScheduleStore,
    update as businessScheduleUpdate,
} from '@/actions/App/Http/Controllers/BusinessScheduleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { BusinessSchedule, ConstructionUser } from '@/types';

type Props = {
    schedule: BusinessSchedule | null;
    users: ConstructionUser[];
    generalContractorOptions: string[];
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

export default function BusinessScheduleForm({
    schedule,
    users,
    generalContractorOptions,
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

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(
            schedule
                ? businessScheduleUpdate.url(schedule.id)
                : businessScheduleStore.url(),
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

    return (
        <>
            <Head title={schedule ? '業務予定編集' : '新規業務予定'} />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Business Schedule
                        </p>
                        <h1 className="text-2xl font-bold">
                            {schedule ? '業務予定編集' : '新規業務予定'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={scheduleIndex({ query: { type: 'all' } })}>
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
                        <textarea
                            className="min-h-32 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.content}
                            onChange={(event) =>
                                setData('content', event.target.value)
                            }
                            placeholder="例: 安全協議会、定時総会"
                        />
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

                    <div className="rounded-2xl border p-4 dark:border-neutral-800">
                        <h2 className="font-semibold">担当ユーザー</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            選択したユーザーの「自分の予定」に表示されます。
                        </p>
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
