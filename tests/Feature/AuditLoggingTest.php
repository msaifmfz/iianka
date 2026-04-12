<?php

use App\Models\AuditLog;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('successful and failed password logins are audited', function (): void {
    $user = User::factory()->create(['login_id' => 'audit-user']);

    $this->post(route('login.store'), [
        'login_id' => $user->login_id,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors();

    $this->assertGuest();

    $this->post(route('login.store'), [
        'login_id' => $user->login_id,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    expect(AuditLog::query()->where('event', 'auth.login.failed')->where('outcome', 'failure')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'auth.login.succeeded')->where('actor_user_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'auth.login.failed')->first()->metadata['credentials']['password'])->toBe('[redacted]');
});

test('admins can view and filter audit logs', function (): void {
    $admin = User::factory()->admin()->create();
    $actor = User::factory()->create(['name' => 'Tracked User']);

    $trackedLog = AuditLog::query()->create([
        'event' => 'admin.users.updated',
        'outcome' => 'success',
        'actor_user_id' => $actor->id,
        'subject_type' => User::class,
        'subject_id' => (string) $actor->id,
        'subject_label' => 'Tracked User',
        'ip_address' => '203.0.113.10',
        'metadata' => ['changed' => ['name']],
    ]);
    $trackedLog->forceFill(['created_at' => '2026-04-12 10:30:15'])->save();

    $outsideRangeLog = AuditLog::query()->create([
        'event' => 'auth.login.failed',
        'outcome' => 'failure',
        'ip_address' => '203.0.113.20',
    ]);
    $outsideRangeLog->forceFill(['created_at' => '2026-04-12 10:29:59'])->save();

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', [
            'search' => 'Tracked',
            'date_from' => '2026-04-12T10:30:00',
            'date_to' => '2026-04-12T10:30:15',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/audit-logs/index')
            ->where('filters.search', 'Tracked')
            ->where('filters.date_from', '2026-04-12T10:30:00')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'admin.users.updated')
            ->where('logs.data.0.event_label', 'ユーザー更新')
            ->where('logs.data.0.outcome_label', '成功')
            ->where('logs.data.0.subject_type_label', 'ユーザー')
            ->where('logs.data.0.actor.name', 'Tracked User')
            ->where('options.events.0.label', 'ユーザー更新')
        );
});

test('non admins cannot view audit logs and the denial is audited', function (): void {
    $user = User::factory()->editor()->create();

    $this->actingAs($user)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    expect(AuditLog::query()
        ->where('event', 'authorization.denied')
        ->where('outcome', 'failure')
        ->where('actor_user_id', $user->id)
        ->exists())->toBeTrue();
});

test('admin user mutations are audited', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Audited Member',
            'login_id' => 'audited-member',
            'email' => null,
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Viewer->value,
            'is_hidden_from_workers' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $member = User::query()->where('login_id', 'audited-member')->firstOrFail();

    expect(AuditLog::query()
        ->where('event', 'admin.users.created')
        ->where('actor_user_id', $admin->id)
        ->where('subject_type', User::class)
        ->where('subject_id', (string) $member->id)
        ->exists())->toBeTrue();
});
