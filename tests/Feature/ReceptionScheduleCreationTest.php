<?php

declare(strict_types=1);

use App\Domain\Reception\Enums\ReceptionCaseActivityType;
use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Models\AuditLog;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ReceptionCase;
use App\Models\ReceptionDocumentType;
use App\Models\User;
use App\Services\BusinessDate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function receptionConstructionSchedulePayload(ReceptionCase $case, array $overrides = []): array
{
    return [
        'reception_case_id' => $case->id,
        'scheduled_on' => $case->scheduled_on?->toDateString()
            ?? $case->due_on?->toDateString()
            ?? BusinessDate::today()->toDateString(),
        'status' => ConstructionSchedule::STATUS_SCHEDULED,
        'location' => $case->site_name,
        'general_contractor' => $case->company_name,
        'content' => $case->reception_content,
        'assigned_user_ids' => $case->assigned_user_id === null ? [] : [$case->assigned_user_id],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function receptionBusinessSchedulePayload(ReceptionCase $case, array $overrides = []): array
{
    return [
        'reception_case_id' => $case->id,
        'scheduled_on' => $case->scheduled_on?->toDateString()
            ?? $case->due_on?->toDateString()
            ?? BusinessDate::today()->toDateString(),
        'location' => $case->site_name,
        'general_contractor' => $case->company_name,
        'content' => $case->documentType?->name,
        'memo' => $case->reception_content,
        'assigned_user_ids' => $case->assigned_user_id === null ? [] : [$case->assigned_user_id],
        ...$overrides,
    ];
}

test('schedule create forms are prefilled from an active reception case', function (): void {
    $editor = User::factory()->editor()->create();
    $assignedUser = User::factory()->create();
    $documentType = ReceptionDocumentType::factory()->create(['name' => '見積依頼']);
    $scheduledOn = BusinessDate::today()->addDays(4)->toDateString();
    $returnTo = '/reception/cases/88';
    $case = ReceptionCase::factory()->received()->create([
        'reception_document_type_id' => $documentType->id,
        'assigned_user_id' => $assignedUser->id,
        'company_name' => '和城建設株式会社',
        'site_name' => '日本橋改修現場',
        'reception_content' => '足場組立の予定を作成してください。',
        'scheduled_on' => $scheduledOn,
        'due_on' => BusinessDate::today()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($editor)
        ->get(route('construction-schedules.create', [
            'reception_case_id' => $case->id,
            'return_to' => $returnTo,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-schedules/form')
            ->where('sourceReceptionCase.id', $case->id)
            ->where('sourceReceptionCase.case_number', $case->case_number)
            ->where('sourceReceptionCase.status', ReceptionCaseStatus::Received->value)
            ->where('initialScheduledOn', $scheduledOn)
            ->where('initialAssignedUserIds', [$assignedUser->id])
            ->where('initialLocation', '日本橋改修現場')
            ->where('initialGeneralContractor', '和城建設株式会社')
            ->where('initialContent', '足場組立の予定を作成してください。')
            ->where('returnTo', $returnTo)
            ->etc()
        );

    $case->update(['scheduled_on' => null]);

    $this->get(route('business-schedules.create', [
        'reception_case_id' => $case->id,
        'return_to' => route('reception.cases.show', $case, false),
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('business-schedules/form')
            ->where('sourceReceptionCase.id', $case->id)
            ->where('initialScheduledOn', $case->due_on?->toDateString())
            ->where('initialAssignedUserIds', [$assignedUser->id])
            ->where('initialLocation', '日本橋改修現場')
            ->where('initialGeneralContractor', '和城建設株式会社')
            ->where('initialContent', '見積依頼')
            ->where('initialMemo', '足場組立の予定を作成してください。')
            ->etc()
        );
});

test('the business schedule form survives a reception case without a document type', function (): void {
    $editor = User::factory()->editor()->create();
    $case = ReceptionCase::factory()->received()->create([
        'reception_document_type_id' => null,
    ]);

    $this->actingAs($editor)
        ->get(route('business-schedules.create', ['reception_case_id' => $case->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase.id', $case->id)
            ->where('initialContent', null)
            ->where('initialMemo', $case->reception_content)
            ->etc()
        );
});

test('direct schedule creation remains available while drafts and viewers cannot create from reception', function (): void {
    $editor = User::factory()->editor()->create();
    $viewer = User::factory()->create();
    $draft = ReceptionCase::factory()->create(['receptor_user_id' => $editor->id]);
    $received = ReceptionCase::factory()->received()->create();
    $completed = ReceptionCase::factory()->completed($editor)->create([
        'due_on' => null,
        'scheduled_on' => null,
    ]);

    $this->actingAs($editor)
        ->get(route('construction-schedules.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase', null)
            ->etc()
        );

    $this->get(route('business-schedules.create', ['reception_case_id' => $completed->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase.status', ReceptionCaseStatus::Completed->value)
            ->where('initialScheduledOn', BusinessDate::today()->toDateString())
            ->etc()
        );

    $this->get(route('construction-schedules.create', ['reception_case_id' => $draft->id]))
        ->assertForbidden();

    $this->post(
        route('business-schedules.store'),
        receptionBusinessSchedulePayload($draft),
    )
        ->assertSessionHasErrors('reception_case_id');

    expect(BusinessSchedule::query()->count())->toBe(0);

    $this->actingAs($viewer)
        ->get(route('business-schedules.create', ['reception_case_id' => $received->id]))
        ->assertForbidden();

    $this->post(
        route('construction-schedules.store'),
        receptionConstructionSchedulePayload($received),
    )->assertForbidden();
});

test('creating a construction schedule links and audits it without changing reception status', function (): void {
    $editor = User::factory()->editor()->create();
    $assignedUser = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'assigned_user_id' => $assignedUser->id,
        'company_name' => '受付元会社',
        'site_name' => '受付元現場',
        'reception_content' => '受付時点の工事内容',
        'scheduled_on' => BusinessDate::today()->addDays(3)->toDateString(),
    ]);
    $returnTo = route('reception.cases.show', $case, false);

    $this->actingAs($editor)
        ->post(
            route('construction-schedules.store', ['return_to' => $returnTo]),
            receptionConstructionSchedulePayload($case),
        )
        ->assertRedirect($returnTo)
        ->assertSessionHasNoErrors();

    $schedule = ConstructionSchedule::query()->sole();
    $activity = $case->activities()
        ->where('type', ReceptionCaseActivityType::ScheduleCreated->value)
        ->sole();
    $auditLog = AuditLog::query()
        ->where('event', 'construction_schedules.created')
        ->sole();

    expect($schedule->reception_case_id)->toBe($case->id)
        ->and($schedule->receptionCase->is($case))->toBeTrue()
        ->and($schedule->assignedUsers()->whereKey($assignedUser)->exists())->toBeTrue()
        ->and($case->refresh()->status)->toBe(ReceptionCaseStatus::Received)
        ->and($activity->user_id)->toBe($editor->id)
        ->and($activity->memo)->toContain('工事予定「受付元現場」を作成しました。')
        // Creating a schedule is not a status transition, so the timeline entry
        // must not render a from → to arrow.
        ->and($activity->from_status)->toBeNull()
        ->and($activity->to_status)->toBeNull()
        ->and($auditLog->metadata['reception_case_id'])->toBe($case->id);

    $this->get(route('construction-schedules.show', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase.id', $case->id)
            ->where('sourceReceptionCase.case_number', $case->case_number)
            ->etc()
        );

    $this->get(route('construction-schedules.edit', $schedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase.id', $case->id)
            ->etc()
        );

    $case->update([
        'company_name' => '後から変更した会社',
        'site_name' => '後から変更した現場',
        'reception_content' => '後から変更した内容',
    ]);

    expect($schedule->refresh()->general_contractor)->toBe('受付元会社')
        ->and($schedule->location)->toBe('受付元現場')
        ->and($schedule->content)->toBe('受付時点の工事内容');
});

test('reception detail presents every linked schedule with role appropriate actions', function (): void {
    $editor = User::factory()->editor()->create();
    $viewer = User::factory()->create();
    $case = ReceptionCase::factory()->received()->create([
        'site_name' => '複数予定の受付現場',
        'scheduled_on' => BusinessDate::today()->addDays(2)->toDateString(),
    ]);
    $constructionSchedule = ConstructionSchedule::factory()->create([
        'reception_case_id' => $case->id,
        'scheduled_on' => BusinessDate::today()->addDays(2)->toDateString(),
        'location' => '受付から作成した工事',
    ]);

    $this->actingAs($editor)
        ->post(
            route('business-schedules.store'),
            receptionBusinessSchedulePayload($case, [
                'scheduled_on' => BusinessDate::today()->addDays(3)->toDateString(),
                'location' => '受付から作成した業務',
            ]),
        )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $businessSchedule = BusinessSchedule::query()->sole();

    expect($case->constructionSchedules()->count())->toBe(1)
        ->and($case->businessSchedules()->count())->toBe(1)
        ->and($businessSchedule->reception_case_id)->toBe($case->id);

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.can.create_schedule', true)
            ->where('canManageSchedules', true)
            ->has('linkedSchedules', 2)
            // Newest scheduled_on first: the business schedule is a day later.
            ->where('linkedSchedules.0.id', $businessSchedule->id)
            ->where('linkedSchedules.0.type', 'business')
            ->where('linkedSchedules.0.title', '受付から作成した業務')
            ->where('linkedSchedules.1.id', $constructionSchedule->id)
            ->where('linkedSchedules.1.type', 'construction')
            ->where('linkedSchedules.1.title', '受付から作成した工事')
            ->etc()
        );

    $this->actingAs($viewer)
        ->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.can.create_schedule', false)
            ->where('canManageSchedules', false)
            ->has('linkedSchedules', 2)
            ->etc()
        );

    $this->actingAs($editor)
        ->get(route('business-schedules.show', $businessSchedule))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('sourceReceptionCase.id', $case->id)
            ->etc()
        );
});

test('a linked schedule source is immutable during schedule editing', function (): void {
    $editor = User::factory()->editor()->create();
    $originalCase = ReceptionCase::factory()->received()->create();
    $otherCase = ReceptionCase::factory()->received()->create();
    $schedule = BusinessSchedule::factory()->create([
        'reception_case_id' => $originalCase->id,
    ]);

    $this->actingAs($editor)
        ->put(route('business-schedules.update', $schedule), [
            ...receptionBusinessSchedulePayload($otherCase),
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'location' => '編集後の業務予定',
        ])
        ->assertRedirect(route('business-schedules.show', $schedule))
        ->assertSessionHasNoErrors();

    expect($schedule->refresh()->reception_case_id)->toBe($originalCase->id)
        ->and($schedule->location)->toBe('編集後の業務予定');
});

test('deleting a reception case preserves schedules and clears their source link', function (): void {
    $case = ReceptionCase::factory()->received()->create();
    $constructionSchedule = ConstructionSchedule::factory()->create([
        'reception_case_id' => $case->id,
    ]);
    $businessSchedule = BusinessSchedule::factory()->create([
        'reception_case_id' => $case->id,
    ]);

    $case->delete();

    expect($constructionSchedule->refresh()->reception_case_id)->toBeNull()
        ->and($businessSchedule->refresh()->reception_case_id)->toBeNull();
});
