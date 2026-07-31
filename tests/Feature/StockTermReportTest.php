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

test('the report aggregates the selected and next terms in display order', function (): void {
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
            ->where('terms.0.previous_term_label', '2026年5月度')
            ->where('terms.1.label', '2026年7月度')
            ->where('terms.1.range_label', '7/21〜8/20')
            ->where('terms.1.rows.0.carry_over', '7.000')
            ->where('terms.1.rows.0.purchased', '3.000')
            ->where('terms.1.rows.0.used', ['4.000', '0.000', '0.000'])
            ->where('terms.1.rows.0.total', '6.000')
            ->where('terms.1.previous_term_label', '2026年6月度')
        );
});

test('the selected and next terms expose their own memo and previous term context', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-05-21')
        ->quantity('2.000')
        ->create(['memo' => '前月度の確認事項']);
    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-06-21')
        ->quantity('3.000')
        ->create(['memo' => '今月度の確認事項']);
    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-07-21')
        ->quantity('4.000')
        ->create(['memo' => '翌月度の確認事項']);

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.term_starts_on', '2026-06-21')
            ->where('terms.0.previous_term_label', '2026年5月度')
            ->where('terms.0.rows.0.memo', '今月度の確認事項')
            ->where('terms.0.rows.0.previous_memo', '前月度の確認事項')
            ->where('terms.1.term_starts_on', '2026-07-21')
            ->where('terms.1.previous_term_label', '2026年6月度')
            ->where('terms.1.rows.0.memo', '翌月度の確認事項')
            ->where('terms.1.rows.0.previous_memo', '今月度の確認事項')
        );
});

test('a negative purchase aggregate carries into the following term report', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '3',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('terms.0.rows.0.purchased', '-3.000')
            ->where('terms.0.rows.0.total', '-3.000')
            ->where('terms.1.rows.0.carry_over', '-3.000')
            ->where('terms.1.rows.0.purchased', '0.000')
            ->where('terms.1.rows.0.total', '-3.000')
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
            ->where('terms.1.rows.0.total', '15.000')
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
            ->where('filters.next_month', '2026-07-01')
            ->where('filters.is_current', true)
            ->where('terms.0.label', '2026年6月度')
            ->where('terms.1.label', '2026年7月度')
        );

    // Explicitly selecting the current term must still report is_current. This
    // resolves the term through StockTerm::fromMonth() rather than
    // StockTerm::current(), so it is the only assertion that pins the business
    // timezone used when parsing the month filter.
    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => '2026-06-01']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-06-01')
            ->where('filters.is_current', true)
            ->where('terms.0.label', '2026年6月度')
        );

    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => '2026-07-01']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-07-01')
            ->where('filters.next_month', '2026-08-01')
            ->where('filters.is_current', false)
            ->where('terms.0.label', '2026年7月度')
            ->where('terms.1.label', '2026年8月度')
        );

    freezeStockReportTime('2026-07-21 10:00:00');

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-07-01')
            ->where('filters.next_month', '2026-08-01')
            ->where('filters.is_current', true)
            ->where('terms.0.label', '2026年7月度')
            ->where('terms.1.label', '2026年8月度')
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
            ->where('filters.next_month', '2026-07-01')
            ->where('terms.0.label', '2026年6月度')
            ->where('terms.1.label', '2026年7月度')
        );
});

test('future month filters remain selected with an editable following term', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('将来在庫')->create();

    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-11-21')
        ->quantity('2.000')
        ->create(['memo' => '11月度の引継ぎ']);
    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-12-21')
        ->quantity('5.000')
        ->create(['memo' => '12月度の予定']);
    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2027-01-21')
        ->quantity('3.000')
        ->create(['memo' => '1月度の予定']);

    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => '2026-12-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-12-01')
            ->where('filters.previous_month', '2026-11-01')
            ->where('filters.next_month', '2027-01-01')
            ->where('filters.is_current', false)
            ->where('terms.0.label', '2026年12月度')
            ->where('terms.0.previous_term_label', '2026年11月度')
            ->where('terms.0.rows.0.carry_over', '2.000')
            ->where('terms.0.rows.0.purchased', '5.000')
            ->where('terms.0.rows.0.total', '7.000')
            ->where('terms.0.rows.0.memo', '12月度の予定')
            ->where('terms.0.rows.0.previous_memo', '11月度の引継ぎ')
            ->where('terms.1.label', '2027年1月度')
            ->where('terms.1.previous_term_label', '2026年12月度')
            ->where('terms.1.rows.0.carry_over', '7.000')
            ->where('terms.1.rows.0.purchased', '3.000')
            ->where('terms.1.rows.0.total', '10.000')
            ->where('terms.1.rows.0.memo', '1月度の予定')
            ->where('terms.1.rows.0.previous_memo', '12月度の予定')
        );
});

test('current direct future and following terms cross calendar years correctly', function (): void {
    $admin = User::factory()->admin()->create();
    Stock::factory()->create();

    freezeStockReportTime('2027-01-20 10:00:00');

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2026-12-01')
            ->where('filters.next_month', '2027-01-01')
            ->where('filters.is_current', true)
            ->where('terms.0.label', '2026年12月度')
            ->where('terms.1.label', '2027年1月度')
        );

    $this->actingAs($admin)
        ->get(route('admin.stocks.index', ['month' => '2027-01-01']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2027-01-01')
            ->where('filters.previous_month', '2026-12-01')
            ->where('filters.next_month', '2027-02-01')
            ->where('filters.is_current', false)
            ->where('terms.0.label', '2027年1月度')
            ->where('terms.1.label', '2027年2月度')
        );

    freezeStockReportTime('2027-01-21 10:00:00');

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('filters.month', '2027-01-01')
            ->where('filters.next_month', '2027-02-01')
            ->where('filters.is_current', true)
            ->where('terms.0.label', '2027年1月度')
            ->where('terms.1.label', '2027年2月度')
        );
});

test('inactive stocks are hidden unless they have figures or a memo in the visible terms', function (): void {
    freezeStockReportTime();

    $admin = User::factory()->admin()->create();
    $withFigures = Stock::factory()->named('旧セメント')->inactive()->create();
    $withMemo = Stock::factory()->named('旧メモ在庫')->inactive()->create();
    Stock::factory()->named('旧ネジ')->inactive()->create();
    Stock::factory()->named('現行ボンド')->create();

    StockPurchase::factory()->for($withFigures)->forTerm('2026-06-21')->quantity('5.000')->create();
    StockPurchase::factory()
        ->for($withMemo)
        ->forTerm('2026-06-21')
        ->create(['memo' => '数量なしの引継ぎ']);

    $response = $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('terms.0.rows', 3)
            ->where('terms.0.rows.0.name', '現行ボンド')
            ->where('terms.0.rows.1.name', '旧セメント')
            ->where('terms.0.rows.1.purchased', '5.000')
        );

    $rows = collect($response->inertiaProps('terms.0.rows'))->keyBy('name');

    expect($rows)->toHaveCount(3)
        ->and($rows->has('旧ネジ'))->toBeFalse()
        ->and($rows->get('旧メモ在庫')['purchased'])->toBe('0.000')
        ->and($rows->get('旧メモ在庫')['memo'])->toBe('数量なしの引継ぎ');
});
