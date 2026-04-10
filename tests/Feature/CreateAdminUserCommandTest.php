<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an admin user with a nullable email address', function (): void {
    $this->artisan('admin:create-user', [
        'login_id' => 'admin-0001',
        '--name' => 'Admin User',
        '--email' => '',
        '--password' => 'password',
    ])
        ->expectsOutputToContain('Admin user [admin-0001] created.')
        ->assertSuccessful();

    $user = User::query()->where('login_id', 'admin-0001')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin User')
        ->and($user->email)->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('it validates duplicate login ids', function (): void {
    User::factory()->create(['login_id' => 'admin-0001']);

    $this->artisan('admin:create-user', [
        'login_id' => 'admin-0001',
        '--name' => 'Admin User',
        '--email' => 'admin@example.com',
        '--password' => 'password',
    ])->assertFailed();
});
