<?php

namespace App\Models;

use Database\Factories\SiteGuideFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SiteGuideFile extends Model
{
    /** @use HasFactory<SiteGuideFileFactory> */
    use HasFactory;

    protected $fillable = [
        'construction_site_id',
        'construction_schedule_id',
        'name',
        'disk',
        'path',
        'mime_type',
        'size',
    ];

    /**
     * @return BelongsTo<ConstructionSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class, 'construction_site_id');
    }

    /**
     * @return BelongsTo<ConstructionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ConstructionSchedule::class, 'construction_schedule_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
