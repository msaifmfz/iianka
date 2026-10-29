<?php

declare(strict_types=1);

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\ReceptionCase;
use App\Models\User;
use App\Policies\ReceptionCasePolicy;
use App\UserRole;
use Illuminate\Support\Facades\Gate;

mutates(ReceptionCasePolicy::class);

test('only the creator can delete or submit a draft regardless of role', function (
    string $ability,
    UserRole $role,
    ReceptionCaseStatus $status,
    bool $isCreator,
    bool $allowed,
): void {
    $user = User::factory()->make(['id' => 1, 'role' => $role]);
    $case = ReceptionCase::factory()->make([
        'status' => $status,
        'receptor_user_id' => $isCreator ? 1 : 2,
        'reception_document_type_id' => 1,
    ]);

    expect(Gate::forUser($user)->allows($ability, $case))->toBe($allowed);
})->with(['delete draft' => ['deleteDraft'], 'submit draft' => ['submit']])
    ->with(UserRole::cases())
    ->with([
        'own draft' => [ReceptionCaseStatus::Draft, true, true],
        'another users draft' => [ReceptionCaseStatus::Draft, false, false],
        'own received case' => [ReceptionCaseStatus::Received, true, false],
        'another users received case' => [ReceptionCaseStatus::Received, false, false],
        'own in progress case' => [ReceptionCaseStatus::InProgress, true, false],
        'another users in progress case' => [ReceptionCaseStatus::InProgress, false, false],
        'own handover case' => [ReceptionCaseStatus::Handover, true, false],
        'another users handover case' => [ReceptionCaseStatus::Handover, false, false],
        'own completed case' => [ReceptionCaseStatus::Completed, true, false],
        'another users completed case' => [ReceptionCaseStatus::Completed, false, false],
    ]);

test('unassigned users have only the workflow permissions granted to their role and case status', function (
    string $ability,
    UserRole $role,
    ReceptionCaseStatus $status,
    array $allowedAbilities,
): void {
    $user = User::factory()->make(['id' => 1, 'role' => $role]);
    $case = ReceptionCase::factory()->make([
        'status' => $status,
        'receptor_user_id' => 2,
        'assigned_user_id' => 3,
        'reception_document_type_id' => 1,
    ]);

    expect(Gate::forUser($user)->allows($ability, $case))
        ->toBe(in_array($ability, $allowedAbilities, true));
})->with(['start', 'handover', 'updateWorkMemo', 'complete'])
    ->with([
        'viewer draft' => [UserRole::Viewer, ReceptionCaseStatus::Draft, []],
        'viewer received' => [UserRole::Viewer, ReceptionCaseStatus::Received, []],
        'viewer in progress' => [UserRole::Viewer, ReceptionCaseStatus::InProgress, []],
        'viewer handover' => [UserRole::Viewer, ReceptionCaseStatus::Handover, []],
        'viewer completed' => [UserRole::Viewer, ReceptionCaseStatus::Completed, []],
        'editor draft' => [UserRole::Editor, ReceptionCaseStatus::Draft, []],
        'editor received' => [UserRole::Editor, ReceptionCaseStatus::Received, ['start', 'updateWorkMemo', 'complete']],
        'editor in progress' => [UserRole::Editor, ReceptionCaseStatus::InProgress, ['handover', 'updateWorkMemo', 'complete']],
        'editor handover' => [UserRole::Editor, ReceptionCaseStatus::Handover, ['start', 'updateWorkMemo', 'complete']],
        'editor completed' => [UserRole::Editor, ReceptionCaseStatus::Completed, []],
        'admin draft' => [UserRole::Admin, ReceptionCaseStatus::Draft, []],
        'admin received' => [UserRole::Admin, ReceptionCaseStatus::Received, ['start', 'updateWorkMemo', 'complete']],
        'admin in progress' => [UserRole::Admin, ReceptionCaseStatus::InProgress, ['handover', 'updateWorkMemo', 'complete']],
        'admin handover' => [UserRole::Admin, ReceptionCaseStatus::Handover, ['start', 'updateWorkMemo', 'complete']],
        'admin completed' => [UserRole::Admin, ReceptionCaseStatus::Completed, []],
    ]);

test('assigned workers can act only in the permitted workflow states', function (
    string $ability,
    ReceptionCaseStatus $status,
    array $allowedAbilities,
): void {
    $user = User::factory()->make(['id' => 1]);
    $case = ReceptionCase::factory()->make([
        'status' => $status,
        'receptor_user_id' => 2,
        'assigned_user_id' => $user->id,
        'reception_document_type_id' => 1,
    ]);

    expect(Gate::forUser($user)->allows($ability, $case))
        ->toBe(in_array($ability, $allowedAbilities, true));
})->with(['start', 'handover', 'updateWorkMemo', 'complete'])
    ->with([
        'draft' => [ReceptionCaseStatus::Draft, []],
        'received' => [ReceptionCaseStatus::Received, ['updateWorkMemo']],
        'in progress' => [ReceptionCaseStatus::InProgress, ['handover', 'updateWorkMemo', 'complete']],
        'handover' => [ReceptionCaseStatus::Handover, ['updateWorkMemo']],
        'completed' => [ReceptionCaseStatus::Completed, []],
    ]);
