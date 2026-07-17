<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\User;
use App\ReceptionCaseStatus;
use Illuminate\Support\Facades\DB;

/**
 * Owns the reception-case status transitions: locking, the state change, the
 * activity entry, and the audit log. Controllers keep only their request,
 * flash message, and redirect.
 */
class ReceptionCaseWorkflow
{
    /**
     * Shown when a transition can no longer be applied because the case changed
     * underneath the request (lost a concurrent race).
     */
    public const string STALE_STATE_MESSAGE = 'この受付は他の操作で状態が変わりました。画面を更新して確認してください。';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Persist the submitted intake and move the draft to received.
     *
     * @param  array<string, mixed>  $attributes  Validated intake fields to persist on submit.
     * @return bool Whether the transition was applied (false if the case moved on first).
     */
    public function submit(ReceptionCase $case, User $actor, array $attributes): bool
    {
        return $this->transition(
            $case,
            $actor,
            ReceptionCaseStatus::Received,
            ReceptionCaseActivity::TYPE_SUBMITTED,
            'reception_cases.submitted',
            'A reception case was submitted.',
            attributes: $attributes,
        );
    }

    public function start(ReceptionCase $case, User $actor, ?string $memo): bool
    {
        return $this->transition(
            $case,
            $actor,
            ReceptionCaseStatus::InProgress,
            ReceptionCaseActivity::TYPE_STARTED,
            'reception_cases.started',
            'A reception case was started.',
            memo: $memo,
            recordToAssignee: true,
        );
    }

    public function handover(ReceptionCase $case, User $actor, string $memo): bool
    {
        return $this->transition(
            $case,
            $actor,
            ReceptionCaseStatus::Handover,
            ReceptionCaseActivity::TYPE_HANDOVER_REQUESTED,
            'reception_cases.handover_requested',
            'A reception case handover was requested.',
            memo: $memo,
            recordFromAssignee: true,
            recordToAssignee: true,
        );
    }

    public function complete(ReceptionCase $case, User $actor): bool
    {
        return $this->transition(
            $case,
            $actor,
            ReceptionCaseStatus::Completed,
            ReceptionCaseActivity::TYPE_COMPLETED,
            'reception_cases.completed',
            'A reception case was completed.',
            attributes: [
                'completed_at' => now(),
                'completed_by_user_id' => $actor->id,
            ],
            recordFromAssignee: true,
            recordToAssignee: true,
        );
    }

    /**
     * @return bool Whether the assignment was applied (false if the case is no longer active).
     */
    public function assign(ReceptionCase $case, User $actor, int $toAssignedUserId, ?string $memo): bool
    {
        $applied = DB::transaction(function () use ($case, $actor, $toAssignedUserId, $memo): bool {
            $locked = ReceptionCase::query()->lockForUpdate()->findOrFail($case->getKey());

            // Re-check under the lock: a case completed (or reverted to draft)
            // between the request's authorization and this transaction must not
            // be reassigned.
            if (! $locked->status->isActive()) {
                return false;
            }

            $fromAssignedUserId = $locked->assigned_user_id;

            $locked->forceFill(['assigned_user_id' => $toAssignedUserId])->save();

            $locked->recordActivity($actor, ReceptionCaseActivity::TYPE_ASSIGNED, [
                'memo' => $memo,
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'from_assigned_user_id' => $fromAssignedUserId,
                'to_assigned_user_id' => $toAssignedUserId,
            ]);

            DB::afterCommit(fn (): ?AuditLog => $this->auditLogger->success('reception_cases.assigned', 'A reception case was assigned.', $locked, [
                'from_assigned_user_id' => $fromAssignedUserId,
                'to_assigned_user_id' => $toAssignedUserId,
            ], $actor));

            return true;
        });

        $case->refresh();

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $attributes  Extra columns to persist alongside the status change.
     * @return bool Whether the case ended up in the target state — true when applied
     *              or already there (idempotent double-click), false when the case
     *              had moved to an incompatible state.
     */
    private function transition(
        ReceptionCase $case,
        User $actor,
        ReceptionCaseStatus $to,
        string $activityType,
        string $auditEvent,
        string $auditDescription,
        ?string $memo = null,
        array $attributes = [],
        bool $recordFromAssignee = false,
        bool $recordToAssignee = false,
    ): bool {
        $applied = DB::transaction(function () use ($case, $actor, $to, $activityType, $auditEvent, $auditDescription, $memo, $attributes, $recordFromAssignee, $recordToAssignee): bool {
            $locked = ReceptionCase::query()->lockForUpdate()->findOrFail($case->getKey());

            // Already in the target state (e.g. a duplicate double-click after the
            // first request committed): treat as success without writing again.
            if ($locked->status === $to) {
                return true;
            }

            // Lost a concurrent race: the case moved to a state that can't reach
            // the target. Report failure so the caller can flag it honestly.
            if (! $locked->status->canTransitionTo($to)) {
                return false;
            }

            $fromStatus = $locked->status;
            $assignedUserId = $locked->assigned_user_id;

            $locked->forceFill([...$attributes, 'status' => $to])->save();

            $activity = [
                'memo' => $memo,
                'from_status' => $fromStatus->value,
                'to_status' => $to->value,
            ];

            if ($recordFromAssignee) {
                $activity['from_assigned_user_id'] = $assignedUserId;
            }

            if ($recordToAssignee) {
                $activity['to_assigned_user_id'] = $assignedUserId;
            }

            $locked->recordActivity($actor, $activityType, $activity);

            DB::afterCommit(fn (): ?AuditLog => $this->auditLogger->success($auditEvent, $auditDescription, $locked, [], $actor));

            return true;
        });

        $case->refresh();

        return $applied;
    }
}
