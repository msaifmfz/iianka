<?php

use App\Domain\Stock\Enums\StockTransactionType;
use App\Models\ConstructionSchedule;
use App\Models\Stock;
use App\Models\StockPurchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function stockReportSchedulePayload(string $scheduledOn, ?string $content, string $location, ?int $contentVersion = null): array
{
    return [
        'scheduled_on' => $scheduledOn,
        'status' => ConstructionSchedule::STATUS_SCHEDULED,
        'location' => $location,
        'content' => $content,
        'content_version' => $contentVersion,
    ];
}

function freezeStockReportTime(string $datetime = '2026-07-15 10:00:00'): void
{
    Carbon::setTestNow(Carbon::parse($datetime, 'Asia/Tokyo'));
}

test('the report aggregates carry-over, purchases, bucket usage, and totals across both terms', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    $stock = Stock::factory()->named('セメント')->create();

    StockPurchase::factory()->for($stock)->forTerm('2026-05-21')->quantity('10.000')->create();
    StockPurchase::factory()->for($stock)->forTerm('2026-06-21')->quantity('5.000')->create();
    StockPurchase::factory()->for($stock)->forTerm('2026-07-21')->quantity('3.000')->create();

    foreach ([
        ['2026-06-10', 'セメント 2', '前月度の現場'],
        ['2026-06-25', 'セメント 1', '第1区間の現場'],
        ['2026-07-05', 'セメント 2', '第2区間の現場'],
        ['2026-07-15', 'セメント 3', '第3区間の現場'],
        ['2026-07-25', 'セメント 4', '翌月度の現場'],
    ] as [$date, $content, $location]) {
        $this->actingAs($editor)
            ->post(route('construction-schedules.store'), stockReportSchedulePayload($date, $content, $location))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/stocks/index')
            ->where('filters.month', '2026-06-01')
            ->where('filters.is_current', true)
            ->where('today', '2026-07-15')
            ->where('terms.0.label', '2026年6月度')
            ->where('terms.0.range_label', '6/21〜7/20')
            ->where('terms.0.rows.0.name', 'セメント')
            ->where('terms.0.rows.0.carry_over', '8.000')
            ->where('terms.0.rows.0.purchased', '5.000')
            ->where('terms.0.rows.0.used', ['1.000', '2.000', '3.000'])
            ->where('terms.0.rows.0.used_total', '6.000')
            ->where('terms.0.rows.0.adjustments', '0.000')
            ->where('terms.0.rows.0.total', '7.000')
            ->where('terms.1.label', '2026年7月度')
            ->where('terms.1.rows.0.carry_over', '7.000')
            ->where('terms.1.rows.0.purchased', '3.000')
            ->where('terms.1.rows.0.used', ['4.000', '0.000', '0.000'])
            ->where('terms.1.rows.0.total', '6.000')
        );
});

test('usage lands in the correct bucket at every boundary date', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('ネジ')->create();

    foreach ([
        '2026-06-20',
        '2026-06-21',
        '2026-06-30',
        '2026-07-01',
        '2026-07-10',
        '2026-07-11',
        '2026-07-20',
        '2026-07-21',
    ] as $index => $date) {
        $this->actingAs($editor)
            ->post(route('construction-schedules.store'), stockReportSchedulePayload($date, 'ネジ 1', "境界テスト現場{$index}"))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.carry_over', '-1.000')
            ->where('terms.0.rows.0.used', ['2.000', '2.000', '2.000'])
            ->where('terms.0.rows.0.total', '-7.000')
            ->where('terms.1.rows.0.carry_over', '-7.000')
            ->where('terms.1.rows.0.used', ['1.000', '0.000', '0.000'])
            ->where('terms.1.rows.0.total', '-8.000')
        );
});

test('moving a schedule to another term re-attributes its usage', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('セメント')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), stockReportSchedulePayload('2026-06-25', 'セメント 2', '移動テスト現場'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = ConstructionSchedule::query()->where('location', '移動テスト現場')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.used', ['2.000', '0.000', '0.000'])
            ->where('terms.1.rows.0.used', ['0.000', '0.000', '0.000'])
        );

    $this->actingAs($editor)
        ->put(
            route('construction-schedules.update', $schedule),
            stockReportSchedulePayload('2026-07-25', 'セメント 2', '移動テスト現場', $schedule->content_version),
        )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.used', ['0.000', '0.000', '0.000'])
            ->where('terms.1.rows.0.used', ['2.000', '0.000', '0.000'])
        );
});

test('deleting a schedule removes its usage from the report', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    Stock::factory()->named('セメント')->create();

    $this->actingAs($editor)
        ->post(route('construction-schedules.store'), stockReportSchedulePayload('2026-06-25', 'セメント 2', '削除テスト現場'))
        ->assertRedirect();

    $schedule = ConstructionSchedule::query()->where('location', '削除テスト現場')->firstOrFail();

    $this->actingAs($editor)
        ->delete(route('construction-schedules.destroy', $schedule))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.used', ['0.000', '0.000', '0.000'])
            ->where('terms.0.rows.0.total', '0.000')
        );
});

test('manual ledger adjustments are reported separately and keep the carry-over identity', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    StockPurchase::factory()->for($stock)->forTerm('2026-06-21')->quantity('10.000')->create();

    $stock->transactions()->create([
        'transaction_type' => StockTransactionType::ManualIncrease,
        'quantity_delta' => '5.000',
        'balance_after' => '5.000',
        'stock_name_snapshot' => $stock->name,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.purchased', '10.000')
            ->where('terms.0.rows.0.adjustments', '5.000')
            ->where('terms.0.rows.0.total', '15.000')
            ->where('terms.1.rows.0.carry_over', '15.000')
        );
});

test('the default term follows the business date around the 21st boundary', function (): void {
    $admin = User::factory()->admin()->create();
    Stock::factory()->create();

    freezeStockReportTime('2026-07-20 10:00:00');

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-06-01')
            ->where('terms.0.label', '2026年6月度')
        );

    freezeStockReportTime('2026-07-21 10:00:00');

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-07-01')
            ->where('terms.0.label', '2026年7月度')
        );
});

test('the month filter navigates terms and falls back on invalid input', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    Stock::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => '2026-03-01']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-03-01')
            ->where('filters.previous_month', '2026-02-01')
            ->where('filters.next_month', '2026-04-01')
            ->where('filters.is_current', false)
            ->where('terms.0.label', '2026年3月度')
            ->where('terms.1.label', '2026年4月度')
        );

    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => 'garbage']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-06-01')
            ->where('filters.is_current', true)
        );
});

test('inactive stocks are hidden unless they still have figures in the visible terms', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $withFigures = Stock::factory()->named('旧セメント')->inactive()->create();
    Stock::factory()->named('旧ネジ')->inactive()->create();
    Stock::factory()->named('現行ボンド')->create();

    StockPurchase::factory()->for($withFigures)->forTerm('2026-06-21')->quantity('5.000')->create();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('terms.0.rows', 2)
            ->where('terms.0.rows.0.name', '現行ボンド')
            ->where('terms.0.rows.1.name', '旧セメント')
            ->where('terms.0.rows.1.purchased', '5.000')
        );
});
