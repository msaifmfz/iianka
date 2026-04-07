<?php

namespace App\Models;

use Database\Factories\ConstructionSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionSite extends Model
{
    /** @use HasFactory<ConstructionSiteFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'notes',
    ];

    /**
     * @return HasMany<ConstructionSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ConstructionSchedule::class);
    }

    /**
     * @return HasMany<SiteGuideFile, $this>
     */
    public function guideFiles(): HasMany
    {
        return $this->hasMany(SiteGuideFile::class);
    }
}
