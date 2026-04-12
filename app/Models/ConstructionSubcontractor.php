<?php

namespace App\Models;

use Database\Factories\ConstructionSubcontractorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'phone',
])]
class ConstructionSubcontractor extends Model
{
    /** @use HasFactory<ConstructionSubcontractorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsToMany<ConstructionSchedule, $this>
     */
    public function constructionSchedules(): BelongsToMany
    {
        return $this->belongsToMany(ConstructionSchedule::class, 'construction_schedule_subcontractor')->withTimestamps();
    }
}
