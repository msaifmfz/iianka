<?php

use App\Models\ReceptionCase;
use App\Models\ReceptionDocumentType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('workflow managers can manage reception document types', function (): void {
    $manager = User::factory()->editor()->create();

    $this->actingAs($manager)
        ->get(route('reception.document-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reception/document-types/index')
        );

    $this->post(route('reception.document-types.store'), [
        'name' => '写真確認',
        'is_active' => true,
    ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.resource.type', 'reception_document_type')
        ->assertInertiaFlash('toast.resource.action', 'created');

    $documentType = ReceptionDocumentType::query()->where('name', '写真確認')->firstOrFail();

    $this->patch(route('reception.document-types.update', $documentType), [
        'name' => '写真確認更新',
        'is_active' => false,
    ])->assertRedirect();

    expect($documentType->refresh()->name)->toBe('写真確認更新')
        ->and($documentType->sort_order)->toBe(10)
        ->and($documentType->is_active)->toBeFalse();
});

test('workflow managers can reorder reception document types', function (): void {
    $manager = User::factory()->editor()->create();
    $first = ReceptionDocumentType::factory()->create([
        'name' => '一番目',
        'sort_order' => 10,
    ]);
    $second = ReceptionDocumentType::factory()->create([
        'name' => '二番目',
        'sort_order' => 20,
    ]);
    $third = ReceptionDocumentType::factory()->create([
        'name' => '三番目',
        'sort_order' => 30,
    ]);

    $this->actingAs($manager)
        ->patch(route('reception.document-types.order.update'), [
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.resource.type', 'reception_document_type')
        ->assertInertiaFlash('toast.resource.action', 'saved');

    expect($third->refresh()->sort_order)->toBe(10)
        ->and($first->refresh()->sort_order)->toBe(20)
        ->and($second->refresh()->sort_order)->toBe(30);

    $this->get(route('reception.document-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('documentTypes.0.id', $third->id)
            ->where('documentTypes.1.id', $first->id)
            ->where('documentTypes.2.id', $second->id)
        );
});

test('non managers cannot manage reception document types', function (): void {
    $user = User::factory()->create();
    $documentTypes = ReceptionDocumentType::factory()
        ->count(2)
        ->sequence(
            ['sort_order' => 10],
            ['sort_order' => 20],
        )
        ->create();

    $this->actingAs($user)
        ->get(route('reception.document-types.index'))
        ->assertForbidden();

    $this->patch(route('reception.document-types.order.update'), [
        'ordered_ids' => $documentTypes->pluck('id')->reverse()->values()->all(),
    ])->assertForbidden();
});

test('document type reorder rejects invalid ordered ids', function (string $case): void {
    $manager = User::factory()->editor()->create();
    $first = ReceptionDocumentType::factory()->create(['sort_order' => 10]);
    $second = ReceptionDocumentType::factory()->create(['sort_order' => 20]);

    $orderedIds = match ($case) {
        'duplicate ids' => [$first->id, $first->id],
        'missing id' => [$first->id],
        'unknown id' => [$first->id, $second->id + 1000],
    };

    $this->actingAs($manager)
        ->from(route('reception.document-types.index'))
        ->patch(route('reception.document-types.order.update'), [
            'ordered_ids' => $orderedIds,
        ])
        ->assertRedirect(route('reception.document-types.index'))
        ->assertSessionHasErrors('ordered_ids');

    expect($first->refresh()->sort_order)->toBe(10)
        ->and($second->refresh()->sort_order)->toBe(20);
})->with([
    'duplicate ids',
    'missing id',
    'unknown id',
]);

test('inactive document types are hidden from new forms but display on existing cases', function (): void {
    $user = User::factory()->create();
    $active = ReceptionDocumentType::factory()->create(['name' => '有効書類']);
    $inactive = ReceptionDocumentType::factory()->inactive()->create(['name' => '無効書類']);
    $case = ReceptionCase::factory()->received()->create([
        'reception_document_type_id' => $inactive->id,
    ]);

    $this->actingAs($user)
        ->get(route('reception.cases.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('documentTypes', 1)
            ->where('documentTypes.0.id', $active->id)
        );

    $this->get(route('reception.cases.show', $case))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('caseData.document_type.id', $inactive->id)
            ->where('caseData.document_type.is_active', false)
            ->where('documentTypes', fn ($documentTypes): bool => collect($documentTypes)->contains(
                fn (array $documentType): bool => $documentType['id'] === $inactive->id
            ))
        );
});
