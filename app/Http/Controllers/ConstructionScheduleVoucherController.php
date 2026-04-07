<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConstructionScheduleVoucherRequest;
use App\Models\ConstructionSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionScheduleVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $checked = in_array($request->query('checked'), ['all', 'unchecked', 'checked'], true)
            ? $request->query('checked')
            : 'all';
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $startsOn = $date->copy()->startOfMonth();
        $endsOn = $date->copy()->endOfMonth();

        $query = ConstructionSchedule::query()
            ->with(['assignedUsers:id,name,email', 'voucherCheckedBy:id,name,email'])
            ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
            ->whereDate('scheduled_on', '<=', $endsOn->toDateString());

        if ($checked === 'checked') {
            $query->whereNotNull('voucher_checked_at');
        }

        if ($checked === 'unchecked') {
            $query->whereNull('voucher_checked_at');
        }

        $schedules = $query
            ->orderBy('scheduled_on')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('voucher-confirmations/index', [
            'filters' => [
                'checked' => $checked,
                'date' => $date->toDateString(),
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
            ],
            'summary' => [
                'total' => $schedules->count(),
                'checked' => $schedules->whereNotNull('voucher_checked_at')->count(),
                'unchecked' => $schedules->whereNull('voucher_checked_at')->count(),
            ],
            'schedules' => $this->schedulePayload($schedules),
            'canManage' => $request->user()?->is_admin === true,
        ]);
    }

    public function update(
        UpdateConstructionScheduleVoucherRequest $request,
        ConstructionSchedule $constructionSchedule
    ): RedirectResponse {
        $validated = $request->validated();
        $voucherChecked = (bool) $validated['voucher_checked'];

        $constructionSchedule->update([
            'voucher_note' => $validated['voucher_note'] ?? null,
            'voucher_checked_at' => $voucherChecked ? now() : null,
            'voucher_checked_by_user_id' => $voucherChecked ? $request->user()?->id : null,
        ]);

        return back()->with('status', '伝票確認を更新しました。');
    }

    /**
     * @param  Collection<int, ConstructionSchedule>  $schedules
     * @return Collection<int, array<string, mixed>>
     */
    private function schedulePayload(Collection $schedules): Collection
    {
        return $schedules->map(fn (ConstructionSchedule $schedule): array => [
            'id' => $schedule->id,
            'scheduled_on' => $schedule->scheduled_on->toDateString(),
            'time' => $schedule->formattedTime(),
            'starts_at' => $schedule->starts_at,
            'location' => $schedule->location,
            'general_contractor' => $schedule->general_contractor,
            'person_in_charge' => $schedule->person_in_charge,
            'content' => $schedule->content,
            'voucher_checked' => $schedule->voucher_checked_at !== null,
            'voucher_checked_at' => $schedule->voucher_checked_at?->toDateTimeString(),
            'voucher_checked_by' => $schedule->voucherCheckedBy === null ? null : [
                'id' => $schedule->voucherCheckedBy->id,
                'name' => $schedule->voucherCheckedBy->name,
                'email' => $schedule->voucherCheckedBy->email,
            ],
            'voucher_note' => $schedule->voucher_note,
            'assigned_users' => $schedule->assignedUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ])->values();
    }
}
