import { AlertTriangle } from 'lucide-react';
import { cn, toggleNumber } from '@/lib/utils';
import type { AttendanceLeaveRecord, ConstructionUser } from '@/types';

type Props = {
    users: ConstructionUser[];
    assignedUserIds: number[];
    leaveRecords: AttendanceLeaveRecord[];
    error?: string;
    onChangeAssignedUserIds: (userIds: number[]) => void;
};

/** Staff checkbox grid with a leave-day warning, shared by the schedule forms. */
export function ScheduleStaffPicker({
    users,
    assignedUserIds,
    leaveRecords,
    error,
    onChangeAssignedUserIds,
}: Props) {
    return (
        <div className="rounded-2xl border p-4 md:col-span-3 dark:border-neutral-800">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold">スタッフ</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        選択したスタッフの予定をもとに空き時間を表示します。
                    </p>
                </div>
                {assignedUserIds.length > 0 && (
                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        {assignedUserIds.length}名選択中
                    </span>
                )}
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                {users.map((user) => (
                    <label
                        key={user.id}
                        className={cn(
                            'flex items-center gap-2 rounded-xl border p-3 text-sm transition',
                            assignedUserIds.includes(user.id)
                                ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
                                : 'border-neutral-200 hover:bg-muted/50 dark:border-neutral-800',
                        )}
                    >
                        <input
                            type="checkbox"
                            checked={assignedUserIds.includes(user.id)}
                            onChange={() =>
                                onChangeAssignedUserIds(
                                    toggleNumber(assignedUserIds, user.id),
                                )
                            }
                        />
                        {user.name}
                    </label>
                ))}
            </div>
            {error && <p className="mt-2 text-xs text-destructive">{error}</p>}
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
                                {record.note ? `: ${record.note}` : ''}
                            </span>
                        ))}
                    </div>
                    <p className="mt-2 text-xs">
                        登録は続行できます。必要に応じて担当者を調整してください。
                    </p>
                </div>
            )}
        </div>
    );
}
