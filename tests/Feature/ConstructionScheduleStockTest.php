<?php

declare(strict_types=1);

use App\Domain\Stock\Enums\StockExtractionStatus;
use App\Models\ConstructionSchedule;
use App\Models\ScheduleContentRevision;
use App\Models\ScheduleStockBalance;
use App\Models\ScheduleStockMention;
use App\Models\Stock;
use App\Models\StockAlias;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\BusinessDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function constructionStockPayload(?string $content, ?int $contentVersion = null): array
{
    return [
        'scheduled_on' => BusinessDate::today()->toDateString(),
        'status' => ConstructionSchedule::STATUS_SCHEDULED,
        'location' => '在庫テスト現場',
        'content' => $content,
        'content_version' => $contentVersion,
    ];
}

function stockTestSchedule(): ConstructionSchedule
{
    return ConstructionSchedule::query()->where('location', '在庫テスト現場')->firstOrFail();
}

test('storing a schedule extracts stock usage and updates inventory', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('牛乳')->quantity('20.000')->create();
    $powder = Stock::factory()->named('Milk Powder')->quantity('7.000')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload("牛乳 2\nMilk Powder 3"))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = stockTestSchedule();

    expect($schedule->content)->toBe("牛乳 2\nMilk Powder 3")
        ->and($schedule->content_hash)->toBe(hash('sha256', "牛乳 2\nMilk Powder 3"))
        ->and($schedule->content_version)->toBe(2)
        ->and($schedule->stock_extraction_status)->toBe(StockExtractionStatus::Processed)
        ->and($schedule->stock_extracted_at)->not->toBeNull();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '18.000']);
    $this->assertDatabaseHas('stocks', ['id' => $powder->id, 'current_quantity' => '4.000']);

    $this->assertDatabaseHas('schedule_content_revisions', [
        'schedule_type' => 'construction_schedule',
        'schedule_id' => $schedule->id,
        'content_hash' => $schedule->content_hash,
        'parser_version' => '1.0.0',
        'created_by' => $editor->id,
    ]);

    $this->assertDatabaseHas('schedule_stock_mentions', [
        'schedule_id' => $schedule->id,
        'stock_id' => $milk->id,
        'stock_name_snapshot' => '牛乳',
        'matched_text' => '牛乳',
        'quantity' => '2.000',
        'identification_method' => 'current_name',
        'status' => 'recognized',
    ]);

    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $milk->id,
        'transaction_type' => 'schedule_stock_out',
        'quantity_delta' => '-2.000',
        'balance_after' => '18.000',
        'source_type' => 'construction_schedule',
        'source_id' => $schedule->id,
        'stock_name_snapshot' => '牛乳',
        'created_by' => $editor->id,
    ]);

    $this->assertDatabaseHas('schedule_stock_balances', [
        'schedule_type' => 'construction_schedule',
        'schedule_id' => $schedule->id,
        'stock_id' => $powder->id,
        'applied_quantity' => '3.000',
    ]);
});

test('storing a schedule without stock mentions records nothing', function (): void {
    User::factory()->editor()->create();
    Stock::factory()->named('牛乳')->quantity('20.000')->create();

    $this->actingAs(User::query()->firstOrFail())
        ->post(route('construction-schedules.store'), constructionStockPayload('通常の作業内容のみ'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = stockTestSchedule();

    expect($schedule->stock_extraction_status)->toBe(StockExtractionStatus::Processed)
        ->and(ScheduleContentRevision::query()->count())->toBe(0)
        ->and(ScheduleStockMention::query()->count())->toBe(0)
        ->and(StockTransaction::query()->count())->toBe(0)
        ->and(ScheduleStockBalance::query()->count())->toBe(0);
});

test('increasing a quantity deducts only the difference', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('Milk 3', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '17.000']);
    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $milk->id,
        'transaction_type' => 'schedule_stock_out',
        'quantity_delta' => '-1.000',
        'balance_after' => '17.000',
    ]);
    $this->assertDatabaseHas('schedule_stock_balances', [
        'schedule_id' => $schedule->id,
        'stock_id' => $milk->id,
        'applied_quantity' => '3.000',
    ]);

    expect($schedule->refresh()->content_version)->toBe(3);
});

test('decreasing a quantity restores the difference', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 3'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('Milk 1', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '19.000']);
    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $milk->id,
        'transaction_type' => 'schedule_reversal',
        'quantity_delta' => '2.000',
        'balance_after' => '19.000',
    ]);
});

test('removing a mention restores the full quantity and deletes the balance', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 3'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('在庫の記載なし', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '20.000']);
    $this->assertDatabaseMissing('schedule_stock_balances', ['schedule_id' => $schedule->id]);
    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $milk->id,
        'transaction_type' => 'schedule_reversal',
        'quantity_delta' => '3.000',
        'balance_after' => '20.000',
    ]);
});

test('replacing one stock with another reconciles both', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();
    $desk = Stock::factory()->named('Desk')->quantity('15.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('Desk 2', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '20.000']);
    $this->assertDatabaseHas('stocks', ['id' => $desk->id, 'current_quantity' => '13.000']);
    $this->assertDatabaseMissing('schedule_stock_balances', ['stock_id' => $milk->id]);
    $this->assertDatabaseHas('schedule_stock_balances', ['stock_id' => $desk->id, 'applied_quantity' => '2.000']);
});

test('resubmitting identical content creates no duplicate deductions', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('Milk 2', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '18.000']);

    expect(StockTransaction::query()->count())->toBe(1)
        ->and(ScheduleContentRevision::query()->count())->toBe(1)
        ->and($schedule->refresh()->content_version)->toBe(2);
});

test('usage may drive stock negative', function (): void {
    $editor = User::factory()->editor()->create();
    $desk = Stock::factory()->named('Desk')->quantity('3.000')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload('Desk 10'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $desk->id, 'current_quantity' => '-7.000']);
});

test('usage cannot push the stock balance below the ledger range', function (): void {
    $editor = User::factory()->editor()->create();
    $desk = Stock::factory()->named('Desk')->quantity('-999999999.000')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload('Desk 1000'))
        ->assertSessionHasErrors('content');

    expect(ConstructionSchedule::query()->count())->toBe(0);
    $this->assertDatabaseHas('stocks', ['id' => $desk->id, 'current_quantity' => '-999999999.000']);
});

test('deleting a schedule reverses applied usage and keeps the audit trail', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->delete(route('construction-schedules.destroy', $schedule))
        ->assertRedirect();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '20.000']);
    $this->assertDatabaseMissing('construction_schedules', ['id' => $schedule->id]);
    $this->assertDatabaseMissing('schedule_stock_balances', ['schedule_id' => $schedule->id]);

    expect(StockTransaction::query()->count())->toBe(2)
        ->and(ScheduleContentRevision::query()->count())->toBe(1)
        ->and(ScheduleStockMention::query()->count())->toBe(1);

    $this->assertDatabaseHas('stock_transactions', [
        'transaction_type' => 'schedule_reversal',
        'quantity_delta' => '2.000',
        'description' => '予定削除による戻し',
    ]);
});

test('a stale content version rejects the update and rolls everything back', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $payload = constructionStockPayload('Milk 5', 1);
    $payload['location'] = '別の現場名';

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('content');

    $schedule->refresh();

    expect($schedule->location)->toBe('在庫テスト現場')
        ->and($schedule->content)->toBe('Milk 2')
        ->and($schedule->content_version)->toBe(2);

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '18.000']);
    expect(StockTransaction::query()->count())->toBe(1);
});

test('a rejected stock update stores no uploaded guide files', function (): void {
    Storage::fake('local');
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'));
    $schedule = stockTestSchedule();

    $payload = constructionStockPayload('Milk 5', 1);
    $payload['guide_files'] = [UploadedFile::fake()->create('案内図.pdf', 100, 'application/pdf')];
    $payload['guide_file_names'] = ['案内図'];

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('content');

    expect(Storage::disk('local')->allFiles('site-guides'))->toBe([]);
});

test('increasing usage of an inactive stock is rejected', function (): void {
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('旧資材')->quantity('5.000')->inactive()->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload('旧資材 2'))
        ->assertRedirect()
        ->assertSessionHasErrors('content');

    expect(ConstructionSchedule::query()->count())->toBe(0)
        ->and(StockTransaction::query()->count())->toBe(0);
});

test('decreasing usage of an inactive stock is allowed', function (): void {
    $editor = User::factory()->editor()->create();
    $stock = Stock::factory()->named('旧資材')->quantity('10.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('旧資材 5'));
    $schedule = stockTestSchedule();

    $stock->update(['is_active' => false]);

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('旧資材 2', 2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'current_quantity' => '8.000']);

    $this->actingAs($editor)
        ->put(route('construction-schedules.update', $schedule), constructionStockPayload('旧資材 7', 3))
        ->assertRedirect()
        ->assertSessionHasErrors('content');

    $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'current_quantity' => '8.000']);
});

test('fractional quantities follow the stock setting', function (): void {
    $editor = User::factory()->editor()->create();
    $rag = Stock::factory()->named('ウエス')->quantity('12.500')->fractional()->create();
    $desk = Stock::factory()->named('Desk')->quantity('15.000')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload("ウエス 1.5\nDesk 1.5"))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = stockTestSchedule();

    $this->assertDatabaseHas('stocks', ['id' => $rag->id, 'current_quantity' => '11.000']);
    $this->assertDatabaseHas('stocks', ['id' => $desk->id, 'current_quantity' => '15.000']);
    $this->assertDatabaseHas('schedule_stock_mentions', [
        'stock_id' => $desk->id,
        'quantity' => '1.500',
        'status' => 'invalid_quantity',
    ]);

    expect($schedule->stock_extraction_status)->toBe(StockExtractionStatus::ProcessedWithIgnoredText);
});

test('duplicate mentions are aggregated into one balance', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload('朝 Milk 2 夕方 Milk 3'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '15.000']);
    $this->assertDatabaseHas('schedule_stock_balances', ['stock_id' => $milk->id, 'applied_quantity' => '5.000']);

    expect(StockTransaction::query()->count())->toBe(1)
        ->and(ScheduleStockMention::query()->count())->toBe(2);
});

test('aliases resolve to their stock', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('牛乳')->quantity('20.000')->create();
    StockAlias::factory()->named('ミルク')->for($milk)->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), constructionStockPayload('ミルク 2'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stocks', ['id' => $milk->id, 'current_quantity' => '18.000']);
    $this->assertDatabaseHas('schedule_stock_mentions', [
        'stock_id' => $milk->id,
        'stock_name_snapshot' => '牛乳',
        'matched_text' => 'ミルク',
        'identification_method' => 'alias',
        'status' => 'recognized',
    ]);
});

test('viewers cannot create schedules with stock usage', function (): void {
    $viewer = User::factory()->create();
    Stock::factory()->named('Milk')->quantity('20.000')->create();

    $this->actingAs($viewer)
        ->post(route('construction-schedules.store'), constructionStockPayload('Milk 2'))
        ->assertForbidden();

    expect(StockTransaction::query()->count())->toBe(0);
});

test('the schedule form receives active stock options with aliases', function (): void {
    $editor = User::factory()->editor()->create();
    $milk = Stock::factory()->named('牛乳')->quantity('20.000')->create();
    StockAlias::factory()->named('ミルク')->for($milk)->create();
    Stock::factory()->named('旧資材')->inactive()->create();

    $this->actingAs($editor)
        ->get(route('construction-schedules.create'))
        ->assertInertia(fn ($page) => $page
            ->component('construction-schedules/form')
            ->has('stockOptions', 1)
            ->where('stockOptions.0.name', '牛乳')
            ->where('stockOptions.0.aliases.0', 'ミルク')
            ->where('stockOptions.0.available_quantity', '20.000')
            ->where('stockOptions.0.allows_fractional_quantity', false));
});

test('the show page exposes applied stock usage', function (): void {
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('牛乳')->quantity('20.000')->create();

    $this->actingAs($editor)->post(route('construction-schedules.store'), constructionStockPayload('牛乳 2'));
    $schedule = stockTestSchedule();

    $this->actingAs($editor)
        ->get(route('construction-schedules.show', $schedule))
        ->assertInertia(fn ($page) => $page
            ->component('construction-schedules/show')
            ->has('stockUsages', 1)
            ->where('stockUsages.0.name', '牛乳')
            ->where('stockUsages.0.quantity', '2.000')
            ->where('stockUsages.0.is_active', true)
            ->where('schedule.content_version', 2));
});
