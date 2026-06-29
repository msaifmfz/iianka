<?php

use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view the schedule search', function (): void {
    $this->get(route('schedule-search.index'))
        ->assertRedirect(route('login'));
});

test('users can search construction and business schedules by location and general contractor', function (): void {
    $user = User::factory()->create();
    $constructionSchedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'schedule_number' => 21,
        'location' => '虎ノ門ヒルズ 現場',
        'general_contractor' => '清水建設',
        'content' => '配線工事',
    ]);
    $businessSchedule = BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-14',
        'schedule_number' => 34,
        'location' => '虎ノ門ヒルズ 定例会',
        'general_contractor' => '清水建設',
        'content' => '工程会議',
    ]);
    $differentLocation = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-15',
        'location' => '丸の内ビル 現場',
        'general_contractor' => '清水建設',
    ]);
    $differentContractor = BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-16',
        'location' => '虎ノ門ヒルズ 調整会',
        'general_contractor' => '大成建設',
    ]);

    $response = $this->actingAs($user)
        ->get(route('schedule-search.index', [
            'location' => ' 虎ノ門 ',
            'general_contractor' => '清水',
            'direction' => 'asc',
            'selected_type' => 'construction',
            'selected_id' => $constructionSchedule->id,
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('schedule-search/index')
            ->where('filters.location', '虎ノ門')
            ->where('filters.general_contractor', '清水')
            ->where('filters.direction', 'asc')
            ->where('selected.type', 'construction')
            ->where('selected.id', $constructionSchedule->id)
            ->has('results.data', 2)
        );

    $resultKeys = collect($response->inertiaProps('results.data'))
        ->map(fn (array $result): string => "{$result['type']}-{$result['id']}");

    expect($resultKeys->all())
        ->toContain("construction-{$constructionSchedule->id}")
        ->toContain("business-{$businessSchedule->id}")
        ->not->toContain("construction-{$differentLocation->id}")
        ->not->toContain("business-{$differentContractor->id}");
});

test('schedule search defaults to recent schedules and can sort oldest first', function (): void {
    $user = User::factory()->create();

    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-04-01',
        'location' => '古い現場',
        'general_contractor' => 'A建設',
    ]);
    BusinessSchedule::factory()->create([
        'scheduled_on' => '2026-05-15',
        'location' => '中間現場',
        'general_contractor' => 'B建設',
    ]);
    ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-06-20',
        'location' => '新しい現場',
        'general_contractor' => 'C建設',
    ]);

    $descendingResponse = $this->actingAs($user)
        ->get(route('schedule-search.index'));

    $descendingResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.direction', 'desc')
        );

    expect(collect($descendingResponse->inertiaProps('results.data'))->pluck('scheduled_on')->all())
        ->toBe([
            '2026-06-20',
            '2026-05-15',
            '2026-04-01',
        ]);

    $ascendingResponse = $this->actingAs($user)
        ->get(route('schedule-search.index', ['direction' => 'asc']));

    expect(collect($ascendingResponse->inertiaProps('results.data'))->pluck('scheduled_on')->all())
        ->toBe([
            '2026-04-01',
            '2026-05-15',
            '2026-06-20',
        ]);
});

test('schedule search hides assignees that are hidden from workers', function (): void {
    $user = User::factory()->create();
    $visibleAssignee = User::factory()->create(['name' => '表示担当者']);
    $hiddenAssignee = User::factory()->hiddenFromWorkers()->create(['name' => '非表示担当者']);

    $schedule = ConstructionSchedule::factory()->create([
        'scheduled_on' => '2026-05-13',
        'location' => '虎ノ門ヒルズ 現場',
    ]);
    $schedule->assignedUsers()->attach([$visibleAssignee->id, $hiddenAssignee->id]);

    $response = $this->actingAs($user)
        ->get(route('schedule-search.index'))
        ->assertOk();

    $assignedNames = collect($response->inertiaProps('results.data'))
        ->firstWhere('id', $schedule->id)['assigned_users'];

    expect(collect($assignedNames)->pluck('name')->all())
        ->toContain('表示担当者')
        ->not->toContain('非表示担当者');
});

test('schedule search ignores invalid share query values', function (): void {
    $user = User::factory()->create();

    ConstructionSchedule::factory()->create();

    $this->actingAs($user)
        ->get(route('schedule-search.index', [
            'direction' => 'sideways',
            'selected_type' => 'internal_notice',
            'selected_id' => 'abc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.direction', 'desc')
            ->where('selected.type', null)
            ->where('selected.id', null)
        );
});
