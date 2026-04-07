import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    index as cleaningDutyRuleIndex,
    store as cleaningDutyRuleStore,
    update as cleaningDutyRuleUpdate,
} from '@/actions/App/Http/Controllers/CleaningDutyRuleController';
import { index as scheduleIndex } from '@/actions/App/Http/Controllers/ConstructionScheduleController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { CleaningDutyRule, ConstructionUser } from '@/types';

type WeekdayOption = {
    value: number;
    label: string;
};

type Props = {
    rule: CleaningDutyRule | null;
    users: ConstructionUser[];
    weekdayOptions: WeekdayOption[];
};

type CleaningDutyRuleForm = {
    _method: 'put' | '';
    weekday: number;
    label: string;
    location: string;
    notes: string;
    is_active: boolean;
    sort_order: number;
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

export default function CleaningDutyRuleForm({
    rule,
    users,
    weekdayOptions,
}: Props) {
    const { data, setData, post, processing, errors } =
        useForm<CleaningDutyRuleForm>({
            _method: rule ? 'put' : '',
            weekday: rule?.weekday ?? 1,
            label: rule?.label ?? '掃除当番',
            location: rule?.location ?? '',
            notes: rule?.notes ?? '',
            is_active: rule?.is_active ?? true,
            sort_order: rule?.sort_order ?? 0,
            assigned_user_ids:
                rule?.assigned_users.map((user) => user.id) ?? [],
        });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(
            rule
                ? cleaningDutyRuleUpdate.url(rule.id)
                : cleaningDutyRuleStore.url(),
        );
    }

    return (
        <>
            <Head title={rule ? '掃除当番設定編集' : '新規掃除当番設定'} />
            <div className="mx-auto max-w-5xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Cleaning Duty Rule
                        </p>
                        <h1 className="text-2xl font-bold">
                            {rule ? '掃除当番設定編集' : '新規掃除当番設定'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={cleaningDutyRuleIndex()}>
                            <ArrowLeft className="size-4" />
                            設定一覧へ戻る
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-3xl border bg-white p-4 shadow-sm sm:p-5 dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <section className="grid gap-4 md:grid-cols-2">
                        <Field label="曜日" error={errors.weekday}>
                            <select
                                className="rounded-md border bg-transparent px-3 py-2 text-sm"
                                value={data.weekday}
                                onChange={(event) =>
                                    setData(
                                        'weekday',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {weekdayOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="表示名" error={errors.label}>
                            <Input
                                value={data.label}
                                onChange={(event) =>
                                    setData('label', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="場所" error={errors.location}>
                            <Input
                                value={data.location}
                                onChange={(event) =>
                                    setData('location', event.target.value)
                                }
                                placeholder="例: 事務所"
                            />
                        </Field>
                        <Field label="表示順" error={errors.sort_order}>
                            <Input
                                type="number"
                                min="0"
                                value={data.sort_order}
                                onChange={(event) =>
                                    setData(
                                        'sort_order',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                    </section>

                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) =>
                                setData('is_active', event.target.checked)
                            }
                        />
                        有効にする
                    </label>

                    <Field label="メモ" error={errors.notes}>
                        <textarea
                            className="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                        />
                    </Field>

                    <div className="rounded-2xl border p-4 dark:border-neutral-800">
                        <h2 className="font-semibold">担当ユーザー</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            選択したユーザーの「自分の予定」に自動表示されます。
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

                    <div className="flex justify-between gap-3">
                        <Button asChild variant="outline">
                            <Link
                                href={scheduleIndex({
                                    query: { type: 'cleaning_duty' },
                                })}
                            >
                                予定表で確認
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? '保存中...' : '保存'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CleaningDutyRuleForm.layout = {
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
