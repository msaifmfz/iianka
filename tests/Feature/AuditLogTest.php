<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;

test('audit logs store actor subject and sanitized metadata', function (): void {
    $actor = User::factory()->create(['name' => 'Audit Actor']);
    $subject = User::factory()->create(['name' => 'Audit Subject']);

    $log = app(AuditLogger::class)->success(
        event: 'test.audit.created',
        description: 'A test audit entry was written.',
        subject: $subject,
        metadata: [
            'field' => 'value',
            'password' => 'secret-password',
            'nested' => [
                'token' => 'secret-token',
            ],
        ],
        actor: $actor,
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->actor_user_id)->toBe($actor->id)
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe((string) $subject->id)
        ->and($log->subject_label)->toBe('Audit Subject')
        ->and($log->metadata['field'])->toBe('value')
        ->and($log->metadata['password'])->toBe('[redacted]')
        ->and($log->metadata['nested']['token'])->toBe('[redacted]');
});

test('audit model casts metadata and relates to the actor', function (): void {
    $actor = User::factory()->create();

    $log = AuditLog::query()->create([
        'event' => 'test.audit.cast',
        'outcome' => 'success',
        'actor_user_id' => $actor->id,
        'metadata' => ['key' => 'value'],
    ]);

    expect($log->fresh()->metadata)->toBe(['key' => 'value'])
        ->and($log->fresh()->actor->is($actor))->toBeTrue();
});
