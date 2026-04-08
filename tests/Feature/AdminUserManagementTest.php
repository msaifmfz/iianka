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
            'email' => 'new-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => false,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->where('email', 'new-member@example.com')->where('is_admin', false)->exists())->toBeTrue();
});

test('admins can update user roles', function (): void {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $member), [
            'name' => 'Promoted Member',
            'email' => $member->email,
            'password' => null,
            'password_confirmation' => null,
            'is_admin' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $member->refresh();

    expect($member->name)->toBe('Promoted Member')
        ->and($member->is_admin)->toBeTrue();
});

test('admins cannot remove their own admin role', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => null,
            'password_confirmation' => null,
            'is_admin' => false,
        ])
        ->assertSessionHasErrors('is_admin');

    expect($admin->refresh()->is_admin)->toBeTrue();
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
