<?php

namespace App\Models;

use Database\Factories\CleaningDutyRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

#[Fillable([
    'weekday',
    'label',
    'location',
    'notes',
    'is_active',
    'sort_order',
])]
class CleaningDutyRule extends Model
{
    /** @use HasFactory<CleaningDutyRuleFactory> */
    use HasFactory;

    public const array WEEKDAY_LABELS = [
        0 => '日曜日',
        1 => '月曜日',
        2 => '火曜日',
        3 => '水曜日',
        4 => '木曜日',
        5 => '金曜日',
        6 => '土曜日',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAY_LABELS[$this->weekday] ?? '曜日未設定';
    }
}
