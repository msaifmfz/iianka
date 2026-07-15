<?php

declare(strict_types=1);

namespace App\Models;

use App\ScheduleStockSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property ScheduleStockSourceType $schedule_type
 * @property int $schedule_id
 * @property string $content_hash
 * @property string $content_snapshot
 * @property string $parser_version
 * @property int|null $created_by
 */
#[Fillable([
    'schedule_type',
    'schedule_id',
    'content_hash',
    'content_snapshot',
    'parser_version',
    'created_by',
])]
class ScheduleContentRevision extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'schedule_type' => ScheduleStockSourceType::class,
            'schedule_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<ScheduleStockMention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(ScheduleStockMention::class, 'revision_id');
    }
}
