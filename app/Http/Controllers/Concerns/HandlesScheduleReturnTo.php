<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared return_to redirect target + initial-form-value extraction for the
 * schedule-domain controllers, so the allowlist and query parsing live in one
 * place instead of being copy-pasted across every schedule form controller.
 */
trait HandlesScheduleReturnTo
{
    /**
     * Send the user back to the page the schedule was reached from, falling
     * back to the given URL when it was reached directly.
     */
    protected function redirectToReturnTo(Request $request, string $fallbackUrl): RedirectResponse
    {
        return redirect($this->returnTo($request) ?? $fallbackUrl);
    }

    protected function returnTo(Request $request): ?string
    {
        $returnTo = $request->query('return_to');

        if (! is_string($returnTo) || ! $this->isAllowedReturnTo($returnTo)) {
            return null;
        }

        return $returnTo;
    }

    protected function isAllowedReturnTo(string $returnTo): bool
    {
        return array_any(
            $this->allowedReturnToPrefixes(),
            fn (string $prefix): bool => str_starts_with($returnTo, $prefix),
        );
    }

    /**
     * @return list<string>
     */
    protected function allowedReturnToPrefixes(): array
    {
        return ['/construction-schedules', '/schedule-overview', '/admin/stocks'];
    }

    /**
     * @return array{initialScheduledOn: string|null, initialStartsAt: string|null, initialEndsAt: string|null, initialAssignedUserIds: list<int>}
     */
    protected function initialFormValues(Request $request): array
    {
        $scheduledOn = $request->query('scheduled_on');
        $startsAt = $request->query('starts_at');
        $endsAt = $request->query('ends_at');

        return [
            'initialScheduledOn' => is_string($scheduledOn) && $scheduledOn !== '' ? $scheduledOn : null,
            'initialStartsAt' => is_string($startsAt) && $startsAt !== '' ? $startsAt : null,
            'initialEndsAt' => is_string($endsAt) && $endsAt !== '' ? $endsAt : null,
            'initialAssignedUserIds' => collect($request->array('assigned_user_ids'))
                ->filter(fn (mixed $userId): bool => is_numeric($userId))
                ->map(fn (mixed $userId): int => (int) $userId)
                ->values()
                ->all(),
        ];
    }
}
