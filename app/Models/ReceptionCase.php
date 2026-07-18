<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Reception\Enums\ReceptionCaseActivityType;
use App\Domain\Reception\Enums\ReceptionCasePriority;
use App\Domain\Reception\Enums\ReceptionCaseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReceptionCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int|null $assigned_user_id
 * @property ReceptionCaseStatus $status
 * @property CarbonImmutable|null $last_activity_at
 * @property-read Collection<int, ReceptionCaseSeenState> $seenStates
 */
#[Fillable([
    'case_number',
    'status',
    'priority',
    'company_name',
    'site_name',
    'reception_document_type_id',
    'reception_content',
    'work_memo',
    'due_on',
    'scheduled_on',
    'receptor_user_id',
    'assigned_user_id',
    'completed_at',
    'completed_by_user_id',
    'last_activity_at',
])]
class ReceptionCase extends Model
{
    /** @use HasFactory<ReceptionCaseFactory> */
    use HasFactory;

    public const CASE_NUMBER_PREFIX = 'WJA-C';

    /**
     * @var array<string, mixed>
     *
     * @psalm-suppress InvalidAttribute Psalm does not yet recognize PHP 8.5 property overrides.
     */
    #[Override]
    protected $attributes = [
        'status' => 'draft',
        'priority' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => ReceptionCaseStatus::class,
            'priority' => ReceptionCasePriority::class,
            'due_on' => 'date',
            'scheduled_on' => 'date',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Eager-load the relations needed to present a case in list and detail views.
     * Pass {@see $withActivities} and {@see $withAttachments} when rendering detail views.
     *
     * @param  Builder<ReceptionCase>  $query
     */
    #[Scope]
    protected function withListRelations(
        Builder $query,
        int $viewerId,
        bool $withActivities = false,
        bool $withAttachments = false,
    ): void {
        $userColumns = 'id,name,email,login_id,is_hidden_from_workers';

        $query->with([
            'documentType',
            "receptor:{$userColumns}",
            "assignedUser:{$userColumns}",
            "completedBy:{$userColumns}",
            // Load the viewer's own seen state, plus the current assignee's (so the
            // presenter can surface "last seen" for the person responsible).
            'seenStates' => fn ($query) => $query->where(fn ($query) => $query
                ->where('user_id', $viewerId)
                ->orWhereExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('reception_cases')
                    ->whereColumn('reception_cases.id', 'reception_case_seen_states.reception_case_id')
                    ->whereColumn('reception_cases.assigned_user_id', 'reception_case_seen_states.user_id'))),
        ]);

        if ($withActivities) {
            $query->with([
                "activities.user:{$userColumns}",
                "activities.fromAssignedUser:{$userColumns}",
                "activities.toAssignedUser:{$userColumns}",
            ]);
        }

        if ($withAttachments) {
            $query->with([
                "attachments.uploader:{$userColumns}",
            ]);
        }
    }

    /**
     * @param  Builder<ReceptionCase>  $query
     */
    #[Scope]
    protected function highPriorityFirst(Builder $query): void
    {
        $query->orderByRaw(
            'case priority when ? then 0 when ? then 1 else 2 end',
            [ReceptionCasePriority::High->value, ReceptionCasePriority::Middle->value],
        );
    }

    /**
     * @return BelongsTo<ReceptionDocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(ReceptionDocumentType::class, 'reception_document_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * @return HasMany<ReceptionCaseActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ReceptionCaseActivity::class);
    }

    /**
     * @return HasMany<ReceptionCaseAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReceptionCaseAttachment::class);
    }

    /**
     * @return HasMany<ReceptionCaseSeenState, $this>
     */
    public function seenStates(): HasMany
    {
        return $this->hasMany(ReceptionCaseSeenState::class);
    }

    public function markSeenBy(User $user): void
    {
        if ($this->assigned_user_id !== $user->id) {
            return;
        }

        ReceptionCaseSeenState::query()->updateOrCreate(
            [
                'reception_case_id' => $this->id,
                'user_id' => $user->id,
            ],
            [
                'seen_at' => now(),
            ],
        );
    }

    public function hasBeenSeenBy(User $user): bool
    {
        // No recorded activity (e.g. a private draft) means there is nothing to
        // flag as new/updated, so treat it as already seen.
        if ($this->last_activity_at === null) {
            return true;
        }

        $seenState = $this->seenStates->firstWhere('user_id', $user->id);

        return $seenState instanceof ReceptionCaseSeenState
            && $seenState->seen_at !== null
            && $seenState->seen_at->greaterThanOrEqualTo($this->last_activity_at);
    }

    public function canTransitionTo(ReceptionCaseStatus $nextStatus): bool
    {
        if (! $this->status->canTransitionTo($nextStatus)) {
            return false;
        }

        return $nextStatus !== ReceptionCaseStatus::InProgress
            || $this->assigned_user_id !== null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordActivity(User $user, ReceptionCaseActivityType $type, array $attributes = [], bool $bumpsActivity = true): ReceptionCaseActivity
    {
        $activity = $this->activities()->create([
            'user_id' => $user->id,
            'type' => $type->value,
            ...$attributes,
        ]);

        if ($bumpsActivity) {
            $this->forceFill(['last_activity_at' => now()])->save();
            $this->markSeenBy($user);
        }

        return $activity;
    }

    #[Override]
    protected static function booted(): void
    {
        self::deleting(function (ReceptionCase $case): void {
            $case->attachments()->get()->each->delete();
        });
    }
}
