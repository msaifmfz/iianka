import { Head, router, useForm } from '@inertiajs/react';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import {
    store as internalNoticeStore,
    update as internalNoticeUpdate,
} from '@/actions/App/Http/Controllers/InternalNoticeController';
import { FloatingBackButton } from '@/components/floating-back-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { ConstructionUser, InternalNotice } from '@/types';

type Props = {
    notice: InternalNotice | null;
    users: ConstructionUser[];
};

type InternalNoticeForm = {
    _method: 'put' | '';
    scheduled_on: string;
    starts_at: string;
    ends_at: string;
    time_note: string;
    title: string;
    location: string;
    content: string;
    memo: string;
    assigned_user_ids: number[];
};

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

export default function InternalNoticeForm({ notice, users }: Props) {
    const { data, setData, post, processing, errors } =
        useForm<InternalNoticeForm>({
            _method: notice ? 'put' : '',
            scheduled_on:
                notice?.scheduled_on ?? new Date().toISOString().slice(0, 10),
            starts_at: notice?.starts_at?.slice(0, 5) ?? '',
            ends_at: notice?.ends_at?.slice(0, 5) ?? '',
            time_note: notice?.time_note ?? '',
            title: notice?.title ?? '',
            location: notice?.location ?? '',
            content: notice?.content ?? '',
            memo: notice?.memo ?? '',
            assigned_user_ids:
                notice?.assigned_users.map((user) => user.id) ?? [],
        });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(
            notice
                ? internalNoticeUpdate.url(notice.id)
                : internalNoticeStore.url(),
        );
    }

    function handleGoBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(scheduleIndex({ query: { type: 'all' } }));
    }

    return (
        <>
            <Head title={notice ? '業務連絡編集' : '新規業務連絡'} />
            <FloatingBackButton
                onClick={handleGoBack}
                className="bottom-5 md:bottom-6 xl:bottom-8"
            />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 pb-24 sm:p-4 sm:pb-24 md:p-6 md:pb-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Internal Notice
                        </p>
                        <h1 className="text-2xl font-bold">
                            {notice ? '業務連絡編集' : '新規業務連絡'}
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-3xl border bg-white p-4 shadow-sm sm:p-5 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <section className="grid gap-4 md:grid-cols-4">
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
                                    setData('starts_at', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="終了時間" error={errors.ends_at}>
                            <Input
                                type="time"
                                value={data.ends_at}
                                onChange={(event) =>
                                    setData('ends_at', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="時間メモ" error={errors.time_note}>
                            <Input
                                value={data.time_note}
                                onChange={(event) =>
                                    setData('time_note', event.target.value)
                                }
                                placeholder="例: 本日中、午前中"
                            />
                        </Field>
                    </section>

                    <section className="grid gap-4 md:grid-cols-2">
                        <Field label="件名" error={errors.title}>
                            <Input
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                placeholder="例: 健康診断"
                            />
                        </Field>
                        <Field label="場所" error={errors.location}>
                            <Input
                                value={data.location}
                                onChange={(event) =>
                                    setData('location', event.target.value)
                                }
                                placeholder="例: 本社 / 会議室"
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
                            placeholder="例: 健康診断を受診してください。"
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
                        <h2 className="font-semibold">対象ユーザー</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            選択したユーザーの「自分の予定」に表示されます。
                        </p>
                        <div className="mt-3 grid gap-2 md:grid-cols-2">
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

InternalNoticeForm.layout = {
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
