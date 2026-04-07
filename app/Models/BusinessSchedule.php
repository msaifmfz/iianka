<?php

namespace App\Models;

use Database\Factories\BusinessScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class BusinessSchedule extends Model
{
    /** @use HasFactory<BusinessScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'scheduled_on',
        'starts_at',
        'ends_at',
        'time_note',
        'personnel',
        'location',
        'general_contractor',
        'person_in_charge',
        'content',
        'memo',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function formattedTime(): string
    {
        if ($this->time_note !== null && $this->time_note !== '') {
            return $this->time_note;
        }

        if ($this->starts_at === null) {
            return '時間未定';
        }

        $start = Carbon::parse($this->starts_at)->format('H:i');

        if ($this->ends_at === null) {
            return $start;
        }

        $end = Carbon::parse($this->ends_at)->format('H:i');

        return "{$start} - {$end}";
    }
}
