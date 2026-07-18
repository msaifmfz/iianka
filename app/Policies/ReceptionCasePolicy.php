<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\ReceptionCase;
use App\Models\User;

class ReceptionCasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReceptionCase $receptionCase): bool
    {
        if ($receptionCase->status !== ReceptionCaseStatus::Draft) {
            return true;
        }

        return $receptionCase->receptor_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ReceptionCase $receptionCase): bool
    {
        if ($receptionCase->status === ReceptionCaseStatus::Completed) {
            return false;
        }

        // Drafts are private to their creator; managers only gain edit rights
        // once the case has been submitted.
        if ($receptionCase->status === ReceptionCaseStatus::Draft) {
            return $receptionCase->receptor_user_id === $user->id;
        }

        return $receptionCase->receptor_user_id === $user->id
            || $user->canManageContent();
    }

    /**
     * Managing attachments is broader than editing the intake fields: the
     * assigned worker can add/remove documents and recordings on an active case
     * even though they cannot change the case's fields.
     */
    public function attachFiles(User $user, ReceptionCase $receptionCase): bool
    {
        if ($this->update($user, $receptionCase)) {
            return true;
        }

        return $receptionCase->assigned_user_id === $user->id
            && $receptionCase->status->isActive();
    }

    public function updatePriority(User $user, ReceptionCase $receptionCase): bool
    {
        if ($receptionCase->status === ReceptionCaseStatus::Completed) {
            return false;
        }

        if ($receptionCase->status === ReceptionCaseStatus::Draft) {
            return false;
        }

        return $user->canManageContent();
    }

    public function deleteDraft(User $user, ReceptionCase $receptionCase): bool
    {
        return $receptionCase->status === ReceptionCaseStatus::Draft
            && $receptionCase->receptor_user_id === $user->id;
    }

    public function submit(User $user, ReceptionCase $receptionCase): bool
    {
        return $receptionCase->status === ReceptionCaseStatus::Draft
            && $receptionCase->receptor_user_id === $user->id;
    }

    public function assign(User $user, ReceptionCase $receptionCase): bool
    {
        return $user->canManageContent()
            && $receptionCase->status->isActive();
    }

    public function start(User $user, ReceptionCase $receptionCase): bool
    {
        return $user->canManageContent()
            && in_array($receptionCase->status, [
                ReceptionCaseStatus::Received,
                ReceptionCaseStatus::Handover,
            ], true);
    }

    public function handover(User $user, ReceptionCase $receptionCase): bool
    {
        return $receptionCase->status === ReceptionCaseStatus::InProgress
            && ($receptionCase->assigned_user_id === $user->id || $user->canManageContent());
    }

    public function updateWorkMemo(User $user, ReceptionCase $receptionCase): bool
    {
        if (! $receptionCase->status->isActive()) {
            return false;
        }

        return $receptionCase->assigned_user_id === $user->id
            || $user->canManageContent();
    }

    public function complete(User $user, ReceptionCase $receptionCase): bool
    {
        if ($receptionCase->status === ReceptionCaseStatus::InProgress) {
            return $receptionCase->assigned_user_id === $user->id
                || $user->canManageContent();
        }

        return $user->canManageContent()
            && in_array($receptionCase->status, [
                ReceptionCaseStatus::Received,
                ReceptionCaseStatus::Handover,
            ], true);
    }

    public function viewArchive(User $user): bool
    {
        return true;
    }
}
