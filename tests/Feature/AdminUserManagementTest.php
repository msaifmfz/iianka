<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the user management page', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Admin User']);
    $member = User::factory()->create(['name' => 'Member User']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/users/index')
            ->where('stats.total', 2)
            ->where('stats.admins', 1)
            ->has('users.data', 2)
            ->where('users.data', fn ($users): bool => collect($users)->contains(
                fn (array $user): bool => $user['id'] === $member->id && $user['name'] === 'Member User'
            ))
        );
});

test('non admins cannot view user management', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admins can create users', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Member',
            'login_id' => 'new-member',
            'email' => null,
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => false,
            'is_hidden_from_workers' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('login_id', 'new-member')->where('is_admin', false)->exists())->toBeTrue()
        ->and(User::query()->where('login_id', 'new-member')->value('email'))->toBeNull()
        ->and(User::query()->where('login_id', 'new-member')->value('is_hidden_from_workers'))->toBeTrue();
});

test('admins can update user roles', function (): void {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $member), [
            'name' => 'Promoted Member',
            'login_id' => $member->login_id,
            'email' => $member->email,
            'password' => null,
            'password_confirmation' => null,
            'is_admin' => true,
            'is_hidden_from_workers' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $member->refresh();

    expect($member->name)->toBe('Promoted Member')
        ->and($member->is_admin)->toBeTrue()
        ->and($member->is_hidden_from_workers)->toBeTrue();
});

test('admins cannot remove their own admin role', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'login_id' => $admin->login_id,
            'email' => $admin->email,
            'password' => null,
            'password_confirmation' => null,
            'is_admin' => false,
            'is_hidden_from_workers' => false,
        ])
        ->assertSessionHasErrors('is_admin');

    expect($admin->refresh()->is_admin)->toBeTrue();
});

test('admins cannot assign duplicate login ids', function (): void {
    $admin = User::factory()->admin()->create();
    $existingUser = User::factory()->create(['login_id' => 'worker-0009']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Another Member',
            'login_id' => 'worker-0009',
            'email' => null,
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => false,
            'is_hidden_from_workers' => false,
        ])
        ->assertSessionHasErrors('login_id');
});

test('admins can delete other users but not themselves', function (): void {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $member))
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->whereKey($member)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertUnprocessable();

    expect(User::query()->whereKey($admin)->exists())->toBeTrue();
});
