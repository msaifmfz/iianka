<?php

declare(strict_types=1);

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\ReceptionCase;
use App\Models\User;

test('requests to delete or submit another users draft return 403 without changing the draft', function (
    string $method,
    string $route,
): void {
    $owner = User::factory()->create();
    $draft = ReceptionCase::factory()->for($owner, 'receptor')->create();
    $manager = User::factory()->editor()->create();

    $this->actingAs($manager)
        ->{$method}(route($route, $draft), [
            'company_name' => '変更不可',
            'site_name' => '変更不可の現場',
            'reception_document_type_id' => $draft->reception_document_type_id,
            'reception_content' => '変更不可の内容',
            'due_on' => '2026-11-10',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('reception_cases', [
        'id' => $draft->id,
        'status' => ReceptionCaseStatus::Draft->value,
        'receptor_user_id' => $owner->id,
        'company_name' => $draft->company_name,
        'site_name' => $draft->site_name,
        'reception_content' => $draft->reception_content,
    ]);
    $this->assertDatabaseMissing('reception_case_activities', ['reception_case_id' => $draft->id]);
})->with([
    'delete draft' => ['delete', 'reception.cases.destroy-draft'],
    'submit draft' => ['post', 'reception.cases.submit'],
]);

test('assigned viewers cannot start or complete a received case and receive 403', function (
    string $method,
    string $route,
): void {
    $worker = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create(['assigned_user_id' => $worker->id]);

    $this->actingAs($worker)
        ->{$method}(route($route, $case), ['memo' => '未承認の操作'])
        ->assertForbidden();

    $this->assertDatabaseHas('reception_cases', [
        'id' => $case->id,
        'status' => ReceptionCaseStatus::Received->value,
        'assigned_user_id' => $worker->id,
        'completed_at' => null,
    ]);
    $this->assertDatabaseMissing('reception_case_activities', ['reception_case_id' => $case->id]);
})->with([
    'start case' => ['patch', 'reception.cases.start'],
    'complete case' => ['post', 'reception.cases.complete'],
]);

test('unassigned viewers cannot hand over an in progress case and receive 403', function (): void {
    $case = ReceptionCase::factory()->inProgress()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->post(route('reception.cases.handover', $case), ['memo' => '未承認の引継ぎ'])
        ->assertForbidden();

    $this->assertDatabaseHas('reception_cases', [
        'id' => $case->id,
        'status' => ReceptionCaseStatus::InProgress->value,
        'assigned_user_id' => $case->assigned_user_id,
    ]);
    $this->assertDatabaseMissing('reception_case_activities', ['reception_case_id' => $case->id]);
});

test('even assigned managers cannot change a completed cases work memo and receive 403', function (): void {
    $manager = User::factory()->editor()->create();
    $case = ReceptionCase::factory()->completed($manager)->create([
        'assigned_user_id' => $manager->id,
        'work_memo' => '完了時のメモ',
    ]);

    $this->actingAs($manager)
        ->patch(route('reception.cases.work-memo.update', $case), ['work_memo' => '完了後の変更'])
        ->assertForbidden();

    $this->assertDatabaseHas('reception_cases', [
        'id' => $case->id,
        'status' => ReceptionCaseStatus::Completed->value,
        'work_memo' => '完了時のメモ',
        'completed_by_user_id' => $manager->id,
    ]);
    $this->assertDatabaseMissing('reception_case_activities', ['reception_case_id' => $case->id]);
});
