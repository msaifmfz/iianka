<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable([
    'event',
    'outcome',
    'description',
    'actor_user_id',
    'actor_type',
    'subject_type',
    'subject_id',
    'subject_label',
    'ip_address',
    'user_agent',
    'method',
    'url',
    'route_name',
    'request_id',
    'metadata',
])]
class AuditLog extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
