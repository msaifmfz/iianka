<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['reception_case_id', 'user_id', 'seen_at'])]
class ReceptionCaseSeenState extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }

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
}
