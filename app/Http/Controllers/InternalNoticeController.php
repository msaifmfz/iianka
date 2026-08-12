<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesScheduleReturnTo;
use App\Http\Requests\StoreInternalNoticeRequest;
use App\Http\Requests\UpdateInternalNoticeRequest;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InternalNoticeController extends Controller
{
    use HandlesScheduleReturnTo;

    public function index(): RedirectResponse
    {
        return redirect()->route('construction-schedules.index', [
            'type' => 'internal_notice',
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('internal-notices/form', [
            'notice' => null,
            'returnTo' => $this->returnTo($request),
            ...$this->initialFormValues($request),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreInternalNoticeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $notice = DB::transaction(function () use ($request, $validated): InternalNotice {
            $notice = InternalNotice::create($this->noticeAttributes($validated));
            $notice->assignedUsers()->sync($request->input('assigned_user_ids', []));

            return $notice;
        });

        $this->auditSuccess('internal_notices.created', 'An internal notice was created.', $notice, [
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
        ]);

        $this->flashToast('業務連絡を作成しました。', resource: [
            'type' => 'internal_notice',
            'id' => $notice->id,
            'action' => 'created',
            'label' => $notice->title,
        ]);

        return $this->redirectToReturnTo($request, route('construction-schedules.index', [
            'range' => 'today',
            'date' => $notice->scheduled_on->toDateString(),
            'type' => 'internal_notice',
        ]));
    }

    public function show(Request $request, InternalNotice $internalNotice): Response
    {
        $internalNotice->load('assignedUsers:id,name,email');

        return Inertia::render('internal-notices/show', [
            'notice' => $this->noticePayload(collect([$internalNotice]))->first(),
            'canManage' => request()->user()?->canManageContent() === true,
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function edit(Request $request, InternalNotice $internalNotice): Response
    {
        Gate::authorize('manage-content');

        $internalNotice->load('assignedUsers:id,name,email');

        return Inertia::render('internal-notices/form', [
            'notice' => $this->noticePayload(collect([$internalNotice]))->first(),
            'returnTo' => $this->returnTo($request),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateInternalNoticeRequest $request, InternalNotice $internalNotice): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $internalNotice): void {
            $internalNotice->update($this->noticeAttributes($validated));
            $internalNotice->assignedUsers()->sync($request->input('assigned_user_ids', []));
        });

        $this->auditSuccess('internal_notices.updated', 'An internal notice was updated.', $internalNotice, [
            'changed' => array_values(array_diff(array_keys($internalNotice->getChanges()), ['updated_at'])),
            'assigned_user_ids' => $request->input('assigned_user_ids', []),
        ]);

        $this->flashToast('業務連絡を修正しました。', resource: [
            'type' => 'internal_notice',
            'id' => $internalNotice->id,
            'action' => 'updated',
            'label' => $internalNotice->title,
        ]);

        return $this->redirectToReturnTo($request, route('internal-notices.show', $internalNotice));
    }

    public function destroy(Request $request, InternalNotice $internalNotice): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('internal_notices.deleted', 'An internal notice was deleted.', $internalNotice);

        $internalNotice->delete();

        $this->flashToast('業務連絡を削除しました。');

        return $this->redirectToReturnTo($request, route('construction-schedules.index', ['type' => 'internal_notice']));
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
