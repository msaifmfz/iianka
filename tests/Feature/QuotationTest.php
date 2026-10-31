<?php

declare(strict_types=1);

use App\Application\Quotation\QuotationCatalog;
use App\Application\Quotation\QuotationPdf;
use App\Domain\Quotation\QuotationCalculator;
use App\Models\Quotation;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;

/** @return array<string, mixed> */
function quotationRequestFixture(): array
{
    return Quotation::factory()->make()->input;
}

test('guests cannot read or create quotations', function (): void {
    $this->get(route('quotations.index'))->assertRedirect(route('login'));
    $this->post(route('quotations.store'), quotationRequestFixture())->assertRedirect(route('login'));
    $this->assertDatabaseCount('quotations', 0);
});

test('viewers can read but cannot create edit preview or update quotations', function (): void {
    $viewer = User::factory()->create(['role' => UserRole::Viewer]);
    $quote = Quotation::factory()->create();
    $this->actingAs($viewer)->get(route('quotations.index'))->assertOk();
    $this->get(route('quotations.show', $quote))->assertOk();
    $this->get(route('quotations.create'))->assertForbidden();
    $this->get(route('quotations.edit', $quote))->assertForbidden();
    $this->postJson(route('quotations.preview'), $quote->input)->assertForbidden();
    $this->post(route('quotations.store'), $quote->input)->assertForbidden();
    $this->put(route('quotations.update', $quote), [...$quote->input, 'version' => 1])->assertForbidden();
    expect($quote->fresh()->version)->toBe(1);
});

test('editors see the complete catalog and the agreed condition defaults', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->get(route('quotations.create'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('quotations/form')->has('catalog', 301)
        ->where('conditions.upward.rate', 20)->where('conditions.high.rate', 20)
        ->where('catalog.18.processing_fee', 12));
});

test('creating a quotation recomputes totals and snapshots source prices', function (): void {
    $user = User::factory()->create(['role' => UserRole::Editor]);
    $input = quotationRequestFixture();
    $this->actingAs($user)->post(route('quotations.store'), [...$input, 'calculation' => ['total' => '1']])->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success')->assertInertiaFlash('toast.message', '見積書を保存しました。PDFをダウンロードできます。');
    $quote = Quotation::query()->sole();
    expect($quote->calculation['total'])->toBe('15000');
    expect($quote->catalog_snapshot['0001']['price'])->toBe('120');
    expect($quote->number)->toBe('Q-20261029-00001');
    expect($quote->created_by)->toBe($user->id);
    expect($quote->version)->toBe(1);
    expect(array_keys($quote->catalog_snapshot))->toBe(['0001']);
    expect($quote->creator->is($user))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['event' => 'quotations.created', 'outcome' => 'success', 'actor_user_id' => $user->id, 'subject_type' => Quotation::class, 'subject_id' => (string) $quote->id]);
    $this->get(route('quotations.show', $quote))->assertInertia(fn (Assert $page): Assert => $page->component('quotations/show')->where('quotation.calculation.total', '15000')->where('canManage', true));
});

test('preview uses the same calculation without saving', function (): void {
    $input = quotationRequestFixture();
    $input['holiday'] = true;
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.preview'), $input)->assertOk()->assertJsonPath('total', '35000');
    $this->assertDatabaseCount('quotations', 0);
});

test('fractional rates preview and persist the same whole yen charges', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $input = quotationRequestFixture();
    $input['items'][0] = [...$input['items'][0], 'mode' => 'free', 'free_name' => '納品材料', 'free_unit' => '本', 'free_price' => '123.45', 'quantity' => '2.5'];
    $input['expenses'] = '12.5';
    $this->actingAs($editor);

    $preview = $this->postJson(route('quotations.preview'), $input)->assertOk()
        ->assertJsonPath('rounding_policy', 'line_half_up_yen')
        ->assertJsonPath('lines.0.unit_price', '123.45')
        ->assertJsonPath('lines.0.quantity', '2.5')
        ->assertJsonPath('lines.0.amount', '309')
        ->assertJsonPath('subtotal', '309')
        ->assertJsonPath('expenses', '13')
        ->assertJsonPath('total', '322');
    $this->assertDatabaseCount('quotations', 0);

    $this->post(route('quotations.store'), [...$input, 'calculation' => ['total' => '1', 'rounding_policy' => 'forged']])
        ->assertRedirect()->assertInertiaFlash('toast.type', 'success');

    $quote = Quotation::query()->sole();
    expect($quote->calculation)->toBe($preview->json());
    expect($quote->input['items'][0])->toMatchArray(['free_price' => '123.45', 'quantity' => '2.5']);
    expect($quote->input['expenses'])->toBe('12.5');
    $this->assertDatabaseHas('audit_logs', ['event' => 'quotations.created', 'subject_type' => Quotation::class, 'subject_id' => (string) $quote->id, 'actor_user_id' => $editor->id]);
    $this->get(route('quotations.show', $quote))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('quotation.calculation.rounding_policy', 'line_half_up_yen')
        ->where('quotation.calculation.total', '322'));
});

test('PDF previews and issued documents show the same rounded charges and precise unit rate', function (string $document): void {
    $input = quotationRequestFixture();
    $input['items'][0] = [...$input['items'][0], 'mode' => 'free', 'free_name' => '納品材料', 'free_unit' => '本', 'free_price' => '123.45', 'quantity' => '2.5'];
    $input['expenses'] = '12.5';
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));

    $preview = $this->postJson(route('quotations.pdf-preview', ['document' => $document]), $input)->assertOk();
    $previewParser = new PdfParser(StreamReader::createByString(base64_decode($preview->json('pdf'), true)));
    $previewText = implode('', array_column(quotationPdfTextRuns($previewParser, 1), 'text'));
    expect($previewText)->toContain('123.45', '309', '￥322', '明細金額・諸経費は1円未満を四捨五入しています。');
    expect($previewText)->not->toContain('308.625');
    $this->assertDatabaseCount('quotations', 0);

    $this->post(route('quotations.store'), $input)->assertRedirect();
    $quote = Quotation::query()->sole();
    $download = $this->get(route('quotations.pdf', [$quote, $document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $savedParser = new PdfParser(StreamReader::createByString($download->getContent()));
    $savedText = implode('', array_column(quotationPdfTextRuns($savedParser, 1), 'text'));
    expect($savedText)->toContain('123.45', '309', '￥322', '明細金額・諸経費は1円未満を四捨五入しています。');
    expect($savedText)->not->toContain('308.625');
})->with(['quotation', 'breakdown']);

test('legacy fractional snapshots remain exact until an explicit revision adopts whole yen rounding', function (string $document): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $input = quotationRequestFixture();
    $input['items'][0] = [...$input['items'][0], 'mode' => 'free', 'free_name' => '納品材料', 'free_unit' => '本', 'free_price' => '123.45', 'quantity' => '2.5'];
    $legacy = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());
    unset($legacy['rounding_policy']);
    $legacy['lines'][0]['amount'] = $legacy['subtotal'] = $legacy['total'] = '308.625';
    $quote = Quotation::factory()->create(['input' => $input, 'calculation' => $legacy, 'created_by' => $editor->id]);
    $this->actingAs($editor);

    $this->get(route('quotations.show', $quote))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('quotation.calculation', $legacy));
    $download = $this->get(route('quotations.pdf', [$quote, $document]))->assertOk();
    $parser = new PdfParser(StreamReader::createByString($download->getContent()));
    $legacyText = implode('', array_column(quotationPdfTextRuns($parser, 1), 'text'));
    expect($legacyText)->toContain('308.625');
    expect($legacyText)->not->toContain('明細金額・諸経費は1円未満を四捨五入しています。');

    $this->postJson(route('quotations.preview', $quote), $input)->assertOk()
        ->assertJsonPath('rounding_policy', 'line_half_up_yen')->assertJsonPath('total', '309');
    expect($quote->fresh()->calculation)->toBe($legacy);
    expect($quote->fresh()->version)->toBe(1);

    $this->put(route('quotations.update', $quote), [...$input, 'version' => 1])->assertRedirect(route('quotations.show', $quote));

    expect($quote->fresh()->calculation)->toMatchArray(['rounding_policy' => 'line_half_up_yen', 'subtotal' => '309', 'total' => '309']);
    expect($quote->fresh()->version)->toBe(2);
    $this->assertDatabaseHas('audit_logs', ['event' => 'quotations.updated', 'subject_type' => Quotation::class, 'subject_id' => (string) $quote->id, 'actor_user_id' => $editor->id]);
})->with(['quotation', 'breakdown']);

test('PDF previews render either document without issuing or saving a quotation', function (string $document): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
    $response = $this->postJson(route('quotations.pdf-preview', ['document' => $document]), quotationRequestFixture())
        ->assertOk();
    $bytes = base64_decode($response->json('pdf'), true);
    expect($bytes)->toStartWith('%PDF-');
    expect(strlen($bytes))->toBeGreaterThan(10000);
    $parser = new PdfParser(StreamReader::createByString($bytes));
    expect(implode('', array_column(quotationPdfTextRuns($parser, 1), 'text')))->toContain('プレビュー（未発行・入力内容は保存されません）');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->assertDatabaseCount('quotations', 0);
})->with(['quotation', 'breakdown']);

test('PDF previews require complete headers and valid items', function (): void {
    $input = quotationRequestFixture();
    $input['client_name'] = '';
    $input['project_name'] = '';
    $input['person_in_charge'] = '';
    $input['issued_on'] = '';
    $input['items'][0]['quantity'] = '0';
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.pdf-preview', ['document' => 'quotation']), $input)
        ->assertUnprocessable()->assertJsonValidationErrors(['client_name', 'project_name', 'person_in_charge', 'issued_on', 'items.0.quantity']);
    $this->assertDatabaseCount('quotations', 0);
});

test('guests and viewers cannot preview unsaved PDFs and invalid document types are refused', function (): void {
    $url = route('quotations.pdf-preview', ['document' => 'quotation']);
    $input = quotationRequestFixture();
    $this->postJson($url, $input)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))->postJson($url, $input)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.pdf-preview', ['document' => 'invalid']), $input)->assertNotFound();
    $this->assertDatabaseCount('quotations', 0);
});

test('an edited PDF preview uses unsaved input and historical prices without changing the issued quotation', function (): void {
    $quote = Quotation::factory()->create();
    $snapshot = $quote->catalog_snapshot;
    $snapshot['0001']['price'] = '200';
    $quote->update(['catalog_snapshot' => $snapshot, 'issuer' => [...$quote->issuer, 'name' => '発行時の会社名']]);
    $input = $quote->input;
    $input['client_name'] = '未保存の宛先';
    $input['items'][0]['quantity'] = '200';
    $this->mock(QuotationPdf::class)->shouldReceive('render')->once()
        ->withArgs(fn (Quotation $draft, string $document, bool $preview): bool => ! $draft->exists && $preview && $document === 'breakdown'
            && $draft->client_name === '未保存の宛先' && $draft->calculation['total'] === '50000'
            && $draft->number === $quote->number && $draft->issuer === $quote->issuer)
        ->andReturn('%PDF-preview');
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.pdf-preview', ['document' => 'breakdown', 'quotation' => $quote]), $input)->assertOk();
    $this->assertDatabaseCount('quotations', 1);
    expect($quote->fresh()->input)->toBe($quote->input);
    expect($quote->fresh()->calculation['total'])->toBe('15000');
    expect($quote->fresh()->version)->toBe(1);
});

test('updates retain original catalog snapshots and reject stale edits', function (): void {
    $quote = Quotation::factory()->create();
    $catalog = $quote->catalog_snapshot;
    $catalog['0001']['price'] = '200';
    $quote->update(['catalog_snapshot' => $catalog]);
    $input = [...$quote->input, 'version' => 1, 'client_name' => '更新後の得意先', 'project_name' => '更新後の工事', 'issued_on' => '2026-11-01'];
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $this->actingAs($editor)
        ->postJson(route('quotations.preview', $quote), $input)->assertOk()->assertJsonPath('total', '25000');
    $this->put(route('quotations.update', $quote), $input)->assertRedirect(route('quotations.show', $quote))
        ->assertInertiaFlash('toast.type', 'success')->assertInertiaFlash('toast.message', '見積書を更新しました。');
    expect($quote->fresh()->calculation['total'])->toBe('25000');
    expect($quote->fresh()->version)->toBe(2);
    expect($quote->fresh()->input)->not->toHaveKey('version');
    expect($quote->fresh()->issued_on->toDateString())->toBe('2026-11-01');
    $this->assertDatabaseHas('quotations', ['id' => $quote->id, 'client_name' => '更新後の得意先', 'project_name' => '更新後の工事']);
    $this->assertDatabaseHas('audit_logs', ['event' => 'quotations.updated', 'outcome' => 'success', 'actor_user_id' => $editor->id, 'subject_type' => Quotation::class, 'subject_id' => (string) $quote->id]);
    $this->put(route('quotations.update', $quote), [...$input, 'client_name' => '古い変更'])->assertSessionHasErrors('version');
    expect($quote->fresh()->client_name)->toBe($input['client_name']);
});

test('invalid item inputs cannot produce or persist a quotation', function (string $field, mixed $value): void {
    $input = quotationRequestFixture();
    data_set($input, $field, $value);
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->post(route('quotations.store'), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('quotations', 0);
})->with([
    'missing client' => ['client_name', ''], 'missing project' => ['project_name', ''],
    'zero quantity' => ['items.0.quantity', '0'], 'negative quantity' => ['items.0.quantity', '-1'],
    'non-numeric quantity' => ['items.0.quantity', 'abc'], 'unknown catalog' => ['items.0.catalog_id', 'unknown'],
    'impossible length' => ['items.0.length', '999'], 'negative rate' => ['items.0.rates.deck', '-20'],
    'too many items' => ['items', array_fill(0, 31, [])], 'no items' => ['items', []],
]);

test('a multiplier must be explicitly supplied and free entries need a price', function (): void {
    $input = quotationRequestFixture();
    $input['items'][0]['adjustment'] = 'multiply';
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->post(route('quotations.store'), $input)->assertSessionHasErrors('items.0.adjustment_value');
    $input['items'][0]['mode'] = 'free';
    $input['items'][0]['free_name'] = '納品';
    $input['items'][0]['free_unit'] = '式';
    $this->post(route('quotations.store'), $input)->assertSessionHasErrors('items.0.free_price');
    $this->assertDatabaseCount('quotations', 0);
});

function quotationPdfMetadata(PdfParser $parser, string $key): string
{
    $info = PdfType::resolve(PdfDictionary::get($parser->getCrossReference()->getTrailer(), 'Info'), $parser);
    $value = PdfType::resolve(PdfDictionary::get($info, $key), $parser);
    $bytes = $value instanceof PdfHexString ? hex2bin($value->value) : PdfString::unescape($value->value);

    return str_starts_with($bytes, "\xFE\xFF") ? mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE') : $bytes;
}

/** @return list<array{text: string, x: float, y: float, font: string}> */
function quotationPdfTextRuns(PdfParser $parser, int $pageNumber): array
{
    $page = new PdfReader($parser)->getPage($pageNumber);
    $resources = PdfType::resolve($page->getAttribute('Resources'), $parser);
    $fonts = PdfType::resolve(PdfDictionary::get($resources, 'Font'), $parser);
    $decoders = [];
    $names = [];
    foreach ($fonts->value as $key => $reference) {
        $font = PdfType::resolve($reference, $parser);
        $unicode = PdfType::resolve(PdfDictionary::get($font, 'ToUnicode'), $parser);
        $cmap = $unicode instanceof PdfStream ? $unicode->getUnfilteredStream() : '';
        preg_match('/begincodespacerange\s*<([0-9A-F]+)>/i', $cmap, $width);
        $codeBytes = isset($width[1]) ? strlen($width[1]) / 2 : 1;
        $characters = [];
        preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks);
        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9A-F]+)>\s*<([0-9A-F]+)>/i', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair) {
                $characters[strtoupper($pair[1])] = hex2bin($pair[2]);
            }
        }
        $ranges = [];
        preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks);
        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9A-F]+)>\s*<([0-9A-F]+)>\s*<([0-9A-F]+)>/i', $block, $triples, PREG_SET_ORDER);
            foreach ($triples as $range) {
                $ranges[] = [hexdec($range[1]), hexdec($range[2]), hexdec($range[3]), strlen($range[3])];
            }
        }
        $name = PdfDictionary::get($font, 'BaseFont')->value;
        $decoders[$key] = static function (string $bytes) use ($characters, $ranges, $codeBytes, $cmap): string {
            if ($cmap === '') {
                return mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
            }
            $unicode = '';
            foreach (str_split($bytes, (int) $codeBytes) as $code) {
                $hex = strtoupper(bin2hex($code));
                if (isset($characters[$hex])) {
                    $unicode .= $characters[$hex];

                    continue;
                }
                foreach ($ranges as [$first, $last, $base, $digits]) {
                    $number = hexdec($hex);
                    if ($number >= $first && $number <= $last) {
                        $unicode .= hex2bin(str_pad(dechex($base + $number - $first), $digits, '0', STR_PAD_LEFT));

                        continue 2;
                    }
                }
                throw new RuntimeException('The PDF contains an unmapped text character: '.$hex);
            }

            return mb_convert_encoding($unicode, 'UTF-8', 'UTF-16BE');
        };
        $names[$key] = $name;
    }
    $tokens = new PdfParser(StreamReader::createByString($page->getContentStream()));
    $operands = [];
    $font = '';
    $x = $y = 0.0;
    $runs = [];
    while (($token = $tokens->readValue()) !== false) {
        if (! $token instanceof PdfToken) {
            $operands[] = $token;

            continue;
        }
        if ($token->value === 'BT') {
            $x = $y = 0.0;
        } elseif ($token->value === 'Tf') {
            $font = $operands[0]->value;
        } elseif ($token->value === 'Td') {
            $x += $operands[0]->value;
            $y += $operands[1]->value;
        } elseif ($token->value === 'Tm') {
            $x = (float) $operands[4]->value;
            $y = (float) $operands[5]->value;
        } elseif (in_array($token->value, ['Tj', 'TJ'], true)) {
            $strings = $operands[0] instanceof PdfArray ? $operands[0]->value : [$operands[0]];
            $text = '';
            foreach ($strings as $string) {
                if ($string instanceof PdfString || $string instanceof PdfHexString) {
                    $bytes = $string instanceof PdfHexString ? hex2bin($string->value) : PdfString::unescape($string->value);
                    $text .= $decoders[$font]($bytes);
                }
            }
            $runs[] = ['text' => $text, 'x' => $x, 'y' => $y, 'font' => $names[$font]];
        }
        $operands = [];
    }

    return $runs;
}

test('PDF exports are real downloadable Japanese documents using the saved calculation', function (string $document, string $title): void {
    $quote = Quotation::factory()->create();
    $response = $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]))
        ->get(route('quotations.pdf', [$quote, $document]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$quote->number.'-'.$document.'.pdf"');
    expect($response->getContent())->toStartWith('%PDF-');
    expect(strlen($response->getContent()))->toBeGreaterThan(10000);
    $parser = new PdfParser(StreamReader::createByString($response->getContent()));
    expect(quotationPdfMetadata($parser, 'Title'))->toBe($title.' '.$quote->number);
    expect(quotationPdfMetadata($parser, 'Author'))->toBe($quote->issuer['name']);
    $page = new PdfReader($parser)->getPage(1)->getBoundary();
    expect($page->getWidth())->toBe(595.28);
    expect($page->getHeight())->toBe(841.89);
    $runs = quotationPdfTextRuns($parser, 1);
    $footer = array_values(array_filter($runs, fn (array $run): bool => $run['y'] > 0 && $run['y'] < 40));
    expect(implode('', array_column($footer, 'text')))->toBe($quote->number.'　1 / 1');
    expect(min(array_column($footer, 'x')))->toBeGreaterThan(300);
})->with(['quotation' => ['quotation', '御見積書'], 'breakdown' => ['breakdown', '内訳明細書']]);

test('quotation PDF caches are private and Japanese fonts work without a prewarmed cache', function (): void {
    $quote = Quotation::factory()->make();
    $storage = storage_path();
    $sandbox = sys_get_temp_dir().'/quotation-pdf-'.Str::uuid();
    File::makeDirectory($sandbox, 0755);
    chmod($sandbox, 0755);
    app()->useStoragePath($sandbox);

    try {
        $pdf = app(QuotationPdf::class)->render($quote, 'quotation');
        $cache = $sandbox.'/app/private/quotation-pdf-cache';
        clearstatcache(true, $cache);
        expect(fileperms($cache) & 0777)->toBe(0700);
        expect(File::allFiles($cache))->not->toBeEmpty();
        $runs = quotationPdfTextRuns(new PdfParser(StreamReader::createByString($pdf)), 1);
        $japanese = array_values(array_filter($runs, fn (array $run): bool => str_contains($run['text'], '御')));
        expect($japanese)->not->toBeEmpty();
        foreach ($japanese as $run) {
            expect($run['font'])->toContain('IPAexGothic');
        }
    } finally {
        app()->useStoragePath($storage);
        File::deleteDirectory($sandbox);
    }
});

test('breakdown footers retain the quotation number and correct page counters on every page', function (): void {
    $quote = Quotation::factory()->make();
    $input = $quote->input;
    $input['items'] = array_fill(0, 30, $input['items'][0]);
    $quote->input = $input;
    $quote->calculation = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());

    $pdf = app(QuotationPdf::class)->render($quote, 'breakdown');

    $parser = new PdfParser(StreamReader::createByString($pdf));
    $pages = new PdfReader($parser)->getPageCount();
    expect($pages)->toBeGreaterThanOrEqual(3);
    foreach (range(1, $pages) as $page) {
        $footer = array_values(array_filter(quotationPdfTextRuns($parser, $page), fn (array $run): bool => $run['y'] > 0 && $run['y'] < 40));
        expect(implode('', array_column($footer, 'text')))->toBe($quote->number.'　'.$page.' / '.$pages);
    }
});

test('the PDF view escapes input and includes explicit charges and all thirty lines', function (): void {
    $quote = Quotation::factory()->make();
    $input = $quote->input;
    $input['items'] = array_fill(0, 30, $input['items'][0]);
    $input['expenses'] = '2000';
    $input['holiday'] = true;
    $input['client_name'] = '<img src="https://example.test/secret">';
    $input['notes'] = '<script>alert(1)</script>';
    $quote->input = $input;
    $quote->calculation = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());
    $html = view('quotations.pdf', ['quote' => $quote, 'document' => 'breakdown', 'money' => QuotationPdf::money(...)])->render();
    expect($html)->not->toContain('<img src=', '<script>');
    $html = str_replace("\u{200B}", '', $html);
    expect(substr_count($html, '有効長=300L'))->toBe(30);
    expect($html)->toContain('交通費・諸経費', '休日加算', '472,000', '&lt;script&gt;', '<pagebreak />');
});

test('quotation validation describes invalid fields with their Japanese labels', function (string $field, mixed $value, string $label): void {
    $input = quotationRequestFixture();
    $input['net_enabled'] = true;
    data_set($input, $field, $value);
    $response = $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.preview'), $input)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($response->json('errors')[$field][0])->toContain($label);
})->with([
    'delivery location' => ['delivery_location', str_repeat('所', 201), '引渡場所'],
    'minimum charge' => ['minimum_charge', '1.5', '最低施工費'],
    'net multiplier' => ['net_multiplier', '11', 'NET掛け率'],
    'item note' => ['items.0.note', str_repeat('備', 301), '備考（お客様に表示）'],
    'item name' => ['items.0.free_name', str_repeat('名', 121), '品名'],
    'specification' => ['items.0.free_specification', str_repeat('規', 121), '規格'],
    'unit' => ['items.0.free_unit', str_repeat('単', 11), '単位'],
    'overall notes' => ['notes', str_repeat('備', 1501), '見積全体の備考'],
]);

/** @return list<float> */
function quotationPdfFontSizes(string $pdf): array
{
    $reader = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
    $sizes = [];
    foreach (range(1, $reader->getPageCount()) as $page) {
        preg_match_all('/\/F\w+\s+([\d.]+)\s+Tf\b/', $reader->getPage($page)->getContentStream(), $fonts);
        array_push($sizes, ...array_map(floatval(...), $fonts[1]));
    }

    return $sizes;
}

test('accepted long text never shrinks quotation or breakdown fonts below the note size', function (string $document, bool $newlines): void {
    $quote = Quotation::factory()->make();
    $input = $quote->input;
    $input['client_name'] = str_repeat('長い得意先名', 20);
    $input['project_name'] = str_repeat('工事名称', 30);
    $input['delivery_location'] = str_repeat('配送場所', 50);
    $input['person_in_charge'] = str_repeat('担当者名', 20);
    $input['items'][0] = array_replace($input['items'][0], [
        'mode' => 'free', 'free_name' => str_repeat('長い品名', 30), 'free_specification' => str_repeat('SPEC', 30),
        'free_unit' => '1234567890', 'free_price' => '100',
        'note' => $newlines ? implode("\n", range(1, 75)) : str_repeat('長い備考', 75),
    ]);
    $quote->input = $input;
    $quote->calculation = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());
    $pdf = app(QuotationPdf::class)->render($quote, $document);
    $sizes = quotationPdfFontSizes($pdf);

    expect($sizes)->not->toBeEmpty();
    expect(min($sizes))->toBeGreaterThanOrEqual(7.49);
})->with(['quotation', 'breakdown'])->with([false, true]);

test('PDFs embed fallback fonts for mathematical symbols and supplementary-plane emoji', function (): void {
    $quote = Quotation::factory()->make();
    $input = $quote->input;
    $input['client_name'] = '日本語 😀 🏗';
    $input['items'][0]['note'] = "≤ ≥ 😀 🏗\n二行目";
    $quote->input = $input;
    $quote->calculation = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());
    $pdf = app(QuotationPdf::class)->render($quote, 'quotation');

    expect($pdf)->toContain('NotoEmoji-Regular', 'DejaVuSansCondensed');
});

test('wide glyph units cannot shrink quotation notes below their readable font size', function (string $document): void {
    $quote = Quotation::factory()->make();
    $input = $quote->input;
    $input['items'][0] = [...$input['items'][0], 'mode' => 'free', 'free_name' => '材料', 'free_unit' => '😀😀', 'free_price' => '1', 'note' => str_repeat('備考', 150)];
    $quote->input = $input;
    $quote->calculation = (new QuotationCalculator)->calculate($input, app(QuotationCatalog::class)->indexed());

    $pdf = app(QuotationPdf::class)->render($quote, $document);

    expect(min(quotationPdfFontSizes($pdf)))->toBeGreaterThanOrEqual(7.49);
})->with(['quotation', 'breakdown']);

test('PDF text preserves note lines and graphemes while escaping HTML', function (): void {
    $text = "FIRST-LINE\r\nSECOND-LINE\rTHIRD-LINE\n<script>😀👷🏽‍♀️</script>";
    $html = QuotationPdf::text($text, 4)->toHtml();

    expect($html)->not->toContain('<script>');
    expect(substr_count($html, '<br />'))->toBe(3);
    expect(str_replace("\u{200B}", '', $html))->toContain('FIRST-LINE', 'SECOND-LINE', 'THIRD-LINE', '&lt;script&gt;', '👷🏽‍♀️');
    expect($html)->toContain('👷🏽‍♀️');
    expect(implode("\n", QuotationPdf::noteParts($text)))->toBe(str_replace(["\r\n", "\r"], "\n", $text));
});

test('disabled fields neither block saving nor leak charges into a new item', function (): void {
    $input = quotationRequestFixture();
    $input['net_multiplier'] = '';
    $input['items'][0]['catalog_id'] = '0025';
    $input['items'][0]['conditions']['high'] = true;
    $input['items'][0]['rates']['high'] = '';
    $input['items'][0]['rates']['deck'] = '999';
    $input['items'][0]['length'] = '-10';
    $input['items'][0]['processing_mode'] = 'manual';
    $input['items'][0]['processing_fee'] = '-100';
    $this->actingAs(User::factory()->create(['role' => UserRole::Editor]))
        ->postJson(route('quotations.preview'), $input)->assertOk()
        ->assertJsonPath('lines.0.unit_price', '240')->assertJsonPath('lines.0.processing_fee', '0');
});
