<?php

namespace App\Models;

use Database\Factories\SiteGuideFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
}
