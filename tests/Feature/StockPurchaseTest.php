<?php

use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\User;

test('admins can set the purchased quantity for a term', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('4.000')->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => '10',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')
        ->assertInertiaFlash('toast.message', '仕入数を更新しました。')
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

test('reducing a purchase cannot push the balance below the ledger range', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->quantity('4.000')->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect()->assertSessionHasNoErrors();

    // Simulate a balance already deep in the negative from accumulated usage.
    $stock->refresh()->forceFill(['current_quantity' => '-999999999.000'])->save();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '0',
    ])->assertSessionHasErrors('quantity');

    expect($stock->refresh()->current_quantity)->toBe('-999999999.000');
});

test('changing a purchase records only the delta in the ledger', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect();

    // Increase 10 -> 15.
    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '15',
    ])->assertRedirect();

    // Decrease 15 -> 12 is recorded as a correction.
    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '12',
    ])->assertRedirect();

    expect($stock->refresh()->current_quantity)->toBe('12.000')
        ->and($stock->purchases()->count())->toBe(1);

    $deltas = StockTransaction::query()
        ->where('stock_id', $stock->id)
        ->orderBy('id')
        ->get(['transaction_type', 'quantity_delta', 'balance_after']);

    expect($deltas->pluck('quantity_delta')->all())->toBe(['10.000', '5.000', '-3.000'])
        ->and($deltas->pluck('balance_after')->all())->toBe(['10.000', '15.000', '12.000'])
        ->and($deltas->last()->transaction_type->value)->toBe('correction');
});

test('saving an unchanged purchase quantity records nothing', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10.000',
    ])->assertRedirect();

    expect(StockTransaction::query()->where('stock_id', $stock->id)->count())->toBe(1);
});

test('purchases can be recorded for separate terms independently', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-07-21',
        'quantity' => '3',
    ])->assertRedirect();

    expect($stock->purchases()->count())->toBe(2)
        ->and($stock->refresh()->current_quantity)->toBe('13.000');
});

test('purchase term must start on the 21st', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $stock), [
            'term_starts_on' => '2026-06-20',
            'quantity' => '10',
        ])
        ->assertSessionHasErrors('term_starts_on');

    expect($stock->purchases()->count())->toBe(0);
});

test('invalid purchase quantities are rejected', function (string $quantity): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => $quantity,
        ])
        ->assertSessionHasErrors('quantity');

    expect($stock->purchases()->count())->toBe(0);
})->with([
    'negative' => '-1',
    'not a number' => 'abc',
    'too many decimals' => '1.2345',
    'too many digits' => '1000000000',
]);

test('fractional purchase quantities follow the stock setting', function (): void {
    $admin = User::factory()->admin()->create();
    $integerStock = Stock::factory()->named('セメント')->create();
    $fractionalStock = Stock::factory()->named('シンナー')->fractional()->create();

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $integerStock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => '1.5',
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $fractionalStock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => '1.5',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($fractionalStock->refresh()->current_quantity)->toBe('1.500');
});

test('inactive stocks reject purchase increases but allow decreases', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->named('セメント')->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect();

    $stock->update(['is_active' => false]);

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => '20',
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($admin)
        ->put(route('admin.stocks.purchases.update', $stock), [
            'term_starts_on' => '2026-06-21',
            'quantity' => '5',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stock->refresh()->current_quantity)->toBe('5.000');
});

test('stock ledger entries cannot be updated or deleted', function (): void {
    $admin = User::factory()->admin()->create();
    $stock = Stock::factory()->create();

    $this->actingAs($admin)->put(route('admin.stocks.purchases.update', $stock), [
        'term_starts_on' => '2026-06-21',
        'quantity' => '10',
    ])->assertRedirect();

    $transaction = StockTransaction::query()->sole();

    expect(fn () => $transaction->update(['description' => 'rewritten']))
        ->toThrow(LogicException::class, StockTransaction::IMMUTABLE_MESSAGE)
        ->and(fn () => $transaction->delete())
        ->toThrow(LogicException::class, StockTransaction::IMMUTABLE_MESSAGE);
});
