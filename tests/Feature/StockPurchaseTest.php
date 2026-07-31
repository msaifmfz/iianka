<?php

use App\Models\Stock;
use App\Models\StockPurchase;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Tokyo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('admins can add a purchased quantity to a term', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('4.000')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '仕入数を追加しました。')
        ->assertInertiaFlash('toast.resource.type', 'stock_purchase_cell')
        ->assertInertiaFlash('toast.resource.id', "{$stock->id}-2026-06-21")
        ->assertInertiaFlash('toast.resource.action', 'saved');

    $this->assertDatabaseHas('stock_purchases', [
        'stock_id' => $stock->id,
        'term_starts_on' => '2026-06-21 00:00:00',
        'quantity' => '10.000',
    ]);
    $this->assertDatabaseHas('stock_transactions', [
        'stock_id' => $stock->id,
        'transaction_type' => 'stock_in',
        'quantity_delta' => '10.000',
        'balance_after' => '14.000',
        'source_type' => 'stock_purchase',
        'created_by' => $admin->id,
    ]);

    expect($stock->refresh()->current_quantity)->toBe('14.000');
});

test('repeated identical additions accumulate instead of replacing the term total', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('4.000')->create();

    foreach (range(1, 2) as $_) {
        $this->actingAs($admin)
            ->post(route('admin.stocks.purchases.store', $stock), [
                'term_starts_on' => '2026-06-21',
                'quantity_to_add' => '10',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($stock->refresh()->current_quantity)->toBe('24.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('20.000');

    $transactions = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['quantity_delta', 'balance_after']);

    expect($transactions->pluck('quantity_delta')->all())->toBe(['10.000', '10.000'])
        ->and($transactions->pluck('balance_after')->all())->toBe(['14.000', '24.000']);
});

test('purchases can be added to separate terms independently', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    foreach ([
        ['2026-06-21', '10'],
        ['2026-07-21', '3'],
    ] as [$termStartsOn, $quantityToAdd]) {
        $this->actingAs($admin)
            ->post(route('admin.stocks.purchases.store', $stock), [
                'term_starts_on' => $termStartsOn,
                'quantity_to_add' => $quantityToAdd,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($stock->purchases()->count())->toBe(2)
        ->and($stock->purchases()->whereDate('term_starts_on', '2026-06-21')->sole()->quantity)->toBe('10.000')
        ->and($stock->purchases()->whereDate('term_starts_on', '2026-07-21')->sole()->quantity)->toBe('3.000')
        ->and($stock->refresh()->current_quantity)->toBe('13.000');
});

test('admins can correct a term by subtracting from its purchased total', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '15',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '3',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '仕入数を訂正しました。')
        ->assertInertiaFlash('toast.resource.type', 'stock_purchase_cell')
        ->assertInertiaFlash('toast.resource.id', "{$stock->id}-2026-06-21")
        ->assertInertiaFlash('toast.resource.action', 'saved');

    expect($stock->refresh()->current_quantity)->toBe('12.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('12.000');

    $transactions = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['transaction_type', 'quantity_delta', 'balance_after']);

    expect($transactions->pluck('quantity_delta')->all())->toBe(['15.000', '-3.000'])
        ->and($transactions->pluck('balance_after')->all())->toBe(['15.000', '12.000'])
        ->and($transactions->last()->transaction_type->value)->toBe('correction');
});

test('a correction can exceed the purchased total for that term', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '11',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->refresh()->current_quantity)->toBe('-1.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('-1.000');

    $transactions = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['quantity_delta', 'balance_after']);

    expect($transactions->pluck('quantity_delta')->all())->toBe(['10.000', '-11.000'])
        ->and($transactions->pluck('balance_after')->all())->toBe(['10.000', '-1.000']);
});

test('correcting a term without a purchase creates a linked negative aggregate', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->quantity('5.000')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '3',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $purchase = $stock->purchases()->sole();
    $transaction = StockTransaction::query()->where('stock_id', $stock->id)->sole();

    expect($stock->refresh()->current_quantity)->toBe('2.000')
        ->and($purchase->quantity)->toBe('-3.000')
        ->and($purchase->memo)->toBeNull()
        ->and($transaction->transaction_type->value)->toBe('correction')
        ->and($transaction->quantity_delta)->toBe('-3.000')
        ->and($transaction->balance_after)->toBe('2.000')
        ->and($transaction->source_id)->toBe($purchase->id)
        ->and($transaction->created_by)->toBe($admin->id);
});

test('corrections can repeat while negative and additions can reduce and reverse the aggregate', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    foreach (['2', '3'] as $quantityToSubtract) {
        $this->actingAs($admin)
            ->post(route('admin.stocks.purchase-corrections.store', $stock), [
                'term_starts_on' => '2026-06-21',
                'quantity_to_subtract' => $quantityToSubtract,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($stock->refresh()->current_quantity)->toBe('-5.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('-5.000');

    foreach (['2', '4'] as $quantityToAdd) {
        $this->actingAs($admin)
            ->post(route('admin.stocks.purchases.store', $stock), [
                'term_starts_on' => '2026-06-21',
                'quantity_to_add' => $quantityToAdd,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    $transactions = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['quantity_delta', 'balance_after']);

    expect($stock->refresh()->current_quantity)->toBe('1.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('1.000')
        ->and($transactions->pluck('quantity_delta')->all())->toBe(['-2.000', '-3.000', '2.000', '4.000'])
        ->and($transactions->pluck('balance_after')->all())->toBe(['-2.000', '-5.000', '-3.000', '1.000']);
});

test('correcting a memo-only purchase preserves its memo', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();
    $purchase = StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-06-21')
        ->quantity('0.000')
        ->create(['memo' => '発注先へ数量確認中']);

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '2',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $transaction = StockTransaction::query()->where('stock_id', $stock->id)->sole();

    expect($stock->refresh()->current_quantity)->toBe('-2.000')
        ->and($purchase->refresh()->quantity)->toBe('-2.000')
        ->and($purchase->memo)->toBe('発注先へ数量確認中')
        ->and($transaction->source_id)->toBe($purchase->id);
});

test('a correction that exceeds the lower stock balance bound rolls back atomically', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect();

    $stock->refresh()->forceFill(['current_quantity' => '-999999999.000'])->save();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '1',
        ])
        ->assertSessionHasErrors('quantity_to_subtract');

    expect($stock->refresh()->current_quantity)->toBe('-999999999.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('10.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->count())->toBe(1);
});

test('a correction that exceeds the lower term aggregate bound rolls back atomically', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-06-21')
        ->quantity('-999999999.000')
        ->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '1',
        ])
        ->assertSessionHasErrors('quantity_to_subtract');

    expect($stock->refresh()->current_quantity)->toBe('0.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('-999999999.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('an addition that overflows the term aggregate rolls back atomically', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    StockPurchase::factory()
        ->for($stock)
        ->forTerm('2026-06-21')
        ->quantity('999999999.000')
        ->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '1',
        ])
        ->assertSessionHasErrors('quantity_to_add');

    expect($stock->refresh()->current_quantity)->toBe('0.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('999999999.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('an addition that overflows the stock balance rolls back atomically', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->quantity('999999999.000')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '1',
        ])
        ->assertSessionHasErrors('quantity_to_add');

    expect($stock->refresh()->current_quantity)->toBe('999999999.000')
        ->and($stock->purchases()->doesntExist())->toBeTrue()
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
});

test('admins can add and correct purchases in a future term', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('4.000')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-08-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-08-21',
            'quantity_to_subtract' => '12',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->refresh()->current_quantity)->toBe('2.000')
        ->and($stock->purchases()->sole()->term_starts_on->toDateString())->toBe('2026-08-21')
        ->and($stock->purchases()->sole()->quantity)->toBe('-2.000');

    $transactions = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['transaction_type', 'quantity_delta', 'balance_after']);

    expect($transactions->pluck('quantity_delta')->all())->toBe(['10.000', '-12.000'])
        ->and($transactions->pluck('balance_after')->all())->toBe(['14.000', '2.000'])
        ->and($transactions->first()->transaction_type->value)->toBe('stock_in')
        ->and($transactions->last()->transaction_type->value)->toBe('correction');
});

test('purchase terms must start on the 21st', function (string $termStartsOn): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => $termStartsOn,
            'quantity_to_add' => '10',
        ])
        ->assertSessionHasErrors('term_starts_on');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and($stock->refresh()->current_quantity)->toBe('0.000');
})->with([
    'historical non-boundary' => '2026-06-20',
    'future non-boundary' => '2026-08-20',
]);

test('correction terms must start on the 21st', function (string $termStartsOn): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => $termStartsOn,
            'quantity_to_subtract' => '1',
        ])
        ->assertSessionHasErrors('term_starts_on');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and($stock->refresh()->current_quantity)->toBe('0.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
})->with([
    'historical non-boundary' => '2026-06-20',
    'future non-boundary' => '2026-08-20',
]);

test('invalid addition quantities are rejected', function (string $quantityToAdd): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => $quantityToAdd,
        ])
        ->assertSessionHasErrors('quantity_to_add');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and($stock->refresh()->current_quantity)->toBe('0.000');
})->with([
    'zero' => '0',
    'negative' => '-1',
    'not a number' => 'abc',
    'too many decimals' => '1.2345',
    'too many digits' => '1000000000',
]);

test('invalid correction quantities are rejected', function (string $quantityToSubtract): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => $quantityToSubtract,
        ])
        ->assertSessionHasErrors('quantity_to_subtract');

    expect($stock->purchases()->doesntExist())->toBeTrue()
        ->and($stock->refresh()->current_quantity)->toBe('0.000')
        ->and(StockTransaction::query()->where('stock_id', $stock->id)->doesntExist())->toBeTrue();
})->with([
    'zero' => '0',
    'negative' => '-1',
    'not a number' => 'abc',
    'too many decimals' => '1.2345',
    'too many digits' => '1000000000',
]);

test('fractional additions and corrections follow the stock setting', function (): void {
    $admin = User::factory()->admin()->create();
    $integerStock = Stock::factory()->named('セメント')->create();
    $fractionalStock = Stock::factory()->named('シンナー')->fractional()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $integerStock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '1.5',
        ])
        ->assertSessionHasErrors('quantity_to_add');

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $fractionalStock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '2.5',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $fractionalStock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '0.5',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($integerStock->refresh()->current_quantity)->toBe('0.000')
        ->and($fractionalStock->refresh()->current_quantity)->toBe('2.000')
        ->and($fractionalStock->purchases()->sole()->quantity)->toBe('2.000');
});

test('inactive stocks reject additions but allow corrections', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect();

    $stock->update(['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '2',
        ])
        ->assertSessionHasErrors('quantity_to_add');

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchase-corrections.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_subtract' => '15',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->refresh()->current_quantity)->toBe('-5.000')
        ->and($stock->purchases()->sole()->quantity)->toBe('-5.000');
});

test('stock ledger entries cannot be updated or deleted', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stocks.purchases.store', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity_to_add' => '10',
        ])
        ->assertRedirect();

    $transaction = StockTransaction::query()->sole();

    expect(fn () => $transaction->update(['description' => 'rewritten']))
        ->toThrow(LogicException::class, StockTransaction::IMMUTABLE_MESSAGE)
        ->and(fn () => $transaction->delete())
        ->toThrow(LogicException::class, StockTransaction::IMMUTABLE_MESSAGE);
});
