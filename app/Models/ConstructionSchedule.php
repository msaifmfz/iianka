<?php

namespace App\Models;

use Database\Factories\ConstructionScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Override;

#[Fillable([
    'construction_site_id',
    'scheduled_on',
    'schedule_number',
    'starts_at',
    'ends_at',
    'time_note',
    'status',
    'meeting_place',
    'personnel',
    'location',
    'general_contractor',
    'person_in_charge',
    'content',
    'navigation_address',
    'voucher_note',
    'voucher_checked_at',
    'voucher_checked_by_user_id',
])]
class ConstructionSchedule extends Model
{
    /** @use HasFactory<ConstructionScheduleFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_POSTPONED = 'postponed';

    public const STATUS_CANCELED = 'canceled';

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'voucher_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ConstructionSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class, 'construction_site_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voucherCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voucher_checked_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<SiteGuideFile, $this>
     */
    public function selectedGuideFiles(): BelongsToMany
    {
        return $this->belongsToMany(SiteGuideFile::class, 'construction_schedule_site_guide_file')->withTimestamps();
    }

    /**
     * @return HasMany<SiteGuideFile, $this>
     */
    public function directGuideFiles(): HasMany
    {
        return $this->hasMany(SiteGuideFile::class);
    }

    public function googleMapsUrl(): ?string
    {
        if ($this->navigation_address === null || $this->navigation_address === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->navigation_address);
    }

    /**
     * @return Collection<int, SiteGuideFile>
     */
    public function allGuideFiles(): Collection
    {
        return $this->selectedGuideFiles->merge($this->directGuideFiles)->values();
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
