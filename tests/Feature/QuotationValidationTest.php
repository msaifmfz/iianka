<?php

declare(strict_types=1);

use App\Http\Requests\SaveQuotationRequest;
use App\Models\Quotation;
use App\Models\User;
use App\UserRole;

mutates(SaveQuotationRequest::class);

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function quotationValidationPayload(array $changes = []): array
{
    $input = Quotation::factory()->make()->input;
    foreach ($changes as $field => $value) {
        data_set($input, $field, $value);
    }

    return $input;
}

test('empty quotation writes return the required field messages without saving', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors([
            'client_name' => '得意先は必須項目です。', 'project_name' => '工事名は必須項目です。',
            'person_in_charge' => '担当者は必須項目です。', 'issued_on' => '作成日は必須項目です。',
            'category' => '見積分類は必須項目です。', 'expenses' => '交通費・諸経費は必須項目です。',
            'small_job' => '少量施工は必須項目です。', 'minimum_charge' => '最低施工費は必須項目です。',
            'holiday' => '休日料金は必須項目です。', 'net_enabled' => 'NET参考額の表示は必須項目です。',
            'display_mode' => '見積書の表示は必須項目です。', 'items' => '明細は必須項目です。',
        ]);

    $this->assertDatabaseCount('quotations', 0);
});

test('invalid quotation values return their specific message without saving', function (array $changes, string $field, string $message): void {
    $input = quotationValidationPayload($changes);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'client type' => [['client_name' => ['invalid']], 'client_name', '得意先には、文字列を指定してください。'],
    'client maximum' => [['client_name' => str_repeat('名', 121)], 'client_name', '得意先の文字数は、120文字以下である必要があります。'],
    'project type' => [['project_name' => ['invalid']], 'project_name', '工事名には、文字列を指定してください。'],
    'project maximum' => [['project_name' => str_repeat('名', 121)], 'project_name', '工事名の文字数は、120文字以下である必要があります。'],
    'delivery type' => [['delivery_location' => ['invalid']], 'delivery_location', '引渡場所には、文字列を指定してください。'],
    'person type' => [['person_in_charge' => ['invalid']], 'person_in_charge', '担当者には、文字列を指定してください。'],
    'person maximum' => [['person_in_charge' => str_repeat('名', 81)], 'person_in_charge', '担当者の文字数は、80文字以下である必要があります。'],
    'date format' => [['issued_on' => '2026/10/29'], 'issued_on', "作成日の形式が'Y-m-d'と一致しません。"],
    'impossible date' => [['issued_on' => '2026-02-30'], 'issued_on', "作成日の形式が'Y-m-d'と一致しません。"],
    'category' => [['category' => 'unknown'], 'category', '選択された見積分類は、有効ではありません。'],
    'expense type' => [['expenses' => 'abc'], 'expenses', '交通費・諸経費には、数値を指定してください。'],
    'expense precision' => [['expenses' => '0.001'], 'expenses', '交通費・諸経費は、小数点以下が0-2である必要があります。'],
    'negative expenses' => [['expenses' => '-0.01'], 'expenses', '交通費・諸経費には、0から、10000000までの数字を指定してください。'],
    'expense maximum' => [['expenses' => '10000000.01'], 'expenses', '交通費・諸経費には、0から、10000000までの数字を指定してください。'],
    'small job boolean' => [['small_job' => 'yes'], 'small_job', "少量施工には、'true'か'false'を指定してください。"],
    'minimum type' => [['minimum_charge' => 'abc'], 'minimum_charge', '最低施工費には、整数を指定してください。'],
    'negative minimum' => [['minimum_charge' => '-1'], 'minimum_charge', '最低施工費には、0から、10000000までの数字を指定してください。'],
    'minimum maximum' => [['minimum_charge' => '10000001'], 'minimum_charge', '最低施工費には、0から、10000000までの数字を指定してください。'],
    'holiday boolean' => [['holiday' => 'yes'], 'holiday', "休日料金には、'true'か'false'を指定してください。"],
    'NET boolean' => [['net_enabled' => 'yes'], 'net_enabled', "NET参考額の表示には、'true'か'false'を指定してください。"],
    'NET multiplier required' => [['net_enabled' => true, 'net_multiplier' => null], 'net_multiplier', 'NET掛け率は必須項目です。'],
    'NET multiplier numeric' => [['net_enabled' => true, 'net_multiplier' => 'abc'], 'net_multiplier', 'NET掛け率には、数値を指定してください。'],
    'NET multiplier precision' => [['net_enabled' => true, 'net_multiplier' => '0.12345'], 'net_multiplier', 'NET掛け率は、小数点以下が0-4である必要があります。'],
    'NET multiplier minimum' => [['net_enabled' => true, 'net_multiplier' => '-0.0001'], 'net_multiplier', 'NET掛け率には、0から、10までの数字を指定してください。'],
    'display choice' => [['display_mode' => 'unknown'], 'display_mode', '選択された見積書の表示は、有効ではありません。'],
    'note type' => [['notes' => ['invalid']], 'notes', '見積全体の備考には、文字列を指定してください。'],
    'items type' => [['items' => 'invalid'], 'items', '明細には、配列を指定してください。'],
    'items list' => [['items' => [1 => []]], 'items', '明細はリストである必要があります。'],
    'unknown item key' => [['items.0.total' => '1'], 'items.0', '見積項目には、配列を指定してください。'],
    'item type' => [['items.0' => 'invalid'], 'items.0', '見積項目には、配列を指定してください。'],
    'input mode' => [['items.0.mode' => 'unknown'], 'items.0.mode', '選択された入力方法は、有効ではありません。'],
    'catalog type' => [['items.0.catalog_id' => ['invalid']], 'items.0.catalog_id', '品目には、文字列を指定してください。'],
    'catalog identifier maximum' => [['items.0.catalog_id' => str_repeat('1', 31)], 'items.0.catalog_id', '品目の文字数は、30文字以下である必要があります。'],
    'empty row' => [['items.0' => []], 'items.0', '見積項目は必須項目です。'],
    'quantity numeric message' => [['items.0.quantity' => 'abc'], 'items.0.quantity', '数量には、数値を指定してください。'],
    'quantity precision' => [['items.0.quantity' => '1.0001'], 'items.0.quantity', '数量は、小数点以下が0-3である必要があります。'],
    'quantity maximum' => [['items.0.quantity' => '10000.001'], 'items.0.quantity', '数量は、10000以下の数値である必要があります。'],
    'free name type' => [['items.0.free_name' => ['invalid']], 'items.0.free_name', '品名には、文字列を指定してください。'],
    'specification type' => [['items.0.free_specification' => ['invalid']], 'items.0.free_specification', '規格には、文字列を指定してください。'],
    'unit type' => [['items.0.free_unit' => ['invalid']], 'items.0.free_unit', '単位には、文字列を指定してください。'],
    'adjustment choice' => [['items.0.adjustment' => 'unknown'], 'items.0.adjustment', '選択された単価調整は、有効ではありません。'],
    'adjustment type' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => 'abc'], 'items.0.adjustment_value', '調整値には、数値を指定してください。'],
    'adjustment precision' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '1.00001'], 'items.0.adjustment_value', '調整値は、小数点以下が0-4である必要があります。'],
    'adjustment maximum' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '10000001'], 'items.0.adjustment_value', '調整値には、-10000000から、10000000までの数字を指定してください。'],
    'adjustment minimum' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '-10000001'], 'items.0.adjustment_value', '調整値には、-10000000から、10000000までの数字を指定してください。'],
    'unknown condition' => [['items.0.conditions.unknown' => true], 'items.0.conditions', '施工条件には、配列を指定してください。'],
    'condition boolean' => [['items.0.conditions.deck' => 'yes'], 'items.0.conditions.deck', "施工条件には、'true'か'false'を指定してください。"],
    'unknown rate' => [['items.0.rates.unknown' => '1'], 'items.0.rates', '割増率には、配列を指定してください。'],
    'active rate required' => [['items.0.rates.deck' => null], 'items.0.rates.deck', '割増率は必須項目です。'],
    'active rate numeric' => [['items.0.rates.deck' => 'abc'], 'items.0.rates.deck', '割増率には、数値を指定してください。'],
    'active rate precision' => [['items.0.rates.deck' => '1.001'], 'items.0.rates.deck', '割増率は、小数点以下が0-2である必要があります。'],
    'active rate maximum' => [['items.0.rates.deck' => '1000.01'], 'items.0.rates.deck', '割増率には、0から、1000までの数字を指定してください。'],
    'other rate numeric' => [['items.0.other_rate' => 'abc'], 'items.0.other_rate', 'その他割増には、数値を指定してください。'],
    'other rate precision' => [['items.0.other_rate' => '0.001'], 'items.0.other_rate', 'その他割増は、小数点以下が0-2である必要があります。'],
    'other rate minimum' => [['items.0.other_rate' => '-0.01'], 'items.0.other_rate', 'その他割増には、0から、1000までの数字を指定してください。'],
    'other rate maximum' => [['items.0.other_rate' => '1000.01'], 'items.0.other_rate', 'その他割増には、0から、1000までの数字を指定してください。'],
    'length numeric' => [['items.0.length' => 'abc'], 'items.0.length', '施工寸法には、数値を指定してください。'],
    'length precision' => [['items.0.length' => '300.001'], 'items.0.length', '施工寸法は、小数点以下が0-2である必要があります。'],
    'zero length' => [['items.0.length' => '0'], 'items.0.length', '施工寸法は、0より大きい必要があります。'],
    'length maximum' => [['items.0.catalog_id' => '0017', 'items.0.length' => '10000.01'], 'items.0.length', '施工寸法は、10000以下の数値である必要があります。'],
    'processing choice' => [['items.0.processing_mode' => 'unknown'], 'items.0.processing_mode', '選択された加工代の設定は、有効ではありません。'],
    'manual fee numeric' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => 'abc'], 'items.0.processing_fee', '加工代には、数値を指定してください。'],
    'manual fee precision' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '0.001'], 'items.0.processing_fee', '加工代は、小数点以下が0-2である必要があります。'],
    'manual fee minimum' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '-0.01'], 'items.0.processing_fee', '加工代には、0から、1000000までの数字を指定してください。'],
    'manual fee maximum' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '1000000.01'], 'items.0.processing_fee', '加工代には、0から、1000000までの数字を指定してください。'],
    'item note type' => [['items.0.note' => ['invalid']], 'items.0.note', '備考（お客様に表示）には、文字列を指定してください。'],
]);

test('missing item fields retain their required messages', function (string $field, string $label): void {
    $input = quotationValidationPayload();
    unset($input['items'][0][$field]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors(['items.0.'.$field => $label.'は必須項目です。']);

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'mode' => ['mode', '入力方法'], 'quantity' => ['quantity', '数量'],
    'adjustment' => ['adjustment', '単価調整'], 'conditions' => ['conditions', '施工条件'],
    'rates' => ['rates', '割増率'], 'other rate' => ['other_rate', 'その他割増'],
    'processing mode' => ['processing_mode', '加工代の設定'],
]);

test('free entries validate their own required values and price limits', function (array $changes, string $field, string $message): void {
    $input = quotationValidationPayload([
        'items.0.mode' => 'free', 'items.0.free_name' => '材料', 'items.0.free_unit' => '本', 'items.0.free_price' => '10',
        ...$changes,
    ]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors(['items.0.'.$field => $message]);

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'name required' => [['items.0.free_name' => null], 'free_name', '品名を入力してください。'],
    'unit required' => [['items.0.free_unit' => null], 'free_unit', '単位を入力してください。'],
    'price required' => [['items.0.free_price' => null], 'free_price', '単価を入力してください。'],
    'price numeric' => [['items.0.free_price' => 'abc'], 'free_price', '自由単価には、数値を指定してください。'],
    'price precision' => [['items.0.free_price' => '1.001'], 'free_price', '自由単価は、小数点以下が0-2である必要があります。'],
    'price minimum' => [['items.0.free_price' => '-0.01'], 'free_price', '自由単価には、0から、10000000までの数字を指定してください。'],
    'price maximum' => [['items.0.free_price' => '10000000.01'], 'free_price', '自由単価には、0から、10000000までの数字を指定してください。'],
]);

test('business validation rejects invalid adjustments and missing manual fees', function (array $changes, string $field, string $message): void {
    $input = quotationValidationPayload($changes);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors(['items.0.'.$field => $message]);

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'multiplier above limit' => [['items.0.adjustment' => 'multiply', 'items.0.adjustment_value' => '100.0001'], 'adjustment_value', '倍率は100以下にしてください。'],
    'negative multiplier' => [['items.0.adjustment' => 'multiply', 'items.0.adjustment_value' => '-0.0001'], 'adjustment_value', '調整後の単価は0円以上にしてください。'],
    'negative direct price' => [['items.0.adjustment' => 'direct', 'items.0.adjustment_value' => '-0.0001'], 'adjustment_value', '調整後の単価は0円以上にしてください。'],
    'discount below base price' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '-120.0001'], 'adjustment_value', '調整後の単価は0円以上にしてください。'],
    'manual fee required' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => null], 'processing_fee', '手動加工代を入力してください。'],
]);

test('quotation validation accepts inclusive numeric boundaries', function (array $changes): void {
    $input = quotationValidationPayload($changes);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.preview'), $input, [], JSON_PRESERVE_ZERO_FRACTION)->assertOk();

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'maximum expenses and minimum' => [['expenses' => '10000000', 'minimum_charge' => '10000000']],
    'zero expenses and minimum' => [['expenses' => '0.00', 'minimum_charge' => '0']],
    'maximum NET' => [['net_enabled' => true, 'net_multiplier' => '10']],
    'zero NET' => [['net_enabled' => true, 'net_multiplier' => '0.0000']],
    'smallest quantity' => [['items.0.quantity' => '0.001']],
    'largest quantity' => [['items.0.quantity' => '10000']],
    'maximum rates' => [['items.0.rates.deck' => '1000', 'items.0.other_rate' => '1000']],
    'zero active rate' => [['items.0.rates.deck' => '0.00']],
    'maximum adjustment' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '10000000']],
    'maximum multiplier' => [['items.0.adjustment' => 'multiply', 'items.0.adjustment_value' => '100']],
    'discount to zero' => [['items.0.adjustment' => 'add', 'items.0.adjustment_value' => '-120']],
    'zero direct price' => [['items.0.adjustment' => 'direct', 'items.0.adjustment_value' => '0']],
    'maximum length' => [['items.0.catalog_id' => '0017', 'items.0.length' => '10000']],
    'smallest length' => [['items.0.length' => '0.01']],
    'stock length' => [['items.0.length' => '450.00', 'items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '-1']],
    'maximum manual fee' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '1000000']],
    'zero manual fee' => [['items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '0.00']],
    'free maximum price' => [['items.0.mode' => 'free', 'items.0.free_name' => '材料', 'items.0.free_unit' => '本', 'items.0.free_price' => '10000000']],
    'free zero price' => [['items.0.mode' => 'free', 'items.0.free_name' => '材料', 'items.0.free_unit' => '本', 'items.0.free_price' => '0.00']],
    'explicit lump sum' => [['display_mode' => 'lump_sum']],
    'cutting declined' => [['items.0.processing_mode' => 'none']],
    'native JSON decimals' => [['expenses' => 12.5, 'items.0.quantity' => 2.125, 'items.0.length' => 300.25, 'items.0.rates.deck' => 12.5]],
    'native whole-number minimum' => [['minimum_charge' => 10000.0]],
    'inactive adjustment ignores stale value' => [['items.0.adjustment' => 'standard', 'items.0.adjustment_value' => 'stale value']],
]);

test('quotation writes accept and retain the maximum supported text lengths', function (): void {
    $input = quotationValidationPayload([
        'client_name' => str_repeat('得', 120), 'project_name' => str_repeat('工', 120),
        'delivery_location' => str_repeat('所', 200), 'person_in_charge' => str_repeat('担', 80), 'notes' => str_repeat('備', 1500),
        'items.0.mode' => 'free', 'items.0.free_name' => str_repeat('品', 120),
        'items.0.free_specification' => str_repeat('規', 120), 'items.0.free_unit' => str_repeat('単', 10),
        'items.0.free_price' => '0', 'items.0.note' => str_repeat('備', 300),
    ]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)->assertRedirect();

    $quote = Quotation::query()->sole();
    expect($quote->input)->toMatchArray([
        'client_name' => $input['client_name'], 'project_name' => $input['project_name'],
        'delivery_location' => $input['delivery_location'], 'person_in_charge' => $input['person_in_charge'], 'notes' => $input['notes'],
    ]);
    expect($quote->input['items'][0])->toMatchArray([
        'free_name' => $input['items'][0]['free_name'], 'free_specification' => $input['items'][0]['free_specification'],
        'free_unit' => $input['items'][0]['free_unit'], 'note' => $input['items'][0]['note'],
    ]);
});

test('invalid update versions return their own message without changing the quotation', function (mixed $version, string $message): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $quote = Quotation::factory()->for($editor, 'creator')->create();
    $before = $quote->fresh()->getAttributes();
    $input = [...$quote->input, 'version' => $version, 'client_name' => '保存されない変更'];

    $this->actingAs($editor)->putJson(route('quotations.update', $quote), $input)
        ->assertUnprocessable()->assertJsonValidationErrors(['version' => $message]);

    expect($quote->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('quotations', 1);
})->with([
    'missing version' => [null, '更新バージョンは必須項目です。'],
    'fractional version' => ['1.5', '更新バージョンには、整数を指定してください。'],
    'zero version' => [0, '更新バージョンには、1以上の数値を指定してください。'],
]);

test('historical processing limits are used when previewing an existing quotation', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $quote = Quotation::factory()->for($editor, 'creator')->create();
    $snapshot = $quote->catalog_snapshot;
    $snapshot['0001']['stock_length'] = 600;
    $quote->update(['catalog_snapshot' => $snapshot]);
    $input = $quote->input;
    $input['items'][0]['length'] = '500';

    $this->actingAs($editor)->postJson(route('quotations.preview', $quote), $input)
        ->assertOk()->assertJsonPath('lines.0.specification', 'D10（有効長=500L）');

    expect($quote->fresh()->input['items'][0]['length'])->toBe('300');
});

test('historical stock lengths normalize inactive manual fees before validation', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $quote = Quotation::factory()->for($editor, 'creator')->create();
    $snapshot = $quote->catalog_snapshot;
    $snapshot['0001']['stock_length'] = 300;
    $quote->update(['catalog_snapshot' => $snapshot]);
    $input = $quote->input;
    $input['items'][0]['processing_mode'] = 'manual';
    $input['items'][0]['processing_fee'] = '-1';

    $this->actingAs($editor)->postJson(route('quotations.preview', $quote), $input)
        ->assertOk()->assertJsonPath('lines.0.processing_fee', '0')->assertJsonPath('lines.0.needs_processing', false);

    expect($quote->fresh()->input['items'][0]['processing_mode'])->toBe('auto');
});

test('all thirty free rows normalize their stale construction options', function (): void {
    $input = quotationValidationPayload([
        'items.0.mode' => 'free', 'items.0.free_name' => '材料', 'items.0.free_unit' => '本', 'items.0.free_price' => '1',
        'items.0.length' => '-10', 'items.0.rates.deck' => '-1', 'items.0.other_rate' => '-1',
        'items.0.processing_mode' => 'manual', 'items.0.processing_fee' => '-1',
    ]);
    $input['items'] = array_fill(0, 30, $input['items'][0]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.preview'), $input)
        ->assertOk()->assertJsonCount(30, 'lines')->assertJsonPath('total', '3000');

    $this->assertDatabaseCount('quotations', 0);
});

test('normalization continues after a malformed row without adding errors for inactive fields', function (): void {
    $input = quotationValidationPayload([
        'items.0.mode' => 'free', 'items.0.free_name' => '材料', 'items.0.free_unit' => '本', 'items.0.free_price' => '1',
        'items.0.length' => '-10', 'items.0.rates.deck' => '-1', 'items.0.other_rate' => '-1',
    ]);
    $input['items'] = ['invalid', $input['items'][0]];
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $response = $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors('items.0');

    expect($response->json('errors'))->not->toHaveKey('items.1.length');
    expect($response->json('errors'))->not->toHaveKey('items.1.rates.deck');
    expect($response->json('errors'))->not->toHaveKey('items.1.other_rate');
    $this->assertDatabaseCount('quotations', 0);
});

test('business validation reports errors in later rows after a free or unknown catalog row', function (array $firstRow, array $expectedErrors): void {
    $input = quotationValidationPayload();
    $manual = [...$input['items'][0], 'processing_mode' => 'manual', 'processing_fee' => null];
    $input['items'] = [array_replace($input['items'][0], $firstRow), $manual];
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)
        ->assertUnprocessable()->assertJsonValidationErrors($expectedErrors);

    $this->assertDatabaseCount('quotations', 0);
})->with([
    'free first row' => [
        ['mode' => 'free', 'free_name' => '材料', 'free_unit' => '本', 'free_price' => '1'],
        ['items.1.processing_fee' => '手動加工代を入力してください。'],
    ],
    'unknown product first row' => [
        ['catalog_id' => 'unknown'],
        ['items.0.catalog_id' => '品目を選択してください。', 'items.1.processing_fee' => '手動加工代を入力してください。'],
    ],
]);

test('items without construction conditions ignore stale surcharges and keep normalized numeric strings', function (): void {
    $input = quotationValidationPayload([
        'items.0.catalog_id' => '0213', 'items.0.rates.deck' => '-1', 'items.0.other_rate' => '-1', 'items.0.length' => '-1',
    ]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)->assertRedirect();

    $quote = Quotation::query()->sole();
    expect($quote->input['items'][0]['rates']['deck'])->toBe('20');
    expect($quote->input['items'][0]['other_rate'])->toBe('0');
    expect($quote->calculation['lines'][0]['surcharge_rate'])->toBe('0');
});

test('optional delivery locations may be null', function (): void {
    $input = quotationValidationPayload(['delivery_location' => null]);
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->postJson(route('quotations.store'), $input)->assertRedirect();

    expect(Quotation::query()->sole()->input['delivery_location'])->toBeNull();
});

test('quotation model versions serialize as integers before persistence', function (): void {
    $quote = Quotation::factory()->make(['version' => '2']);

    expect($quote->toArray()['version'])->toBe(2);
});
