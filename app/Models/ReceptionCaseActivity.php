<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReceptionCaseActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reception_case_id',
    'user_id',
    'type',
    'memo',
    'from_status',
    'to_status',
    'from_assigned_user_id',
    'to_assigned_user_id',
])]
class ReceptionCaseActivity extends Model
{
    /** @use HasFactory<ReceptionCaseActivityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ReceptionCase, $this>
     */
    public function receptionCase(): BelongsTo
    {
        return $this->belongsTo(ReceptionCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_assigned_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_assigned_user_id');
    }
}
