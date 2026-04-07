<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternalNoticeRequest;
use App\Http\Requests\UpdateInternalNoticeRequest;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InternalNoticeController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('construction-schedules.index', [
            'type' => 'internal_notice',
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('internal-notices/form', [
            'notice' => null,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreInternalNoticeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $notice = InternalNotice::create($this->noticeAttributes($validated));
        $notice->assignedUsers()->sync($request->input('assigned_user_ids', []));

        return redirect()
            ->route('construction-schedules.index', [
                'range' => 'today',
                'date' => $notice->scheduled_on->toDateString(),
                'type' => 'internal_notice',
            ])
            ->with('status', '業務連絡を作成しました。');
    }

    public function show(InternalNotice $internalNotice): Response
    {
        $internalNotice->load('assignedUsers:id,name,email');

        return Inertia::render('internal-notices/show', [
            'notice' => $this->noticePayload(collect([$internalNotice]))->first(),
            'canManage' => request()->user()?->is_admin === true,
        ]);
    }

    public function edit(Request $request, InternalNotice $internalNotice): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $internalNotice->load('assignedUsers:id,name,email');

        return Inertia::render('internal-notices/form', [
            'notice' => $this->noticePayload(collect([$internalNotice]))->first(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateInternalNoticeRequest $request, InternalNotice $internalNotice): RedirectResponse
    {
        $validated = $request->validated();
        $internalNotice->update($this->noticeAttributes($validated));
        $internalNotice->assignedUsers()->sync($request->input('assigned_user_ids', []));

        return redirect()
            ->route('internal-notices.show', $internalNotice)
            ->with('status', '業務連絡を更新しました。');
    }

    public function destroy(Request $request, InternalNotice $internalNotice): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $internalNotice->delete();

        return redirect()
            ->route('construction-schedules.index', ['type' => 'internal_notice'])
            ->with('status', '業務連絡を削除しました。');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function noticeAttributes(array $validated): array
    {
        return collect($validated)
            ->only([
                'scheduled_on',
                'starts_at',
                'ends_at',
                'time_note',
                'title',
                'location',
                'content',
                'memo',
            ])
            ->all();
    }

    /**
     * @param  Collection<int, InternalNotice>  $notices
     * @return Collection<int, array<string, mixed>>
     */
    private function noticePayload(Collection $notices): Collection
    {
        return $notices->map(fn (InternalNotice $notice): array => [
            'id' => $notice->id,
            'type' => 'internal_notice',
            'scheduled_on' => $notice->scheduled_on->toDateString(),
            'time' => $notice->formattedTime(),
            'starts_at' => $notice->starts_at,
            'ends_at' => $notice->ends_at,
            'time_note' => $notice->time_note,
            'title' => $notice->title,
            'location' => $notice->location,
            'content' => $notice->content,
            'memo' => $notice->memo,
            'assigned_users' => $notice->assignedUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ])->values();
    }
}
