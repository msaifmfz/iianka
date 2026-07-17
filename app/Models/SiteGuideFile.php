<?php

namespace App\Models;

use Database\Factories\SiteGuideFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Override;
use RuntimeException;

/**
 * @property int $id
 * @property string $disk
 * @property string $path
 */
#[Fillable([
    'name',
    'disk',
    'path',
    'mime_type',
    'size',
])]
class SiteGuideFile extends Model
{
    /** @use HasFactory<SiteGuideFileFactory> */
    use HasFactory;

    public function url(): string
    {
        return route('site-guide-files.show', $this);
    }

    public function absolutePath(): string
    {
        $path = Storage::disk($this->disk)->path($this->path);

        if (! is_file($path)) {
            throw new RuntimeException("Guide file [{$this->id}] is missing from storage.");
        }

        return $path;
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    public function deleteStoredFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }

    #[Override]
    protected static function booted(): void
    {
        self::deleted(function (SiteGuideFile $file): void {
            $file->deleteStoredFile();
        });
    }
}
