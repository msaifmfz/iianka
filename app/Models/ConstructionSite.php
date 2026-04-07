<?php

namespace App\Models;

use Database\Factories\ConstructionSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'address',
    'notes',
])]
class ConstructionSite extends Model
{
    /** @use HasFactory<ConstructionSiteFactory> */
    use HasFactory;

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
