<?php

use App\Models\Stock;
use App\Models\StockAlias;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the stock management page', function (): void {
    $admin = User::factory()->admin()->create();
    Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/stocks/index')
            ->has('terms', 2)
            ->has('terms.0.buckets', 3)
            ->has('terms.0.rows', 1)
            ->where('terms.0.rows.0.name', 'セメント')
            ->has('stockOrder', 1)
            ->where('stockOrder.0.name', 'セメント')
        );
});

test('non admins cannot access stock management', function (string $role): void {
    $user = $role === 'editor'
        ? User::factory()->editor()->create()
        : User::factory()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($user)->get(route('admin.stocks.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.stocks.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.stocks.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.stocks.update', $stock), [])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.stocks.destroy', $stock))->assertForbidden();
    $this->actingAs($user)->post(route('admin.stocks.purchases.store', $stock), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.stocks.purchase-corrections.store', $stock), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.stocks.term-memo.update', $stock), [])->assertForbidden();
    $this->actingAs($user)->patch(route('admin.stocks.order.update'), ['ordered_ids' => [$stock->id]])->assertForbidden();
})->with(['editor', 'viewer']);

test('guests are redirected from stock purchase and memo writes', function (): void {
    $stock = Stock::factory()->create();

    $this->post(route('admin.stocks.purchases.store', $stock), [])
        ->assertRedirect(route('login'));
    $this->post(route('admin.stocks.purchase-corrections.store', $stock), [])
        ->assertRedirect(route('login'));
    $this->put(route('admin.stocks.term-memo.update', $stock), [])
        ->assertRedirect(route('login'));
    $this->patch(route('admin.stocks.order.update'), ['ordered_ids' => [$stock->id]])
        ->assertRedirect(route('login'));
});

test('admins can reorder every stock and reports use the saved order', function (): void {
    $admin = User::factory()->admin()->create();
    $first = Stock::factory()->named('一番')->create(['sort_order' => 10]);
    $second = Stock::factory()->named('二番')->create(['sort_order' => 20]);
    $third = Stock::factory()->named('三番')->create(['sort_order' => 30]);
    $inactive = Stock::factory()->named('無効在庫')->inactive()->create(['sort_order' => 40]);

    $this->actingAs($admin)
        ->from(route('admin.stocks.index'))
        ->patch(route('admin.stocks.order.update'), [
            'ordered_ids' => [$third->id, $inactive->id, $first->id, $second->id],
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', '在庫の表示順を保存しました。');

    expect($third->refresh()->sort_order)->toBe(10)
        ->and($inactive->refresh()->sort_order)->toBe(20)
        ->and($first->refresh()->sort_order)->toBe(30)
        ->and($second->refresh()->sort_order)->toBe(40);

    $this->actingAs($admin)
        ->get(route('admin.stocks.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('stockOrder.0.id', $third->id)
            ->where('stockOrder.1.id', $inactive->id)
            ->where('stockOrder.2.id', $first->id)
            ->where('stockOrder.3.id', $second->id)
            ->where('terms.0.rows.0.stock_id', $third->id)
            ->where('terms.0.rows.1.stock_id', $first->id)
            ->where('terms.0.rows.2.stock_id', $second->id)
            ->where('terms.1.rows.0.stock_id', $third->id)
            ->where('terms.1.rows.1.stock_id', $first->id)
            ->where('terms.1.rows.2.stock_id', $second->id));
});

test('stock reordering rejects incomplete duplicate and unknown id sets', function (array $orderedIds): void {
    $admin = User::factory()->admin()->create();
    $first = Stock::factory()->named('一番')->create(['sort_order' => 10]);
    $second = Stock::factory()->named('二番')->create(['sort_order' => 20]);

    $resolvedIds = array_map(
        fn (string $id): int => match ($id) {
            'first' => $first->id,
            'second' => $second->id,
            default => 999999,
        },
        $orderedIds,
    );

    $this->actingAs($admin)
        ->from(route('admin.stocks.index'))
        ->patch(route('admin.stocks.order.update'), ['ordered_ids' => $resolvedIds])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertSessionHasErrors('ordered_ids');

    expect($first->refresh()->sort_order)->toBe(10)
        ->and($second->refresh()->sort_order)->toBe(20);
})->with([
    'missing stock' => [['first']],
    'duplicate stock' => [['first', 'first']],
    'unknown stock' => [['first', 'unknown']],
]);

test('admins can create stocks with aliases', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [
            'name' => 'ボンド',
            'sku' => 'BD-001',
            'allows_fractional_quantity' => false,
            'aliases' => ['接着剤', 'ＢＯＮＤ'],
            'initial_quantity' => null,
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '在庫を追加しました。')
        ->assertInertiaFlash('toast.resource.type', 'stock')
        ->assertInertiaFlash('toast.resource.action', 'created')
        ->assertInertiaFlash('toast.resource.label', 'ボンド');

    $stock = Stock::query()->where('name', 'ボンド')->firstOrFail();

    expect($stock->normalized_name)->toBe('ボンド')
        ->and($stock->sku)->toBe('BD-001')
        ->and($stock->is_active)->toBeTrue()
        ->and($stock->current_quantity)->toBe('0.000');

    $this->assertDatabaseHas('stock_aliases', [
        'stock_id' => $stock->id,
        'alias' => '接着剤',
        'normalized_alias' => '接着剤',
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('stock_aliases', [
        'stock_id' => $stock->id,
        'alias' => 'ＢＯＮＤ',
        'normalized_alias' => 'bond',
    ]);
});

test('new stocks are appended after the current display order', function (): void {
    $admin = User::factory()->admin()->create();
    Stock::factory()->named('既存在庫')->create(['sort_order' => 40]);

    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [
            'name' => '追加在庫',
            'sku' => null,
            'allows_fractional_quantity' => false,
            'aliases' => [],
            'initial_quantity' => null,
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertSessionHasNoErrors();

    expect(Stock::query()->where('name', '追加在庫')->value('sort_order'))->toBe(50);
});

test('creating a stock with an initial quantity records a purchase in the current term', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00', 'Asia/Tokyo'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [
            'name' => 'セメント',
            'sku' => null,
            'allows_fractional_quantity' => false,
            'aliases' => [],
            'initial_quantity' => '10',
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertSessionHasNoErrors();

    $stock = Stock::query()->where('name', 'セメント')->firstOrFail();

    expect($stock->current_quantity)->toBe('10.000');

    $this->assertDatabaseHas('stock_purchases', [
        'stock_id' => $stock->id,
        'term_starts_on' => '2026-06-21 00:00:00',
        'quantity' => '10.000',
    ]);
    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $stock->id,
        'transaction_type' => 'stock_in',
        'quantity_delta' => '10.000',
        'balance_after' => '10.000',
        'source_type' => 'stock_purchase',
        'stock_name_snapshot' => 'セメント',
        'description' => '初期在庫',
        'created_by' => $admin->id,
    ]);

    Carbon::setTestNow();
});

test('fractional initial quantity is rejected for integer-only stocks', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [
            'name' => 'セメント',
            'sku' => null,
            'allows_fractional_quantity' => false,
            'aliases' => [],
            'initial_quantity' => '1.5',
        ])
        ->assertSessionHasErrors('initial_quantity');

    expect(Stock::query()->count())->toBe(0);
});

test('stock names and aliases must be unique across the catalog by normalized value', function (): void {
    $admin = User::factory()->admin()->create();
    $existing = Stock::factory()->named('ボンド')->create();
    StockAlias::factory()->for($existing)->named('セメダイン')->create();

    $basePayload = [
        'sku' => null,
        'allows_fractional_quantity' => false,
        'aliases' => [],
        'initial_quantity' => null,
    ];

    // Half-width katakana normalizes to the existing stock name.
    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [...$basePayload, 'name' => 'ﾎﾞﾝﾄﾞ'])
        ->assertSessionHasErrors('name');

    // Conflicts with another stock's alias.
    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [...$basePayload, 'name' => 'セメダイン'])
        ->assertSessionHasErrors('name');

    // Alias conflicts with another stock's name.
    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [...$basePayload, 'name' => '新しい在庫', 'aliases' => ['ボンド']])
        ->assertSessionHasErrors('aliases.0');

    // Duplicates within the same request.
    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [...$basePayload, 'name' => '瞬間接着剤', 'aliases' => ['アロン', 'ｱﾛﾝ']])
        ->assertSessionHasErrors('aliases.1');

    expect(Stock::query()->count())->toBe(1);
});

test('admins can update stocks and sync aliases', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('ボンド')->create();
    $kept = StockAlias::factory()->for($stock)->named('接着剤')->create();
    $removed = StockAlias::factory()->for($stock)->named('セメダイン')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.update', $stock), [
            'name' => '木工用ボンド',
            'sku' => 'BD-002',
            'allows_fractional_quantity' => true,
            'is_active' => false,
            'aliases' => ['接着剤', 'グルー'],
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertInertiaFlash('toast.resource.type', 'stock')
        ->assertInertiaFlash('toast.resource.action', 'updated');

    $stock->refresh();

    expect($stock->name)->toBe('木工用ボンド')
        ->and($stock->normalized_name)->toBe('木工用ボンド')
        ->and($stock->sku)->toBe('BD-002')
        ->and($stock->allows_fractional_quantity)->toBeTrue()
        ->and($stock->is_active)->toBeFalse();

    expect(StockAlias::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(StockAlias::query()->whereKey($removed->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('stock_aliases', [
        'stock_id' => $stock->id,
        'alias' => 'グルー',
        'normalized_alias' => 'グルー',
    ]);
    expect($stock->aliases()->count())->toBe(2);
});

test('updating keeps the stock own name and aliases valid', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('ボンド')->create();
    StockAlias::factory()->for($stock)->named('接着剤')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.update', $stock), [
            'name' => 'ボンド',
            'sku' => null,
            'allows_fractional_quantity' => false,
            'is_active' => true,
            'aliases' => ['接着剤'],
        ])
        ->assertRedirect(route('admin.stocks.index'))
        ->assertSessionHasNoErrors();
});

test('stocks with history cannot be deleted', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00', 'Asia/Tokyo'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.store'), [
            'name' => 'セメント',
            'sku' => null,
            'allows_fractional_quantity' => false,
            'aliases' => [],
            'initial_quantity' => '5',
        ])
        ->assertRedirect(route('admin.stocks.index'));

    $stock = Stock::query()->where('name', 'セメント')->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.stocks.destroy', $stock))
        ->assertUnprocessable();

    expect(Stock::query()->whereKey($stock->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('stocks without history can be deleted along with their aliases', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();
    $alias = StockAlias::factory()->for($stock)->named('cement')->create();

    $this->actingAs($admin)
        ->delete(route('admin.stocks.destroy', $stock))
        ->assertRedirect(route('admin.stocks.index'))
        ->assertInertiaFlash('toast.message', '在庫を削除しました。');

    expect(Stock::query()->whereKey($stock->id)->exists())->toBeFalse()
        ->and(StockAlias::query()->whereKey($alias->id)->exists())->toBeFalse();
});
