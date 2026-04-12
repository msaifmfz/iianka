<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $event,
        string $outcome = 'success',
        ?string $description = null,
        ?Authenticatable $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        ?string $subjectLabel = null,
        ?string $actorType = null,
    ): ?AuditLog {
        try {
            $request ??= $this->request();
            $actor ??= $request?->user();

            return AuditLog::query()->create([
                'event' => $event,
                'outcome' => $outcome,
                'description' => $description,
                'actor_user_id' => $actor instanceof User ? $actor->getKey() : null,
                'actor_type' => $actorType ?? $this->actorType($actor),
                'subject_type' => $subjectType ?? $this->subjectType($subject),
                'subject_id' => ($subjectId ?? $subject?->getKey()) === null ? null : (string) ($subjectId ?? $subject?->getKey()),
                'subject_label' => $subjectLabel ?? $this->subjectLabel($subject),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'route_name' => $request?->route()?->getName(),
                'request_id' => $this->requestId($request),
                'metadata' => $this->sanitize($metadata),
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to write audit log.', [
                'audit_event' => $event,
                'audit_outcome' => $outcome,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function success(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Authenticatable $actor = null,
        ?Request $request = null,
    ): ?AuditLog {
        return $this->record(
            event: $event,
            outcome: 'success',
            description: $description,
            actor: $actor,
            subject: $subject,
            metadata: $metadata,
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function failure(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?Authenticatable $actor = null,
        ?Request $request = null,
    ): ?AuditLog {
        return $this->record(
            event: $event,
            outcome: 'failure',
            description: $description,
            actor: $actor,
            subject: $subject,
            metadata: $metadata,
            request: $request,
        );
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function requestId(?Request $request): ?string
    {
        $requestId = $request?->attributes->get('request_id') ?? context('request_id');

        return is_string($requestId) ? $requestId : null;
    }

    private function actorType(?Authenticatable $actor): ?string
    {
        if (! $actor instanceof Authenticatable) {
            return null;
        }

        return $actor::class;
    }

    private function subjectType(?Model $subject): ?string
    {
        return $subject instanceof Model ? $subject::class : null;
    }

    private function subjectLabel(?Model $subject): ?string
    {
        if (! $subject instanceof Model) {
            return null;
        }

        foreach (['name', 'title', 'login_id', 'email', 'location', 'label'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $subject::class.'#'.$subject->getKey();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitize(array $metadata): array
    {
        return Arr::map($metadata, fn (mixed $value, string|int $key): mixed => $this->sanitizeValue($key, $value));
    }

    private function sanitizeValue(string|int $key, mixed $value): mixed
    {
        if (is_string($key) && $this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_array($value)) {
            return Arr::map($value, fn (mixed $childValue, string|int $childKey): mixed => $this->sanitizeValue($childKey, $childValue));
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[unserializable]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'secret')
            || str_contains($key, 'recovery')
            || ($key !== 'credentials' && str_contains($key, 'credential'))
            || $key === '_token';
    }
}
