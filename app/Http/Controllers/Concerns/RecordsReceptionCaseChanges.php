<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\User;

trait RecordsReceptionCaseChanges
{
    /**
     * Log the activity + audit entry for an already-persisted reception-case mutation.
     *
     * Must be called within the mutating transaction, right after the case is saved
     * and before any further save touches it (recordActivity re-saves the model, which
     * would otherwise overwrite getChanges() with just last_activity_at). Skips both
     * entries when nothing but timestamps changed.
     */
    protected function recordReceptionCaseUpdate(ReceptionCase $receptionCase, User $user, string $auditDescription): void
    {
        $changed = array_values(array_diff(array_keys($receptionCase->getChanges()), ['updated_at']));

        if ($changed === []) {
            return;
        }

        $receptionCase->recordActivity($user, ReceptionCaseActivity::TYPE_UPDATED, [
            'from_status' => $receptionCase->status->value,
            'to_status' => $receptionCase->status->value,
        ]);

        $this->auditSuccess('reception_cases.updated', $auditDescription, $receptionCase, [
            'changed' => $changed,
        ]);
    }
}
