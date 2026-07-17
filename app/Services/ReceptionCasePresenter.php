<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\ReceptionCaseAttachment;
use App\Models\ReceptionDocumentType;
use App\Models\User;

class ReceptionCasePresenter
{
    /**
     * @param  iterable<int, ReceptionCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    public function cases(iterable $cases, User $viewer): array
    {
        return collect($cases)
            ->map(fn (ReceptionCase $case): array => $this->case($case, $viewer))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function case(ReceptionCase $case, User $viewer): array
    {
        return [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'status' => $case->status->value,
            'status_label' => $case->status->label(),
            'priority' => $case->priority->value,
            'company_name' => $case->company_name,
            'site_name' => $case->site_name,
            'reception_document_type_id' => $case->reception_document_type_id,
            'document_type' => $case->documentType ? $this->documentType($case->documentType) : null,
            'reception_content' => $case->reception_content,
            'work_memo' => $case->work_memo,
            'due_on' => $case->due_on?->toDateString(),
            'scheduled_on' => $case->scheduled_on?->toDateString(),
            'receptor' => $case->receptor ? $this->user($case->receptor) : null,
            'assigned_user' => $case->assignedUser ? $this->user($case->assignedUser) : null,
            'assigned_user_handover_chain' => $this->assignedUserHandoverChain($case),
            'completed_at' => $case->completed_at?->toISOString(),
            'completed_by' => $case->completedBy ? $this->user($case->completedBy) : null,
            'last_activity_at' => $case->last_activity_at?->toISOString(),
            'last_seen_at' => $this->lastSeenAt($case),
            'created_at' => $case->created_at?->toISOString(),
            'updated_at' => $case->updated_at?->toISOString(),
            'is_unseen' => ! $case->hasBeenSeenBy($viewer),
            'attachments' => $case->relationLoaded('attachments')
                ? $case->attachments
                    ->sortByDesc('id')
                    ->map(fn (ReceptionCaseAttachment $attachment): array => $this->attachment($attachment))
                    ->values()
                    ->all()
                : [],
            'activities' => $case->relationLoaded('activities')
                ? $case->activities
                    ->sortBy('id')
                    ->reject(fn (ReceptionCaseActivity $activity): bool => $activity->type === ReceptionCaseActivity::TYPE_CREATED_DRAFT)
                    ->map(fn (ReceptionCaseActivity $activity): array => $this->activity($activity))
                    ->values()
                    ->all()
                : [],
            'can' => [
                'view' => $viewer->can('view', $case),
                'update' => $viewer->can('update', $case),
                'attach_files' => $viewer->can('attachFiles', $case),
                'update_priority' => $viewer->can('updatePriority', $case),
                'delete_draft' => $viewer->can('deleteDraft', $case),
                'submit' => $viewer->can('submit', $case),
                'assign' => $viewer->can('assign', $case),
                'start' => $viewer->can('start', $case),
                'update_work_memo' => $viewer->can('updateWorkMemo', $case),
                'handover' => $viewer->can('handover', $case),
                'complete' => $viewer->can('complete', $case),
            ],
        ];
    }

    /**
     * The upload limits and accepted file types, sourced from the model so the
     * frontend never hardcodes (and drifts from) the server-side rules.
     *
     * @return array<string, mixed>
     */
    public function attachmentConstraints(): array
    {
        return [
            'max_attachments' => ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE,
            'max_recordings' => ReceptionCaseAttachment::MAX_RECORDINGS_PER_CASE,
            'max_recording_seconds' => ReceptionCaseAttachment::MAX_RECORDING_SECONDS,
            'max_file_kilobytes' => ReceptionCaseAttachment::MAX_FILE_KILOBYTES,
            'accept' => ReceptionCaseAttachment::acceptAttribute(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attachment(ReceptionCaseAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->kind->value,
            'kind_label' => $attachment->kind->label(),
            'source' => $attachment->source->value,
            'source_label' => $attachment->source->label(),
            'name' => $attachment->name,
            'url' => $attachment->url(),
            'download_url' => $attachment->downloadUrl(),
            'preview_mode' => $attachment->previewMode()->value,
            'mime_type' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'size' => $attachment->size,
            'duration_seconds' => $attachment->duration_seconds,
            'uploaded_by' => $attachment->uploader ? $this->user($attachment->uploader) : null,
            'created_at' => $attachment->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activity(ReceptionCaseActivity $activity): array
    {
        $statusChanged = $activity->from_status !== $activity->to_status;
        $assignedUserChanged = $activity->type === ReceptionCaseActivity::TYPE_ASSIGNED
            && $activity->from_assigned_user_id !== $activity->to_assigned_user_id;

        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'memo' => $activity->memo,
            'from_status' => $statusChanged ? $activity->from_status : null,
            'to_status' => $statusChanged ? $activity->to_status : null,
            'from_assigned_user' => $assignedUserChanged && $activity->fromAssignedUser ? $this->user($activity->fromAssignedUser) : null,
            'to_assigned_user' => $assignedUserChanged && $activity->toAssignedUser ? $this->user($activity->toAssignedUser) : null,
            'user' => $activity->user ? $this->user($activity->user) : null,
            'created_at' => $activity->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documentType(ReceptionDocumentType $documentType): array
    {
        return [
            'id' => $documentType->id,
            'name' => $documentType->name,
            'sort_order' => $documentType->sort_order,
            'is_active' => $documentType->is_active,
            'reception_cases_count' => $documentType->reception_cases_count ?? null,
            'created_at' => $documentType->created_at?->toISOString(),
            'updated_at' => $documentType->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  iterable<int, ReceptionDocumentType>  $documentTypes
     * @return array<int, array<string, mixed>>
     */
    public function documentTypes(iterable $documentTypes): array
    {
        return collect($documentTypes)
            ->map(fn (ReceptionDocumentType $documentType): array => $this->documentType($documentType))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'login_id' => $user->login_id,
            'is_hidden_from_workers' => $user->is_hidden_from_workers,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assignedUserHandoverChain(ReceptionCase $case): array
    {
        if (! $case->relationLoaded('activities')) {
            return [];
        }

        $hasHandover = $case->activities
            ->contains(fn (ReceptionCaseActivity $activity): bool => $activity->type === ReceptionCaseActivity::TYPE_HANDOVER_REQUESTED);

        if (! $hasHandover) {
            return [];
        }

        $chain = [];

        foreach ($case->activities->sortBy('id') as $activity) {
            if ($activity->type !== ReceptionCaseActivity::TYPE_ASSIGNED) {
                continue;
            }
            if ($activity->from_assigned_user_id === null) {
                continue;
            }
            if ($activity->to_assigned_user_id === null) {
                continue;
            }
            if ($activity->from_assigned_user_id === $activity->to_assigned_user_id) {
                continue;
            }
            $chain = $this->appendHandoverUser(
                $chain,
                $activity->relationLoaded('fromAssignedUser') ? $activity->fromAssignedUser : null,
            );
            $chain = $this->appendHandoverUser(
                $chain,
                $activity->relationLoaded('toAssignedUser') ? $activity->toAssignedUser : null,
            );
        }

        return count($chain) > 1 ? $chain : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $chain
     * @return array<int, array<string, mixed>>
     */
    private function appendHandoverUser(array $chain, ?User $user): array
    {
        if (! $user instanceof User) {
            return $chain;
        }

        $lastUser = array_last($chain) ?? null;

        if (($lastUser['id'] ?? null) === $user->id) {
            return $chain;
        }

        $chain[] = $this->user($user);

        return $chain;
    }

    private function lastSeenAt(ReceptionCase $case): ?string
    {
        if (! $case->relationLoaded('seenStates') || $case->assigned_user_id === null) {
            return null;
        }

        return $case->seenStates
            ->firstWhere('user_id', $case->assigned_user_id)
            ?->seen_at
            ?->toISOString();
    }
}
