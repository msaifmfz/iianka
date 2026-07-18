<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Application\Reception\ReceptionCaseWorkflow;
use App\Models\ReceptionCase;
use Illuminate\Http\RedirectResponse;

/**
 * Shared reception-case toast + workflow-response helpers, so the success/stale
 * toast contract lives in one place instead of being copy-pasted across every
 * workflow controller.
 */
trait FlashesReceptionCaseToasts
{
    /**
     * Flash a success toast carrying the reception-case resource descriptor the
     * frontend uses to highlight the affected row.
     */
    protected function flashCaseToast(ReceptionCase $case, string $action, string $message, string $type = 'success'): void
    {
        $this->flashToast($message, type: $type, resource: [
            'type' => 'reception_case',
            'id' => $case->id,
            'action' => $action,
            'label' => $case->case_number,
        ]);
    }

    /**
     * Turn a workflow transition result into a redirect: warn + go back when the
     * case lost a concurrent race, otherwise flash success and follow $onApplied
     * (defaulting to back()).
     */
    protected function respondToTransition(
        bool $applied,
        ReceptionCase $case,
        string $action,
        string $message,
        ?RedirectResponse $onApplied = null,
    ): RedirectResponse {
        if (! $applied) {
            $this->flashToast(ReceptionCaseWorkflow::STALE_STATE_MESSAGE, type: 'warning');

            return back();
        }

        $this->flashCaseToast($case, $action, $message);

        return $onApplied ?? back();
    }
}
