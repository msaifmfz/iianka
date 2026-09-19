<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Client;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Writes CRM history entries together with their photos and voice memos,
 * keeping the place's `last_logged_at` in step so the map's freshness cues
 * stay right.
 *
 * Files are stored before the transaction opens; if the transaction rolls
 * back they are deleted again, so a failed save never leaves orphans.
 */
final readonly class PlaceLogRecorder
{
    /**
     * @param  array{type: string, occurred_at: string, summary: string, reaction?: string|null, client_contact_id?: int|null}  $fields
     * @param  list<array{file: UploadedFile, duration_seconds?: int|null}>  $attachments
     */
    public function create(ClientPlace $place, User $author, array $fields, array $attachments = []): ClientPlaceLog
    {
        $stored = $this->storeFiles($place, $attachments);

        try {
            return DB::transaction(function () use ($place, $author, $fields, $stored): ClientPlaceLog {
                $log = $place->logs()->create([...$fields, 'user_id' => $author->id]);

                $this->attach($log, $author, $stored);
                $place->refreshLastLoggedAt();

                return $log;
            });
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($stored, 'path'));

            throw $exception;
        }
    }

    /**
     * @param  array{type: string, occurred_at: string, summary: string, reaction?: string|null, client_contact_id?: int|null}  $fields
     * @param  list<array{file: UploadedFile, duration_seconds?: int|null}>  $attachments
     */
    public function update(ClientPlaceLog $log, User $editor, array $fields, array $attachments = []): void
    {
        $place = $log->place;
        $stored = $this->storeFiles($place, $attachments);

        try {
            DB::transaction(function () use ($log, $place, $editor, $fields, $stored): void {
                $log->update($fields);

                $this->attach($log, $editor, $stored);
                $place->refreshLastLoggedAt();
            });
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($stored, 'path'));

            throw $exception;
        }
    }

    public function delete(ClientPlaceLog $log): void
    {
        $place = $log->place;
        $paths = $log->attachments()->pluck('path')->all();

        DB::transaction(function () use ($log, $place): void {
            $log->delete();
            $place->refreshLastLoggedAt();
        });

        $this->deleteFiles($paths);
    }

    public function deleteAttachment(ClientPlaceLogAttachment $attachment): void
    {
        $path = $attachment->path;

        $attachment->delete();

        $this->deleteFiles([$path]);
    }

    public function deletePlace(ClientPlace $place): void
    {
        $paths = ClientPlaceLogAttachment::query()
            ->whereHas('log', fn ($query) => $query->whereBelongsTo($place, 'place'))
            ->pluck('path')
            ->all();

        DB::transaction(fn (): ?bool => $place->delete());

        $this->deleteFiles($paths);
    }

    /**
     * Permanently remove a soft-deleted client with everything under it.
     *
     * A soft delete only hides the client — it never fires the database
     * cascade — so its places, history and attachment files all survive it.
     * This is the only path that reclaims them.
     */
    public function purgeClient(Client $client): void
    {
        $paths = ClientPlaceLogAttachment::query()
            ->whereHas('log.place', fn ($query) => $query->whereBelongsTo($client))
            ->pluck('path')
            ->all();

        DB::transaction(fn (): ?bool => $client->forceDelete());

        $this->deleteFiles($paths);
    }

    /**
     * @param  list<array{file: UploadedFile, duration_seconds?: int|null}>  $attachments
     * @return list<array{path: string, file: UploadedFile, extension: string, duration_seconds: int|null}>
     */
    private function storeFiles(ClientPlace $place, array $attachments): array
    {
        $stored = [];

        try {
            foreach ($attachments as $attachment) {
                $file = $attachment['file'];
                $path = $file->store("crm-attachments/{$place->id}", ClientPlaceLogAttachment::DISK);

                if ($path === false) {
                    throw new RuntimeException('Failed to store a CRM attachment.');
                }

                $stored[] = [
                    'path' => $path,
                    'file' => $file,
                    'extension' => Str::lower($file->getClientOriginalExtension()),
                    'duration_seconds' => $attachment['duration_seconds'] ?? null,
                ];
            }
        } catch (Throwable $exception) {
            $this->deleteFiles(array_column($stored, 'path'));

            throw $exception;
        }

        return $stored;
    }

    /**
     * @param  list<array{path: string, file: UploadedFile, extension: string, duration_seconds: int|null}>  $stored
     */
    private function attach(ClientPlaceLog $log, User $uploader, array $stored): void
    {
        foreach ($stored as $item) {
            $log->attachments()->create([
                'uploaded_by_user_id' => $uploader->id,
                'kind' => ClientPlaceLogAttachment::kindForExtension($item['extension']),
                'name' => pathinfo($item['file']->getClientOriginalName(), PATHINFO_FILENAME) ?: 'attachment',
                'disk' => ClientPlaceLogAttachment::DISK,
                'path' => $item['path'],
                'mime_type' => $item['file']->getMimeType(),
                'extension' => $item['extension'],
                'size' => $item['file']->getSize(),
                'duration_seconds' => $item['duration_seconds'],
            ]);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(ClientPlaceLogAttachment::DISK)->delete($paths);
        }
    }
}
