<?php

declare(strict_types=1);

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\ReceptionCase;

dataset('reception case status transitions', function (): iterable {
    $allowedTargets = [
        ReceptionCaseStatus::Draft->value => [ReceptionCaseStatus::Received],
        ReceptionCaseStatus::Received->value => [ReceptionCaseStatus::InProgress, ReceptionCaseStatus::Completed],
        ReceptionCaseStatus::InProgress->value => [ReceptionCaseStatus::Handover, ReceptionCaseStatus::Completed],
        ReceptionCaseStatus::Handover->value => [ReceptionCaseStatus::InProgress, ReceptionCaseStatus::Completed],
        ReceptionCaseStatus::Completed->value => [],
    ];

    foreach (ReceptionCaseStatus::cases() as $from) {
        foreach (ReceptionCaseStatus::cases() as $to) {
            yield "{$from->value} to {$to->value}" => [
                $from,
                $to,
                in_array($to, $allowedTargets[$from->value], true),
            ];
        }
    }
});

test('reception statuses enforce the complete transition matrix', function (
    ReceptionCaseStatus $from,
    ReceptionCaseStatus $to,
    bool $isAllowed,
): void {
    expect($from->canTransitionTo($to))->toBe($isAllowed);
})->with('reception case status transitions');

test('a reception case needs an assignee before work can start', function (): void {
    $case = new ReceptionCase;
    $case->forceFill([
        'status' => ReceptionCaseStatus::Received,
        'assigned_user_id' => null,
    ]);

    expect($case->canTransitionTo(ReceptionCaseStatus::InProgress))->toBeFalse();

    $case->forceFill(['assigned_user_id' => 42]);

    expect($case->canTransitionTo(ReceptionCaseStatus::InProgress))->toBeTrue();
});
