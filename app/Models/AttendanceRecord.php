<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property string $status
 */
#[Fillable(['user_id', 'work_date', 'status', 'note'])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    public const STATUS_WORKING = 'working';

    public const STATUS_EARLY = 'early';

    public const STATUS_LEAVE = 'leave';

    /**
     * Every status an attendance record may hold, in display order.
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_WORKING,
        self::STATUS_EARLY,
        self::STATUS_LEAVE,
    ];

    /**
     * Statuses that put the worker on site, so the day counts toward the user's
     * 出勤日数 total. 早出 is an early start to a working day rather than a kind
     * of absence, so it counts exactly like 出勤.
     *
     * @var list<string>
     */
    public const WORKED_DAY_STATUSES = [
        self::STATUS_WORKING,
        self::STATUS_EARLY,
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function countsAsWorkedDay(): bool
    {
        return in_array($this->status, self::WORKED_DAY_STATUSES, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
