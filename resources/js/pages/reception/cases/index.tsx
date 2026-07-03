import { Head, router } from '@inertiajs/react';
import { Play, UserRound } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    assign as receptionAssign,
    start as receptionStart,
} from '@/actions/App/Http/Controllers/ReceptionCaseAssignmentController';
import { ReceptionCaseList } from '@/components/reception-case-list';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import type { ReceptionCase, ReceptionUser } from '@/types';

type Props = {
    reviewCases: ReceptionCase[];
    inProgressCases: ReceptionCase[];
    assigneeOptions: ReceptionUser[];
};

function ReviewActions({
    receptionCase,
    assigneeOptions,
}: {
    receptionCase: ReceptionCase;
    assigneeOptions: ReceptionUser[];
}) {
    const assignedUserId = receptionCase.assigned_user?.id.toString() ?? '';
    const [selectedAssignedUserId, setSelectedAssignedUserId] =
        useState(assignedUserId);
    const [isAssigning, setIsAssigning] = useState(false);
    const [isStarting, setIsStarting] = useState(false);
    const hasPendingAssignment = selectedAssignedUserId !== assignedUserId;

    if (!receptionCase.can.assign && !receptionCase.can.start) {
        return null;
    }

    function assign(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!selectedAssignedUserId || isAssigning) {
            return;
        }

        setIsAssigning(true);

        router.patch(
            receptionAssign.url(receptionCase.id),
            {
                assigned_user_id: selectedAssignedUserId,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsAssigning(false),
            },
        );
    }

    function start() {
        setIsStarting(true);

        router.patch(
            receptionStart.url(receptionCase.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsStarting(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            {receptionCase.can.assign && (
                <form
                    onSubmit={assign}
                    className="flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    <label className="flex items-center gap-2">
                        <UserRound className="size-4 text-muted-foreground" />
                        <NativeSelect
                            aria-label="担当者"
                            className="h-8 w-auto px-2"
                            value={selectedAssignedUserId}
                            onChange={(event) =>
                                setSelectedAssignedUserId(event.target.value)
                            }
                        >
                            <option value="">担当者</option>
                            {assigneeOptions.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </label>
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={
                            !selectedAssignedUserId ||
                            !hasPendingAssignment ||
                            isAssigning
                        }
                    >
                        <UserRound className="size-4" />
                        担当者を設定する
                    </Button>
                </form>
            )}
            {receptionCase.can.start && (
                <Button
                    type="button"
                    size="sm"
                    disabled={
                        !assignedUserId || hasPendingAssignment || isStarting
                    }
                    onClick={start}
                >
                    <Play className="size-4" />
                    対応開始
                </Button>
            )}
        </div>
    );
}

export default function ReceptionCasesIndex({
    reviewCases,
    inProgressCases,
    assigneeOptions,
}: Props) {
    return (
        <>
            <Head title="やること一覧" />
            <div className="mx-auto w-full max-w-6xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Reception Tasks
                    </p>
                    <h1 className="text-2xl font-bold">やること一覧</h1>
                </div>

                {reviewCases.length > 0 && (
                    <ReceptionCaseList
                        title="レビューが必要な案件"
                        cases={reviewCases}
                        empty="管理レビューの受付案件はありません。"
                        showScheduledOn={false}
                        showLastActivityAt={false}
                    >
                        {(receptionCase) => (
                            <ReviewActions
                                key={`${receptionCase.id}:${receptionCase.assigned_user?.id ?? 'unassigned'}`}
                                receptionCase={receptionCase}
                                assigneeOptions={assigneeOptions}
                            />
                        )}
                    </ReceptionCaseList>
                )}

                <ReceptionCaseList
                    title="対応中"
                    cases={inProgressCases}
                    empty="対応中の受付案件はありません。"
                    showScheduledOn={false}
                    showLastActivityAt={false}
                />
            </div>
        </>
    );
}
