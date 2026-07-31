<?php

use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Tokyo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('admins can create a trimmed memo without changing inventory', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('7.000')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => '  発注先へ納期を確認  ',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', 'メモを保存しました。')
        ->assertInertiaFlash('toast.resource.type', 'stock_term_memo')
        ->assertInertiaFlash('toast.resource.id', "{$stock->id}-2026-06-21")
        ->assertInertiaFlash('toast.resource.action', 'saved');

    $purchase = $stock->purchases()->sole();

    expect($purchase->quantity)->toBe('0.000')
        ->and($purchase->memo)->toBe('発注先へ納期を確認')
        ->and($stock->refresh()->current_quantity)->toBe('7.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('admins can update and clear a term memo without changing its purchase total', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '5',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => '初回メモ',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => '  更新後メモ  ',
        ])
        ->assertRedirect();

    expect($stock->purchases()->sole()->memo)->toBe('更新後メモ');

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => " \n\t ",
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->purchases()->sole()->memo)->toBeNull()
        ->and($stock->purchases()->sole()->quantity)->toBe('5.000')
        ->and($stock->refresh()->current_quantity)->toBe('5.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->count())->toBe(1);
});

test('memo and addition saves preserve each other in either order', function (): void {
    $admin = User::factory()->admin()->create();
    $memoFirst = Stock::factory()->named('メモ先行')->create();
    $additionFirst = Stock::factory()->named('仕入先行')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $memoFirst), [
            'term_starts_on' => '2026-06-21',
            'memo' => 'メモを先に保存',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $memoFirst), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '4',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $additionFirst), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '6',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $additionFirst), [
            'term_starts_on' => '2026-06-21',
            'memo' => '仕入を先に保存',
        ])
        ->assertRedirect();

    expect($memoFirst->purchases()->sole()->quantity)->toBe('4.000')
        ->and($memoFirst->purchases()->sole()->memo)->toBe('メモを先に保存')
        ->and($additionFirst->purchases()->sole()->quantity)->toBe('6.000')
        ->and($additionFirst->purchases()->sole()->memo)->toBe('仕入を先に保存');
});

test('memos are isolated by stock and term', function (): void {
    $admin = User::factory()->admin()->create();
    $cement = Stock::factory()->named('セメント')->create();
    $thinner = Stock::factory()->named('シンナー')->create();

    foreach ([
        [$cement, '2026-06-21', 'セメント6月'],
        [$cement, '2026-07-21', 'セメント7月'],
        [$thinner, '2026-06-21', 'シンナー6月'],
    ] as [$stock, $termStartsOn, $memo]) {
        $this->actingAs($admin)
            ->put(route('admin.stocks.term-memo.update', $stock), [
                'term_starts_on' => $termStartsOn,
                'memo' => $memo,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($cement->purchases()->whereDate('term_starts_on', '2026-06-21')->sole()->memo)->toBe('セメント6月')
        ->and($cement->purchases()->whereDate('term_starts_on', '2026-07-21')->sole()->memo)->toBe('セメント7月')
        ->and($thinner->purchases()->whereDate('term_starts_on', '2026-06-21')->sole()->memo)->toBe('シンナー6月')
        ->and($thinner->purchases()->count())->toBe(1);
});

test('inactive stocks still allow memo updates', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->inactive()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => '廃番在庫の確認事項',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->purchases()->sole()->memo)->toBe('廃番在庫の確認事項')
        ->and($stock->refresh()->current_quantity)->toBe('0.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('memo values must be strings', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'memo' => ['not', 'a', 'string'],
        ])
        ->assertSessionHasErrors('memo');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('admins can create update and clear a future term memo without changing inventory', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->quantity('7.000')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-08-21',
            'memo' => '  将来月度の初回メモ  ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->purchases()->sole()->term_starts_on->toDateString())->toBe('2026-08-21')
        ->and($stock->purchases()->sole()->memo)->toBe('将来月度の初回メモ');

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-08-21',
            'memo' => '将来月度の更新メモ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->purchases()->sole()->memo)->toBe('将来月度の更新メモ');

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => '2026-08-21',
            'memo' => " \n\t ",
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->purchases()->sole()->quantity)->toBe('0.000')
        ->and($stock->purchases()->sole()->memo)->toBeNull()
        ->and($stock->refresh()->current_quantity)->toBe('7.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('memo terms must start on the 21st', function (string $termStartsOn): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.term-memo.update', $stock), [
            'term_starts_on' => $termStartsOn,
            'memo' => '保存されないメモ',
        ])
        ->assertSessionHasErrors('term_starts_on');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
})->with([
    'historical non-boundary' => '2026-06-20',
    'future non-boundary' => '2026-08-20',
]);
