<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientPlaceLogAttachment;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
});

/**
 * Soft-deleting a client never fires the database cascade, so its places,
 * history and attachment files outlive it with no route left to reach them.
 */
function deletedClientWithAttachment(string $deletedDaysAgo): ClientPlaceLogAttachment
{
    $attachment = ClientPlaceLogAttachment::factory()->create();
    Storage::disk(ClientPlaceLogAttachment::DISK)->put($attachment->path, 'photo');

    $client = $attachment->log->place->client;
    $client->delete();
    $client->forceFill(['deleted_at' => now()->sub($deletedDaysAgo)])->saveQuietly();

    return $attachment;
}

test('long-deleted clients are purged with their files, recent ones and live ones are kept', function (): void {
    $old = deletedClientWithAttachment('100 days');
    $recent = deletedClientWithAttachment('10 days');
    $live = ClientPlaceLogAttachment::factory()->create();
    Storage::disk(ClientPlaceLogAttachment::DISK)->put($live->path, 'photo');

    $this->artisan('crm:prune-deleted-clients', ['--days' => 90, '--force' => true])
        ->assertSuccessful();

    $disk = Storage::disk(ClientPlaceLogAttachment::DISK);
    $disk->assertMissing($old->path);
    $disk->assertExists($recent->path);
    $disk->assertExists($live->path);

    $this->assertModelMissing($old);
    $this->assertModelMissing($old->log);
    $this->assertModelMissing($old->log->place);
    $this->assertModelExists($recent);
    $this->assertModelExists($live);

    expect(Client::withTrashed()->count())->toBe(2)
        ->and(AuditLog::query()->where('event', 'clients.purged')->count())->toBe(1);
});

test('purging a client removes its documents and their files', function (): void {
    $old = deletedClientWithAttachment('100 days');
    $document = ClientDocument::factory()->for($old->log->place->client)->create();
    Storage::disk(ClientDocument::DISK)->put($document->path, 'quote');

    $this->artisan('crm:prune-deleted-clients', ['--days' => 90, '--force' => true])
        ->assertSuccessful();

    $this->assertModelMissing($document);
    Storage::disk(ClientDocument::DISK)->assertMissing($document->path);
});

test('declining the confirmation removes nothing', function (): void {
    $attachment = deletedClientWithAttachment('100 days');

    $this->artisan('crm:prune-deleted-clients')
        ->expectsConfirmation('This cannot be undone. Continue?', 'no')
        ->assertFailed();

    Storage::disk(ClientPlaceLogAttachment::DISK)->assertExists($attachment->path);
    $this->assertModelExists($attachment);
    expect(AuditLog::query()->where('event', 'clients.purged')->exists())->toBeFalse();
});

test('nothing to prune is reported rather than treated as an error', function (): void {
    ClientPlaceLogAttachment::factory()->create();

    $this->artisan('crm:prune-deleted-clients', ['--force' => true])->assertSuccessful();

    expect(Client::withTrashed()->count())->toBe(1);
});

test('a negative retention window is refused', function (): void {
    deletedClientWithAttachment('100 days');

    $this->artisan('crm:prune-deleted-clients', ['--days' => -1, '--force' => true])
        ->assertFailed();

    expect(Client::withTrashed()->onlyTrashed()->count())->toBe(1);
});
