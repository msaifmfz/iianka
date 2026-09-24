<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientPlace;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake(ClientDocument::DISK);
});

/**
 * A real file rather than UploadedFile::fake(): fakes report a MIME type from
 * the name, while real uploads are sniffed from their content.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'crm');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function minimalPdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

test('any signed-in user files a document with its date and place', function (): void {
    $uploader = User::factory()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($uploader)
        ->post(route('crm.clients.documents.store', $place->client), [
            'file' => realUpload('quote-0921.pdf', minimalPdf()),
            'name' => '見積書 9月',
            'issued_on' => '2026-09-21',
            'client_place_id' => (string) $place->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = ClientDocument::query()->sole();

    expect($document->client_id)->toBe($place->client_id)
        ->and($document->client_place_id)->toBe($place->id)
        ->and($document->uploaded_by_user_id)->toBe($uploader->id)
        ->and($document->name)->toBe('見積書 9月')
        ->and($document->issued_on?->toDateString())->toBe('2026-09-21')
        ->and($document->extension)->toBe('pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and(AuditLog::query()->where('event', 'client_documents.created')->count())->toBe(1);

    Storage::disk(ClientDocument::DISK)->assertExists($document->path);
});

test('a document without a name is named after its file and filed on the whole client', function (): void {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.clients.documents.store', $client), [
            'file' => UploadedFile::fake()->create('工程表.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'name' => '  ',
            'issued_on' => '',
            'client_place_id' => '',
        ])
        ->assertSessionHasNoErrors();

    $document = ClientDocument::query()->sole();

    expect($document->name)->toBe('工程表')
        ->and($document->client_place_id)->toBeNull()
        ->and($document->issued_on)->toBeNull();
});

test('a place of another client cannot be linked', function (): void {
    $client = Client::factory()->create();
    $otherPlace = ClientPlace::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.clients.documents.store', $client), [
            'file' => realUpload('quote.pdf', minimalPdf()),
            'client_place_id' => (string) $otherPlace->id,
        ])
        ->assertSessionHasErrors('client_place_id');

    expect(ClientDocument::query()->count())->toBe(0);
});

test('only documents and photos whose content matches their name are filed', function (string $name, string $content): void {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crm.clients.documents.store', $client), [
            'file' => realUpload($name, $content),
        ])
        ->assertSessionHasErrors(['file' => '登録できるのは書類（PDF・Word・Excel・PowerPoint・テキスト・CSV）と写真のみです。']);

    expect(ClientDocument::query()->count())->toBe(0);
    expect(Storage::disk(ClientDocument::DISK)->allFiles())->toBe([]);
})->with([
    'HTML named as a PDF' => ['quote.pdf', '<html><body><script>alert(1)</script></body></html>'],
    'HTML named as text' => ['notes.txt', '<html><body><script>alert(1)</script></body></html>'],
    'SVG named as a photo' => ['scan.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
    'a voice memo' => ['memo.wav', "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xac\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00data\x00\x00\x00\x00"],
    'an archive' => ['drawings.zip', "PK\x05\x06".str_repeat("\x00", 18)],
]);

test('a file is required', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('crm.clients.documents.store', Client::factory()->create()), [])
        ->assertSessionHasErrors('file');
});

test('guests cannot file or open documents', function (): void {
    $document = ClientDocument::factory()->create();

    $this->post(route('crm.clients.documents.store', $document->client), [])->assertRedirect(route('login'));
    $this->get(route('crm.documents.show', $document))->assertRedirect(route('login'));
});

test('a PDF opens in the browser viewer while an Office file downloads', function (): void {
    $pdf = ClientDocument::factory()->create(['name' => '契約書', 'extension' => 'pdf']);
    $excel = ClientDocument::factory()->create(['name' => '工程表', 'extension' => 'xlsx', 'path' => 'crm-documents/1/sheet.xlsx']);
    Storage::disk(ClientDocument::DISK)->put($pdf->path, minimalPdf());
    Storage::disk(ClientDocument::DISK)->put($excel->path, 'sheet');

    $this->actingAs(User::factory()->create());

    $pdfResponse = $this->get(route('crm.documents.show', $pdf))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
    expect($pdfResponse->headers->get('Content-Disposition'))->toStartWith('inline;');

    $excelResponse = $this->get(route('crm.documents.show', $excel))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'");
    expect($excelResponse->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

test('only the uploader or an admin deletes a document, and its file goes with it', function (): void {
    $uploader = User::factory()->create();
    $document = ClientDocument::factory()->for($uploader, 'uploader')->create();
    Storage::disk(ClientDocument::DISK)->put($document->path, minimalPdf());

    $this->actingAs(User::factory()->create())
        ->delete(route('crm.documents.destroy', $document))
        ->assertForbidden();
    $this->assertModelExists($document);

    $this->actingAs($uploader)
        ->delete(route('crm.documents.destroy', $document))
        ->assertRedirect();

    $this->assertModelMissing($document);
    Storage::disk(ClientDocument::DISK)->assertMissing($document->path);

    $other = ClientDocument::factory()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('crm.documents.destroy', $other))
        ->assertRedirect();
    $this->assertModelMissing($other);
});

test('the client page lists its documents newest first with who may delete them', function (): void {
    $viewer = User::factory()->create();
    $client = Client::factory()->create();
    $older = ClientDocument::factory()->for($client)->create(['name' => '旧見積書', 'issued_on' => '2026-08-01']);
    $newer = ClientDocument::factory()->for($client)->for($viewer, 'uploader')->create(['name' => '新見積書', 'issued_on' => '2026-09-01']);
    ClientDocument::factory()->create(['name' => '他社の書類']);

    $this->actingAs($viewer)
        ->get(route('crm.clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('documents', 2)
            ->where('documents.0.id', $newer->id)
            ->where('documents.0.can_delete', true)
            ->where('documents.1.id', $older->id)
            ->where('documents.1.can_delete', false)
            ->where('documentLimits.extensions', ClientDocument::allowedExtensions())
        );
});
