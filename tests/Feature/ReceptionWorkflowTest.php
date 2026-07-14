<?php

use App\Models\AuditLog;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\ReceptionCaseAttachment;
use App\Models\ReceptionCaseSeenState;
use App\Models\ReceptionCaseSequence;
use App\Models\ReceptionDocumentType;
use App\Models\User;
use App\ReceptionCasePriority;
use App\ReceptionCaseStatus;
use App\Services\BusinessDate;
use App\Services\ReceptionCaseNumberGenerator;
use App\Services\ReceptionCaseWorkflow;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function receptionPayload(?ReceptionDocumentType $documentType = null): array
{
    $documentType ??= ReceptionDocumentType::factory()->create();

    return [
        'company_name' => '和城建設',
        'site_name' => '日本橋現場',
        'reception_document_type_id' => $documentType->id,
        'reception_content' => '図面の確認をお願いします。',
        'due_on' => BusinessDate::today()->addDays(2)->toDateString(),
        'scheduled_on' => null,
    ];
}

test('authenticated users can access the intake screen without creating a draft', function (): void {
    $user = User::factory()->create();
    ReceptionDocumentType::factory()->create(['name' => '見積依頼']);

    $this->actingAs($user)
        ->get(route('reception.cases.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reception/cases/form')
            ->has('documentTypes', 1)
        );

    expect(ReceptionCase::query()->count())->toBe(0);
});

test('first autosave creates a private draft with a date scoped case number', function (): void {
    $user = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('reception.cases.store-draft'), [
            'company_name' => '初回下書き',
            'reception_document_type_id' => $documentType->id,
        ])
        ->assertCreated()
        ->assertJsonPath('case_number', 'WJA-C-'.BusinessDate::today()->format('Ymd').'-0001');

    $case = ReceptionCase::query()->firstOrFail();

    expect($response->json('id'))->toBe($case->id)
        ->and($case->status)->toBe(ReceptionCaseStatus::Draft)
        ->and($case->priority)->toBe(ReceptionCasePriority::Normal)
        ->and($case->receptor_user_id)->toBe($user->id)
        ->and($case->last_activity_at)->toBeNull();

    $this->actingAs(User::factory()->create())
        ->get(route('reception.cases.show', $case))
        ->assertForbidden();
});

test('media first draft creation can reserve a private draft without text fields', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('reception.cases.store-draft'), [
            'priority' => ReceptionCasePriority::Normal->value,
            'media_only' => true,
        ])
        ->assertCreated();

    $case = ReceptionCase::query()->findOrFail($response->json('id'));

    expect($case->status)->toBe(ReceptionCaseStatus::Draft)
        ->and($case->receptor_user_id)->toBe($user->id)
        ->and($case->company_name)->toBeNull()
        ->and($case->last_activity_at)->toBeNull();
});

test('intake draft flow lets the receptor set and preserve priority', function (): void {
    $user = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();

    $draftId = $this->actingAs($user)
        ->postJson(route('reception.cases.store-draft'), [
            'company_name' => '優先下書き',
            'priority' => ReceptionCasePriority::Middle->value,
        ])
        ->assertCreated()
        ->json('id');

    $draft = ReceptionCase::query()->findOrFail($draftId);

    expect($draft->priority)->toBe(ReceptionCasePriority::Middle);

    $this->patchJson(route('reception.cases.update-draft', $draft), [
        'priority' => ReceptionCasePriority::High->value,
    ])->assertOk();

    expect($draft->refresh()->priority)->toBe(ReceptionCasePriority::High);

    $this->post(route('reception.cases.submit', $draft), receptionPayload($documentType))
        ->assertRedirect(route('reception.cases.create'));

    expect($draft->refresh()->priority)->toBe(ReceptionCasePriority::High)
        ->and($draft->status)->toBe(ReceptionCaseStatus::Received);
});

test('case priority is stored through submit and presented to the frontend', function (): void {
    $user = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $user->id,
        'priority' => ReceptionCasePriority::Normal,
    ]);

    $this->actingAs($user)
        ->post(route('reception.cases.submit', $case), [
            ...receptionPayload($documentType),
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertRedirect(route('reception.cases.create'));

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::High);

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.priority', ReceptionCasePriority::High->value)
            ->where('caseData.can.update_priority', false)
        );
});

test('draft case numbers are unique and not reused after deletion', function (): void {
    $user = User::factory()->create();

    $firstId = $this->actingAs($user)
        ->postJson(route('reception.cases.store-draft'), ['company_name' => '削除する下書き'])
        ->assertCreated()
        ->json('id');

    $this->delete(route('reception.cases.destroy-draft', $firstId))
        ->assertRedirect(route('reception.home'));

    $this->postJson(route('reception.cases.store-draft'), ['company_name' => '次の下書き'])
        ->assertCreated()
        ->assertJsonPath('case_number', 'WJA-C-'.BusinessDate::today()->format('Ymd').'-0002');
});

test('case number generator continues from the committed sequence value', function (): void {
    $generator = app(ReceptionCaseNumberGenerator::class);
    $today = BusinessDate::today()->format('Ymd');

    expect($generator->next())->toBe("WJA-C-{$today}-0001")
        ->and($generator->next())->toBe("WJA-C-{$today}-0002");

    // Simulate another request committing an advance to the sequence between our
    // calls. The next number must continue from the true stored value, guarding
    // against a stale in-memory read (which would otherwise reuse a number and
    // collide on the case_number unique index).
    ReceptionCaseSequence::query()
        ->where('sequence_date', BusinessDate::today()->toDateString())
        ->update(['last_number' => 40]);

    expect($generator->next())->toBe("WJA-C-{$today}-0041");
});

test('submitting a draft requires required fields and changes it to received', function (): void {
    $user = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->create([
        'receptor_user_id' => $user->id,
        'reception_document_type_id' => $documentType->id,
        'company_name' => null,
    ]);

    $this->actingAs($user)
        ->post(route('reception.cases.submit', $case), ['company_name' => '不足'])
        ->assertSessionHasErrors(['site_name', 'reception_document_type_id', 'reception_content', 'due_on']);

    $this->post(route('reception.cases.submit', $case), receptionPayload($documentType))
        ->assertRedirect(route('reception.cases.create'));

    expect($case->refresh()->status)->toBe(ReceptionCaseStatus::Received);

    $this->actingAs(User::factory()->create())
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reception/cases/show')
            ->where('caseData.status', ReceptionCaseStatus::Received->value)
        );
});

test('receptor and workflow managers can edit active fields but other users cannot', function (): void {
    $receptor = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $otherUser = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $receptor->id,
        'company_name' => '変更前',
    ]);

    $this->actingAs($otherUser)
        ->patch(route('reception.cases.update', $case), ['company_name' => '不可'])
        ->assertForbidden();

    $this->actingAs($receptor)
        ->patch(route('reception.cases.update', $case), [
            ...receptionPayload(),
            'company_name' => '受付者更新',
        ])
        ->assertRedirect();

    expect($case->refresh()->company_name)->toBe('受付者更新');

    $this->actingAs($manager)
        ->patch(route('reception.cases.update', $case), [
            ...receptionPayload(),
            'company_name' => '管理者更新',
        ])
        ->assertRedirect();

    expect($case->refresh()->company_name)->toBe('管理者更新');
});

test('deadline changes after reception are regular updates', function (): void {
    $receptor = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $receptor->id,
        'reception_document_type_id' => $documentType->id,
        'due_on' => BusinessDate::today()->addDay()->toDateString(),
    ]);
    $newDueOn = BusinessDate::today()->addDays(5)->toDateString();

    $this->actingAs($receptor)
        ->patch(route('reception.cases.update', $case), [
            ...receptionPayload($documentType),
            'due_on' => $newDueOn,
        ])
        ->assertRedirect();

    expect($case->refresh()->due_on?->toDateString())->toBe($newDueOn)
        ->and(ReceptionCaseActivity::query()
            ->where('reception_case_id', $case->id)
            ->where('type', ReceptionCaseActivity::TYPE_UPDATED)
            ->exists())->toBeTrue();

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.due_on', $newDueOn)
        );
});

test('editing an active case requires all fields and never silently blanks them', function (): void {
    $receptor = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $receptor->id,
        'reception_document_type_id' => $documentType->id,
        'site_name' => '元の現場',
        'reception_content' => '元の内容',
    ]);

    $this->actingAs($receptor)
        ->patch(route('reception.cases.update', $case), ['company_name' => '新会社'])
        ->assertSessionHasErrors(['site_name', 'reception_document_type_id', 'reception_content', 'due_on']);

    expect($case->refresh()->site_name)->toBe('元の現場')
        ->and($case->reception_content)->toBe('元の内容');

    $this->patch(route('reception.cases.update', $case), [
        ...receptionPayload($documentType),
        'company_name' => '新会社',
        'site_name' => '新現場',
    ])->assertRedirect();

    expect($case->refresh()->company_name)->toBe('新会社')
        ->and($case->site_name)->toBe('新現場');
});

test('workflow managers cannot edit another users draft', function (): void {
    $creator = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $draft = ReceptionCase::factory()->create(['receptor_user_id' => $creator->id]);

    $this->actingAs($manager)
        ->patch(route('reception.cases.update', $draft), ['company_name' => '横取り'])
        ->assertForbidden();

    $this->patchJson(route('reception.cases.update-draft', $draft), ['company_name' => '横取り'])
        ->assertForbidden();
});

test('editing a draft stays quiet without activity or last activity bump', function (): void {
    $creator = User::factory()->create();
    $draft = ReceptionCase::factory()->create([
        'receptor_user_id' => $creator->id,
        'last_activity_at' => null,
    ]);

    $this->actingAs($creator)
        ->patch(route('reception.cases.update', $draft), ['company_name' => '下書き更新'])
        ->assertRedirect();

    expect($draft->refresh()->company_name)->toBe('下書き更新')
        ->and($draft->last_activity_at)->toBeNull()
        ->and($draft->activities()->where('type', 'updated')->exists())->toBeFalse();
});

test('activity history hides draft creation entries', function (): void {
    $user = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create();

    $draftId = $this->actingAs($user)
        ->postJson(route('reception.cases.store-draft'), [
            'company_name' => '下書き履歴会社',
        ])
        ->assertCreated()
        ->json('id');

    $case = ReceptionCase::query()->findOrFail($draftId);

    expect($case->activities()
        ->where('type', ReceptionCaseActivity::TYPE_CREATED_DRAFT)
        ->exists())->toBeTrue();

    $this->post(route('reception.cases.submit', $case), receptionPayload($documentType))
        ->assertRedirect(route('reception.cases.create'));

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.activities', function ($activities): bool {
                $activityTypes = collect($activities)->pluck('type');

                return $activityTypes->contains(ReceptionCaseActivity::TYPE_SUBMITTED)
                    && ! $activityTypes->contains(ReceptionCaseActivity::TYPE_CREATED_DRAFT);
            })
        );
});

test('workflow managers assign explicitly before starting work', function (): void {
    $manager = User::factory()->editor()->create();
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $assignee->id,
        ])
        ->assertRedirect();

    expect($case->refresh()->assigned_user_id)->toBe($assignee->id)
        ->and($case->status)->toBe(ReceptionCaseStatus::Received);

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    expect($case->refresh()->status)->toBe(ReceptionCaseStatus::InProgress);

    $startedActivity = $case->activities()
        ->where('type', ReceptionCaseActivity::TYPE_STARTED)
        ->firstOrFail();

    expect($startedActivity->from_assigned_user_id)->toBeNull()
        ->and($startedActivity->to_assigned_user_id)->toBe($assignee->id);

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.activities.0.type', ReceptionCaseActivity::TYPE_ASSIGNED)
            ->where('caseData.activities.0.from_status', null)
            ->where('caseData.activities.0.to_status', null)
            ->where('caseData.activities.0.from_assigned_user', null)
            ->where('caseData.activities.0.to_assigned_user.id', $assignee->id)
            ->where('caseData.activities.1.type', ReceptionCaseActivity::TYPE_STARTED)
            ->where('caseData.activities.1.from_status', ReceptionCaseStatus::Received->value)
            ->where('caseData.activities.1.to_status', ReceptionCaseStatus::InProgress->value)
            ->where('caseData.activities.1.from_assigned_user', null)
            ->where('caseData.activities.1.to_assigned_user', null)
        );
});

test('only workflow managers can set assignees from review cases', function (): void {
    $viewer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create();

    $this->actingAs($viewer)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviewCases', [])
            ->where('assigneeOptions', [])
        );

    $this->patch(route('reception.cases.assign', $case), [
        'assigned_user_id' => $assignee->id,
    ])->assertForbidden();

    $this->actingAs($admin)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviewCases.0.id', $case->id)
            ->where('reviewCases.0.can.assign', true)
            ->where('assigneeOptions', fn ($options): bool => collect($options)->contains(
                fn (array $option): bool => $option['id'] === $assignee->id,
            ))
        );
});

test('task list does not expose my reception drafts', function (): void {
    $user = User::factory()->create();
    $draft = ReceptionCase::factory()->create(['receptor_user_id' => $user->id]);
    $activeCase = ReceptionCase::factory()->inProgress($user)->create();

    $this->actingAs($user)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->missing('drafts')
            ->has('inProgressCases', 1)
            ->where('inProgressCases.0.id', $activeCase->id)
            ->where('inProgressCases', fn ($cases): bool => ! collect($cases)->pluck('id')->contains($draft->id))
        );
});

test('workflow manager review shows unseen cases before already seen cases', function (): void {
    $manager = User::factory()->editor()->create();
    $seenCase = ReceptionCase::factory()->received()->create([
        'due_on' => BusinessDate::today()->toDateString(),
        'last_activity_at' => now()->subHour(),
    ]);
    $unseenCase = ReceptionCase::factory()->received()->create([
        'due_on' => BusinessDate::today()->addDays(10)->toDateString(),
        'last_activity_at' => now(),
    ]);

    ReceptionCaseSeenState::query()->create([
        'reception_case_id' => $seenCase->id,
        'user_id' => $manager->id,
        'seen_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviewCases.0.id', $unseenCase->id)
            ->where('reviewCases.0.is_unseen', true)
            ->where('reviewCases.0.last_seen_at', null)
            ->where('reviewCases.1.id', $seenCase->id)
            ->where('reviewCases.1.is_unseen', false)
            ->where('reviewCases.1.last_seen_at', null)
        );
});

test('workflow manager review sorts by priority before due date within unseen state', function (): void {
    $manager = User::factory()->editor()->create();
    $normalCase = ReceptionCase::factory()->received()->create([
        'priority' => ReceptionCasePriority::Normal,
        'due_on' => BusinessDate::today()->toDateString(),
        'last_activity_at' => now(),
    ]);
    $middleCase = ReceptionCase::factory()->received()->create([
        'priority' => ReceptionCasePriority::Middle,
        'due_on' => BusinessDate::today()->addDay()->toDateString(),
        'last_activity_at' => now(),
    ]);
    $highCase = ReceptionCase::factory()->received()->create([
        'priority' => ReceptionCasePriority::High,
        'due_on' => BusinessDate::today()->addDays(10)->toDateString(),
        'last_activity_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviewCases.0.id', $highCase->id)
            ->where('reviewCases.1.id', $middleCase->id)
            ->where('reviewCases.2.id', $normalCase->id)
        );
});

test('in progress task lists sort non-overdue cases by priority before due date', function (): void {
    $assignee = User::factory()->create();
    $normalCase = ReceptionCase::factory()->inProgress($assignee)->create([
        'priority' => ReceptionCasePriority::Normal,
        'due_on' => BusinessDate::today()->addDay()->toDateString(),
    ]);
    $middleCase = ReceptionCase::factory()->inProgress($assignee)->create([
        'priority' => ReceptionCasePriority::Middle,
        'due_on' => BusinessDate::today()->addDays(2)->toDateString(),
    ]);
    $highCase = ReceptionCase::factory()->inProgress($assignee)->create([
        'priority' => ReceptionCasePriority::High,
        'due_on' => BusinessDate::today()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($assignee)
        ->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('inProgressCases.0.id', $highCase->id)
            ->where('inProgressCases.1.id', $middleCase->id)
            ->where('inProgressCases.2.id', $normalCase->id)
        );

    $this->get(route('reception.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('assignedTasks.0.id', $highCase->id)
            ->where('assignedTasks.1.id', $middleCase->id)
            ->where('assignedTasks.2.id', $normalCase->id)
        );
});

test('case priority quick update uses priority authorization and records activity', function (): void {
    $receptor = User::factory()->create();
    $editor = User::factory()->editor()->create();
    $admin = User::factory()->admin()->create();
    $viewer = User::factory()->create();
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($assignee)->create([
        'receptor_user_id' => $receptor->id,
        'priority' => ReceptionCasePriority::Normal,
        'last_activity_at' => now()->subHour(),
    ]);
    $draft = ReceptionCase::factory()->create([
        'receptor_user_id' => $receptor->id,
        'priority' => ReceptionCasePriority::Normal,
    ]);
    $completedCase = ReceptionCase::factory()->completed()->create([
        'priority' => ReceptionCasePriority::Normal,
    ]);

    ReceptionCaseSeenState::query()->create([
        'reception_case_id' => $case->id,
        'user_id' => $assignee->id,
        'seen_at' => now()->subMinute(),
    ]);

    $this->actingAs($viewer)
        ->patch(route('reception.cases.priority.update', $case), [
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertForbidden();

    $this->actingAs($receptor)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.can.update', true)
            ->where('caseData.can.update_priority', false)
        );

    $this->patch(route('reception.cases.priority.update', $case), [
        'priority' => ReceptionCasePriority::High->value,
    ])->assertForbidden();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::Normal);

    $this->actingAs($editor)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.can.update_priority', true)
        );

    $this->patch(route('reception.cases.priority.update', $draft), [
        'priority' => ReceptionCasePriority::High->value,
    ])->assertForbidden();

    expect($draft->refresh()->priority)->toBe(ReceptionCasePriority::Normal);

    $this->actingAs($admin)
        ->patch(route('reception.cases.priority.update', $completedCase), [
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertForbidden();

    expect($completedCase->refresh()->priority)->toBe(ReceptionCasePriority::Normal);

    $this->actingAs($editor)
        ->patch(route('reception.cases.priority.update', $case), [
            'priority' => 'invalid',
        ])
        ->assertSessionHasErrors(['priority']);

    $this->patch(route('reception.cases.priority.update', $case), [
        'priority' => ReceptionCasePriority::Middle->value,
    ])->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::Middle)
        ->and($case->activities()->where('type', ReceptionCaseActivity::TYPE_UPDATED)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('reception.cases.priority.update', $case), [
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::High);

    $this->actingAs($assignee)
        ->get(route('reception.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('assignedTasks.0.id', $case->id)
            ->where('assignedTasks.0.priority', ReceptionCasePriority::High->value)
            ->where('assignedTasks.0.is_unseen', true)
        );
});

test('a general update that omits priority preserves the stored priority', function (): void {
    $manager = User::factory()->editor()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'priority' => ReceptionCasePriority::High,
    ]);

    $payload = receptionPayload($documentType);
    expect($payload)->not->toHaveKey('priority');

    $this->actingAs($manager)
        ->patch(route('reception.cases.update', $case), $payload)
        ->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::High);
});

test('receptors can edit active fields but cannot change priority through case updates', function (): void {
    $receptor = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $documentType = ReceptionDocumentType::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'receptor_user_id' => $receptor->id,
        'reception_document_type_id' => $documentType->id,
        'priority' => ReceptionCasePriority::Normal,
        'company_name' => '変更前',
    ]);
    $payload = [
        ...receptionPayload($documentType),
        'company_name' => '受付者更新',
    ];

    $this->actingAs($receptor)
        ->patch(route('reception.cases.update', $case), [
            ...$payload,
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertForbidden();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::Normal)
        ->and($case->company_name)->toBe('変更前');

    $this->patch(route('reception.cases.update', $case), [
        ...$payload,
        'priority' => ReceptionCasePriority::Normal->value,
    ])->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::Normal)
        ->and($case->company_name)->toBe('受付者更新');

    $this->patch(route('reception.cases.update', $case), [
        ...receptionPayload($documentType),
        'company_name' => '優先度なし更新',
    ])->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::Normal)
        ->and($case->company_name)->toBe('優先度なし更新');

    $this->actingAs($manager)
        ->patch(route('reception.cases.update', $case), [
            ...receptionPayload($documentType),
            'priority' => ReceptionCasePriority::High->value,
        ])
        ->assertRedirect();

    expect($case->refresh()->priority)->toBe(ReceptionCasePriority::High);
});

test('assignees can request handover with memo and managers can reassign and restart', function (): void {
    $manager = User::factory()->editor()->create();
    $assignee = User::factory()->create();
    $nextAssignee = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($assignee)->create();

    $this->actingAs($assignee)
        ->post(route('reception.cases.handover', $case), ['memo' => ''])
        ->assertSessionHasErrors([
            'memo' => '引継ぎメモは必須項目です。',
        ]);

    $this->post(route('reception.cases.handover', $case), ['memo' => '次の担当へお願いします'])
        ->assertRedirect();

    expect($case->refresh()->status)->toBe(ReceptionCaseStatus::Handover);

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $nextAssignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    expect($case->refresh()->status)->toBe(ReceptionCaseStatus::InProgress)
        ->and($case->assigned_user_id)->toBe($nextAssignee->id);
});

test('assignment memo validation uses the assignee memo label', function (): void {
    $manager = User::factory()->editor()->create();
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $assignee->id,
            'memo' => ['not-a-string'],
        ])
        ->assertSessionHasErrors([
            'memo' => '担当者へのメモには、文字列を指定してください。',
        ]);
});

test('assignees and managers can update work memo quietly', function (): void {
    $assignee = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $viewer = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($assignee)->create([
        'last_activity_at' => now()->subHour(),
    ]);
    $lastActivityAt = $case->last_activity_at?->toISOString();

    $this->actingAs($assignee)
        ->patch(route('reception.cases.work-memo.update', $case), [
            'work_memo' => '  現地確認中  ',
        ])
        ->assertRedirect();

    expect($case->refresh()->work_memo)->toBe('現地確認中')
        ->and($case->last_activity_at?->toISOString())->toBe($lastActivityAt)
        ->and($case->activities()->count())->toBe(0);

    $this->actingAs($viewer)
        ->patch(route('reception.cases.work-memo.update', $case), [
            'work_memo' => '別ユーザーのメモ',
        ])
        ->assertForbidden();

    expect($case->refresh()->work_memo)->toBe('現地確認中');

    $this->actingAs($manager)
        ->patch(route('reception.cases.work-memo.update', $case), [
            'work_memo' => '   ',
        ])
        ->assertRedirect();

    expect($case->refresh()->work_memo)->toBeNull()
        ->and($case->activities()->count())->toBe(0);
});

test('case detail exposes work memo and update permission', function (): void {
    $assignee = User::factory()->create();
    $viewer = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($assignee)->create([
        'work_memo' => '寸法確認が必要',
    ]);

    $this->actingAs($assignee)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.work_memo', '寸法確認が必要')
            ->where('caseData.can.update_work_memo', true)
        );

    $this->actingAs($viewer)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.work_memo', '寸法確認が必要')
            ->where('caseData.can.update_work_memo', false)
        );
});

test('case detail shows assignee handover chain when reassigned after handovers', function (): void {
    $manager = User::factory()->editor()->create();
    $firstAssignee = User::factory()->create(['name' => 'A担当']);
    $secondAssignee = User::factory()->create(['name' => 'B担当']);
    $thirdAssignee = User::factory()->create(['name' => 'C担当']);
    $case = ReceptionCase::factory()->received()->create();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $firstAssignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    $this->actingAs($firstAssignee)
        ->post(route('reception.cases.handover', $case), [
            'memo' => 'B担当へお願いします',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $secondAssignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    $this->actingAs($secondAssignee)
        ->post(route('reception.cases.handover', $case), [
            'memo' => 'C担当へお願いします',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $thirdAssignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.assigned_user.id', $thirdAssignee->id)
            ->where('caseData.assigned_user_handover_chain', fn ($chain): bool => collect($chain)->pluck('id')->all() === [
                $firstAssignee->id,
                $secondAssignee->id,
                $thirdAssignee->id,
            ])
        );
});

test('activity history only exposes changed status or assignee values', function (): void {
    $manager = User::factory()->editor()->create();
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $assignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    $this->actingAs($assignee)
        ->post(route('reception.cases.handover', $case), [
            'memo' => '次の担当へお願いします',
        ])
        ->assertRedirect();

    $this->actingAs($manager)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.activities', function ($activities) use ($assignee): bool {
                $activitiesByType = collect($activities)->keyBy('type');
                $assigned = $activitiesByType->get(ReceptionCaseActivity::TYPE_ASSIGNED);
                $started = $activitiesByType->get(ReceptionCaseActivity::TYPE_STARTED);
                $handover = $activitiesByType->get(ReceptionCaseActivity::TYPE_HANDOVER_REQUESTED);

                return $assigned['from_status'] === null
                    && $assigned['to_status'] === null
                    && $assigned['from_assigned_user'] === null
                    && $assigned['to_assigned_user']['id'] === $assignee->id
                    && $started['from_status'] === ReceptionCaseStatus::Received->value
                    && $started['to_status'] === ReceptionCaseStatus::InProgress->value
                    && $started['from_assigned_user'] === null
                    && $started['to_assigned_user'] === null
                    && $handover['from_status'] === ReceptionCaseStatus::InProgress->value
                    && $handover['to_status'] === ReceptionCaseStatus::Handover->value
                    && $handover['from_assigned_user'] === null
                    && $handover['to_assigned_user'] === null;
            })
        );
});

test('assignees can complete tasks and completed cases move to archive', function (): void {
    $assignee = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($assignee)->create([
        'company_name' => '完了会社',
        'site_name' => '完了現場',
    ]);

    $this->actingAs($assignee)
        ->post(route('reception.cases.complete', $case))
        ->assertRedirect(route('reception.archive.index'));

    $completedActivity = $case->activities()
        ->where('type', ReceptionCaseActivity::TYPE_COMPLETED)
        ->firstOrFail();

    expect($case->refresh()->status)->toBe(ReceptionCaseStatus::Completed)
        ->and($case->completed_by_user_id)->toBe($assignee->id)
        ->and($completedActivity->memo)->toBeNull();

    $this->get(route('reception.cases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('inProgressCases', [])
        );

    $this->get(route('reception.archive.index', ['keyword' => '完了会社']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reception/archive/index')
            ->has('cases', 1)
            ->where('cases.0.id', $case->id)
        );
});

test('reception home only shows in progress assigned tasks', function (): void {
    $assignee = User::factory()->create();
    $inProgress = ReceptionCase::factory()->inProgress($assignee)->create([
        'last_activity_at' => now()->subMinutes(10),
    ]);
    $draft = ReceptionCase::factory()->create(['receptor_user_id' => $assignee->id]);

    ReceptionCase::factory()->received()->create(['assigned_user_id' => $assignee->id]);
    ReceptionCase::factory()->handover($assignee)->create();
    ReceptionCase::factory()->completed()->create(['assigned_user_id' => $assignee->id]);
    ReceptionCaseSeenState::query()->create([
        'reception_case_id' => $inProgress->id,
        'user_id' => $assignee->id,
        'seen_at' => now()->subMinute(),
    ]);

    $this->actingAs($assignee)
        ->get(route('reception.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('assignedTasks', 1)
            ->where('assignedTasks.0.id', $inProgress->id)
            ->where('assignedTasks.0.status', ReceptionCaseStatus::InProgress->value)
            ->where('assignedTasks.0.last_seen_at', fn ($lastSeenAt): bool => is_string($lastSeenAt) && $lastSeenAt !== '')
            ->has('drafts', 1)
            ->where('drafts.0.id', $draft->id)
        );
});

test('archive completion date range works', function (): void {
    $user = User::factory()->create();
    ReceptionCase::factory()->completed($user)->create([
        'company_name' => '範囲内',
        'completed_at' => '2026-06-10 09:00:00',
    ]);
    ReceptionCase::factory()->completed($user)->create([
        'company_name' => '範囲外',
        'completed_at' => '2026-07-10 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('reception.archive.index', [
            'completed_from' => '2026-06-01',
            'completed_to' => '2026-06-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('cases', 1)
            ->where('cases.0.company_name', '範囲内')
        );
});

test('case detail opened from archive returns to archive list filters', function (): void {
    $user = User::factory()->create();
    $case = ReceptionCase::factory()->completed($user)->create();

    $this->actingAs($user)
        ->get(route('reception.cases.show', [
            'reception_case' => $case,
            'from' => 'archive',
            'keyword' => '完了会社',
            'completed_from' => '2026-06-01',
            'completed_to' => '2026-06-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('returnTo.source', 'archive')
            ->where('returnTo.filters.keyword', '完了会社')
            ->where('returnTo.filters.completed_from', '2026-06-01')
            ->where('returnTo.filters.completed_to', '2026-06-30')
        );
});

test('case detail opened from reception home returns to reception home', function (): void {
    $user = User::factory()->create();
    $case = ReceptionCase::factory()->inProgress($user)->create();

    $this->actingAs($user)
        ->get(route('reception.cases.show', [
            'reception_case' => $case,
            'from' => 'home',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('returnTo.source', 'home')
        );
});

test('only assigned users mark a reception case seen from detail', function (): void {
    $manager = User::factory()->editor()->create();
    $viewer = User::factory()->create();
    $assignee = User::factory()->create();
    $nextAssignee = User::factory()->create();
    $case = ReceptionCase::factory()->handover($assignee)->create([
        'last_activity_at' => now()->subMinute(),
    ]);

    $this->actingAs($viewer)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.is_unseen', true)
            ->where('caseData.last_seen_at', null)
        );

    expect(ReceptionCaseSeenState::query()
        ->where('reception_case_id', $case->id)
        ->where('user_id', $viewer->id)
        ->exists())->toBeFalse();

    $this->actingAs($assignee)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.is_unseen', false)
            ->where('caseData.last_seen_at', fn ($lastSeenAt): bool => is_string($lastSeenAt) && $lastSeenAt !== '')
        );

    expect(ReceptionCaseSeenState::query()
        ->where('reception_case_id', $case->id)
        ->where('user_id', $assignee->id)
        ->exists())->toBeTrue();

    $this->actingAs($viewer)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.is_unseen', true)
            ->where('caseData.last_seen_at', fn ($lastSeenAt): bool => is_string($lastSeenAt) && $lastSeenAt !== '')
        );

    expect(ReceptionCaseSeenState::query()
        ->where('reception_case_id', $case->id)
        ->where('user_id', $viewer->id)
        ->exists())->toBeFalse();

    $this->actingAs($manager)
        ->patch(route('reception.cases.assign', $case), [
            'assigned_user_id' => $nextAssignee->id,
        ])
        ->assertRedirect();

    $this->patch(route('reception.cases.start', $case))
        ->assertRedirect();

    $this->actingAs($nextAssignee)
        ->get(route('reception.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('assignedTasks', 1)
            ->where('assignedTasks.0.id', $case->id)
            ->where('assignedTasks.0.is_unseen', true)
        );
});

test('the intake screen exposes attachment constraints from the server', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reception.cases.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('attachmentConstraints.max_attachments', ReceptionCaseAttachment::MAX_ATTACHMENTS_PER_CASE)
            ->where('attachmentConstraints.max_recordings', ReceptionCaseAttachment::MAX_RECORDINGS_PER_CASE)
            ->where('attachmentConstraints.max_recording_seconds', ReceptionCaseAttachment::MAX_RECORDING_SECONDS)
            ->where('attachmentConstraints.max_file_kilobytes', ReceptionCaseAttachment::MAX_FILE_KILOBYTES)
            ->where('attachmentConstraints.accept', fn ($accept): bool => is_string($accept)
                && str_contains($accept, '.pdf')
                && str_contains($accept, 'application/pdf'))
        );
});

test('the workflow service ignores a repeated transition after a lost race', function (): void {
    $assignee = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $case = ReceptionCase::factory()->received()->create([
        'assigned_user_id' => $assignee->id,
    ]);

    $workflow = app(ReceptionCaseWorkflow::class);

    // Simulate two concurrent requests that both passed the pre-transaction
    // checks: the second is an idempotent no-op, still reporting success.
    $first = $workflow->start($case, $manager, null);
    $second = $workflow->start($case, $manager, null);

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and($case->refresh()->status)->toBe(ReceptionCaseStatus::InProgress)
        ->and($case->activities()->where('type', ReceptionCaseActivity::TYPE_STARTED)->count())->toBe(1);
});

test('the workflow service reports a blocked transition instead of applying it', function (): void {
    $assignee = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $case = ReceptionCase::factory()->completed($assignee)->create([
        'assigned_user_id' => $assignee->id,
    ]);

    $workflow = app(ReceptionCaseWorkflow::class);

    $applied = $workflow->start($case, $manager, null);

    expect($applied)->toBeFalse()
        ->and($case->refresh()->status)->toBe(ReceptionCaseStatus::Completed)
        ->and($case->activities()->where('type', ReceptionCaseActivity::TYPE_STARTED)->exists())->toBeFalse();
});

test('the workflow service refuses to assign a case that is no longer active', function (): void {
    $assignee = User::factory()->create();
    $nextAssignee = User::factory()->create();
    $manager = User::factory()->editor()->create();
    $case = ReceptionCase::factory()->completed($assignee)->create([
        'assigned_user_id' => $assignee->id,
    ]);

    $workflow = app(ReceptionCaseWorkflow::class);

    $applied = $workflow->assign($case, $manager, $nextAssignee->id, null);

    expect($applied)->toBeFalse()
        ->and($case->refresh()->assigned_user_id)->toBe($assignee->id)
        ->and($case->activities()->where('type', ReceptionCaseActivity::TYPE_ASSIGNED)->exists())->toBeFalse();
});

test('deleting a draft records an audit entry after the row is gone', function (): void {
    $user = User::factory()->create();
    $draft = ReceptionCase::factory()->create(['receptor_user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('reception.cases.destroy-draft', $draft))
        ->assertRedirect(route('reception.home'));

    expect(ReceptionCase::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'reception_cases.draft_deleted')->exists())->toBeTrue();
});
