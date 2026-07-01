<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function auditSuccess(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Authenticatable $actor = null,
        ?Request $request = null,
    ): void {
        app(AuditLogger::class)->success($event, $description, $subject, $metadata, $actor, $request);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function auditFailure(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Authenticatable $actor = null,
        ?Request $request = null,
    ): void {
        app(AuditLogger::class)->failure($event, $description, $subject, $metadata, $actor, $request);
    }

    /**
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'message' => $message,
        ]);
    }
}
