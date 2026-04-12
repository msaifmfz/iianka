<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canManageUsers() === true, 403);

        $search = $request->string('search')->trim()->toString();
        $role = $request->query('role');
        $selectedRole = is_string($role) && UserRole::tryFrom($role) instanceof UserRole
            ? $role
            : 'all';

        $users = User::query()
            ->select(['id', 'name', 'login_id', 'email', 'email_verified_at', 'two_factor_confirmed_at', 'role', 'is_admin', 'is_hidden_from_workers', 'created_at', 'updated_at'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('login_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($selectedRole !== 'all', fn ($query) => $query->where('role', $selectedRole))
            ->orderByRaw('case role when ? then 0 when ? then 1 else 2 end', [
                UserRole::Admin->value,
                UserRole::Editor->value,
            ])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user): array => $this->userPayload($user, $request->user()));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $selectedRole,
            ],
            'stats' => [
                'total' => User::query()->count(),
                'admins' => User::query()->where('role', UserRole::Admin->value)->count(),
                'editors' => User::query()->where('role', UserRole::Editor->value)->count(),
                'viewers' => User::query()->where('role', UserRole::Viewer->value)->count(),
                'secured' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canManageUsers() === true, 403);

        return Inertia::render('admin/users/form', [
            'managedUser' => null,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'login_id' => $validated['login_id'],
            'email' => $validated['email'] ?: null,
            'password' => $validated['password'],
        ]);
        $role = UserRole::from($validated['role']);
        $user->forceFill([
            'role' => $role,
            'is_admin' => $role === UserRole::Admin,
            'is_hidden_from_workers' => $validated['is_hidden_from_workers'],
        ])->save();

        $this->auditSuccess('admin.users.created', 'An admin created a user account.', $user, [
            'role' => $user->role->value,
            'is_hidden_from_workers' => $user->is_hidden_from_workers,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを作成しました。');
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($request->user()?->canManageUsers() === true, 403);

        return Inertia::render('admin/users/form', [
            'managedUser' => $this->userPayload($user, $request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $role = UserRole::from($validated['role']);

        $attributes = [
            'name' => $validated['name'],
            'login_id' => $validated['login_id'],
            'email' => $validated['email'] ?: null,
            'role' => $role,
            'is_admin' => $role === UserRole::Admin,
            'is_hidden_from_workers' => $validated['is_hidden_from_workers'],
        ];

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        $user->forceFill($attributes)->save();

        $this->auditSuccess('admin.users.updated', 'An admin updated a user account.', $user, [
            'changed' => array_values(array_diff(array_keys($user->getChanges()), ['updated_at'])),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを更新しました。');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers() === true, 403);
        abort_if($request->user()?->is($user), 422, '自分自身は削除できません。');

        $this->auditSuccess('admin.users.deleted', 'An admin deleted a user account.', $user);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを削除しました。');
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user, ?User $currentUser): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'login_id' => $user->login_id,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'is_admin' => $user->is_admin,
            'is_hidden_from_workers' => $user->is_hidden_from_workers,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'is_current_user' => $currentUser?->is($user) === true,
            'can_delete' => $currentUser?->is($user) !== true,
        ];
    }
}
