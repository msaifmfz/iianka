<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $search = $request->string('search')->trim()->toString();
        $role = $request->query('role');

        $users = User::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'two_factor_confirmed_at', 'is_admin', 'created_at', 'updated_at'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($role === 'admin', fn ($query) => $query->where('is_admin', true))
            ->when($role === 'member', fn ($query) => $query->where('is_admin', false))
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user): array => $this->userPayload($user, $request->user()));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => is_string($role) ? $role : 'all',
            ],
            'stats' => [
                'total' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
                'members' => User::query()->where('is_admin', false)->count(),
                'secured' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('admin/users/form', [
            'managedUser' => null,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->forceFill([
            'is_admin' => $validated['is_admin'],
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを作成しました。');
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('admin/users/form', [
            'managedUser' => $this->userPayload($user, $request->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $validated['is_admin'],
        ];

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        $user->forceFill($attributes)->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'ユーザーを更新しました。');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_if($request->user()?->is($user), 422, '自分自身は削除できません。');

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
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
            'is_admin' => $user->is_admin,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'is_current_user' => $currentUser?->is($user) === true,
            'can_delete' => $currentUser?->is($user) !== true,
        ];
    }
}
