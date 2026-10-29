<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

mutates(AuditLogger::class);

test('audit entries inherit the current requests actor and tracing context', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create(['name' => '操作対象']);
    $request = Request::create('https://example.test/audit?source=form', 'POST', server: [
        'REMOTE_ADDR' => '192.0.2.15',
        'HTTP_USER_AGENT' => 'Audit test client',
    ]);
    $request->setRouteResolver(fn (): Route => new Route('POST', 'audit', fn (): null => null)->name('audit.test'));
    $request->attributes->set('request_id', 'request-trace');
    app()->instance('request', $request);
    $request->setUserResolver(fn (): User => $actor);
    context()->add('request_id', 'fallback-trace');

    $entry = app(AuditLogger::class)->record('test.request.context', subject: $subject);

    expect($entry)->toBeInstanceOf(AuditLog::class);
    $this->assertDatabaseHas('audit_logs', [
        'id' => $entry->id,
        'actor_user_id' => $actor->id,
        'actor_type' => User::class,
        'subject_type' => User::class,
        'subject_id' => (string) $subject->id,
        'subject_label' => '操作対象',
        'ip_address' => '192.0.2.15',
        'user_agent' => 'Audit test client',
        'method' => 'POST',
        'url' => 'https://example.test/audit?source=form',
        'route_name' => 'audit.test',
        'request_id' => 'request-trace',
    ]);
});

test('audit entries without an HTTP request retain console tracing context', function (): void {
    app()->offsetUnset('request');
    context()->add('request_id', 'console-trace');

    $entry = app(AuditLogger::class)->record('test.console.context');

    expect($entry)->toBeInstanceOf(AuditLog::class);
    $this->assertDatabaseHas('audit_logs', [
        'id' => $entry->id,
        'actor_user_id' => null,
        'actor_type' => null,
        'subject_type' => null,
        'subject_id' => null,
        'subject_label' => null,
        'ip_address' => null,
        'user_agent' => null,
        'method' => null,
        'url' => null,
        'route_name' => null,
        'request_id' => 'console-trace',
    ]);
});

test('explicit audit context takes precedence over the current request and inferred subject', function (): void {
    $requestActor = User::factory()->create();
    $actor = User::factory()->create();
    $subject = User::factory()->create();
    app()->instance('request', Request::create('https://example.test/current', 'GET'));
    $request = Request::create('https://example.test/explicit', 'DELETE');
    $request->setUserResolver(fn (): User => $requestActor);
    context()->add('request_id', 'explicit-context-trace');

    $entry = app(AuditLogger::class)->record(
        'test.explicit.context',
        actor: $actor,
        subject: $subject,
        request: $request,
        subjectType: 'legacy-record',
        subjectId: 'legacy-42',
        subjectLabel: '指定した対象',
        actorType: 'service-account',
    );

    expect($entry)->toBeInstanceOf(AuditLog::class);
    $this->assertDatabaseHas('audit_logs', [
        'id' => $entry->id,
        'actor_user_id' => $actor->id,
        'actor_type' => 'service-account',
        'subject_type' => 'legacy-record',
        'subject_id' => 'legacy-42',
        'subject_label' => '指定した対象',
        'method' => 'DELETE',
        'url' => 'https://example.test/explicit',
        'route_name' => null,
        'request_id' => 'explicit-context-trace',
    ]);
});

test('audit entries retain an explicit subject reference without a model instance', function (): void {
    app()->offsetUnset('request');

    $entry = app(AuditLogger::class)->record(
        'test.external.subject',
        subjectType: 'legacy-record',
        subjectId: 'legacy-42',
        subjectLabel: '削除済みの対象',
        actorType: 'console',
    );

    expect($entry)->toBeInstanceOf(AuditLog::class);
    $this->assertDatabaseHas('audit_logs', [
        'id' => $entry->id,
        'actor_type' => 'console',
        'subject_type' => 'legacy-record',
        'subject_id' => 'legacy-42',
        'subject_label' => '削除済みの対象',
    ]);
});
