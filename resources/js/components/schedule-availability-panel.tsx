import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import type { ScheduleAvailability } from '@/types';

type Props = {
    scheduledOn: string;
    assignedUserCount: number;
    busySchedules: ScheduleAvailability[];
    hasTimeConflict: boolean;
    hasTimeRange: boolean;
    suggestedTimeSlots: readonly (readonly [string, string])[];
    onSelectTimeSlot: (startsAt: string, endsAt: string) => void;
};

/**
 * Shows the selected staff's existing schedules for the chosen day plus
 * conflict status and quick time-slot suggestions. Shared by the schedule
 * forms.
 */
export function ScheduleAvailabilityPanel({
    scheduledOn,
    assignedUserCount,
    busySchedules,
    hasTimeConflict,
    hasTimeRange,
    suggestedTimeSlots,
    onSelectTimeSlot,
}: Props) {
    return (
        <div className="rounded-2xl border bg-neutral-50 p-4 md:col-span-3 dark:border-neutral-800 dark:bg-neutral-900/50">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">時間の空き状況</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {assignedUserCount === 0
                            ? 'スタッフを選択すると、その日の重複予定を確認できます。'
                            : `${scheduledOn} の選択スタッフの予定を表示しています。`}
                    </p>
                </div>
                {assignedUserCount > 0 &&
                    (hasTimeConflict ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-100">
                            <AlertTriangle className="size-3.5" />
                            重複あり
                        </span>
                    ) : (
                        hasTimeRange && (
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
                                    {busySchedule.user_names.join('、')}
                                </span>
                            </div>
                            <p className="mt-1 text-muted-foreground">
                                {busySchedule.title}
                            </p>
                        </div>
                    ))}
                </div>
            ) : (
                assignedUserCount > 0 && (
                    <p className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                        この日の選択スタッフには時間指定の予定がありません。
                    </p>
                )
            )}

            {assignedUserCount > 0 && (
                <div className="mt-4">
                    <p className="text-xs font-semibold text-muted-foreground">
                        空き時間クイック選択
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {suggestedTimeSlots.length > 0 ? (
                            suggestedTimeSlots.map(([startsAt, endsAt]) => (
                                <button
                                    key={`${startsAt}-${endsAt}`}
                                    type="button"
                                    className="rounded-full bg-white px-3 py-1.5 text-xs font-semibold ring-1 ring-neutral-200 transition hover:bg-amber-50 hover:text-amber-900 dark:bg-neutral-950 dark:ring-neutral-800 dark:hover:bg-amber-950/40 dark:hover:text-amber-100"
                                    onClick={() =>
                                        onSelectTimeSlot(startsAt, endsAt)
                                    }
                                >
                                    {startsAt} - {endsAt}
                                </button>
                            ))
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                推奨枠はすべて既存予定と重なっています。予定一覧を見ながら手入力してください。
                            </span>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
