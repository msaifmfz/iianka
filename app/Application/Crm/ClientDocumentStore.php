<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Keeps a client's documents and their files in step: a failed insert never
 * leaves an orphaned file, and a removed row never leaves one behind.
 */
final readonly class ClientDocumentStore
{
    /**
     * @param  array{name: string|null, issued_on: string|null, client_place_id: int|null}  $fields
     */
    public function create(Client $client, User $uploader, UploadedFile $file, array $fields): ClientDocument
    {
        $path = $file->store("crm-documents/{$client->id}", ClientDocument::DISK);

        if ($path === false) {
            throw new RuntimeException('Failed to store a CRM client document.');
        }

        try {
            return $client->documents()->create([
                'client_place_id' => $fields['client_place_id'],
                'uploaded_by_user_id' => $uploader->id,
                'name' => $fields['name'] ?? (pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document'),
                'issued_on' => $fields['issued_on'],
                'disk' => ClientDocument::DISK,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'extension' => Str::lower($file->getClientOriginalExtension()),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk(ClientDocument::DISK)->delete($path);

            throw $exception;
        }
    }

    public function delete(ClientDocument $document): void
    {
        $path = $document->path;

        $document->delete();

        Storage::disk(ClientDocument::DISK)->delete($path);
    }
}
