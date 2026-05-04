<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConstructionScheduleVoucherRequest;
use App\Models\ConstructionSchedule;
use App\Models\User;
use App\Services\BusinessDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ConstructionScheduleVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $checked = in_array($request->query('checked'), ['all', 'unchecked', 'checked'], true)
            ? $request->query('checked')
            : 'all';
        $today = BusinessDate::today();
        $requestedDay = $this->dateQuery($request->query('day'));
        $date = $this->dateQuery($request->query('date'), $requestedDay ?? $today);
        $startsOn = $date->copy()->startOfMonth();
        $endsOn = $date->copy()->endOfMonth();
        $day = match (true) {
            $request->query('day') === 'all' => 'all',
            $requestedDay !== null => $requestedDay->toDateString(),
            $request->query('date') !== null => 'all',
            default => $today->toDateString(),
        };

        if ($day !== 'all' && ! Carbon::parse($day)->betweenIncluded($startsOn, $endsOn)) {
            $day = 'all';
        }

        $monthlyQuery = ConstructionSchedule::query()
            ->requiresVoucherConfirmation()
            ->whereDate('scheduled_on', '>=', $startsOn->toDateString())
            ->whereDate('scheduled_on', '<=', $endsOn->toDateString());

        $query = (clone $monthlyQuery)
            ->with(['assignedUsers:id,name,email', 'voucherCheckedBy:id,name,email']);

        if ($checked === 'checked') {
            $query->whereNotNull('voucher_checked_at');
        }

        if ($checked === 'unchecked') {
            $query->whereNull('voucher_checked_at');
        }

        if ($day !== 'all') {
            $query->whereDate('scheduled_on', $day);
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
                'day' => $day,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'today' => $today->toDateString(),
            ],
            'summary' => [
                'total' => (clone $monthlyQuery)->count(),
                'checked' => (clone $monthlyQuery)->whereNotNull('voucher_checked_at')->count(),
                'unchecked' => (clone $monthlyQuery)->whereNull('voucher_checked_at')->count(),
            ],
            'dayOptions' => $this->dayOptions($monthlyQuery),
            'schedules' => $this->schedulePayload($schedules),
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function update(
        UpdateConstructionScheduleVoucherRequest $request,
        ConstructionSchedule $constructionSchedule
    ): RedirectResponse {
        abort_unless($constructionSchedule->requiresVoucherConfirmation(), 404);

        $validated = $request->validated();
        $voucherChecked = (bool) $validated['voucher_checked'];

        $constructionSchedule->update([
            'voucher_note' => $validated['voucher_note'] ?? null,
            'voucher_checked_at' => $voucherChecked ? now() : null,
            'voucher_checked_by_user_id' => $voucherChecked ? $request->user()?->id : null,
        ]);

        $this->auditSuccess('construction_schedules.voucher_updated', 'A construction schedule voucher confirmation was updated.', $constructionSchedule, [
            'voucher_checked' => $voucherChecked,
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
            'voucher_checked_at' => $schedule->voucher_checked_at?->toJSON(),
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

    private function dateQuery(mixed $value, ?Carbon $fallback = null): ?Carbon
    {
        if (! is_string($value)) {
            return $fallback?->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, 'Asia/Tokyo')->startOfDay();
        } catch (Throwable) {
            return $fallback?->copy();
        }
    }

    /**
     * @return Collection<int, array{date: string, total: int, checked: int, unchecked: int}>
     */
    private function dayOptions(Builder $monthlyQuery): Collection
    {
        return (clone $monthlyQuery)
            ->selectRaw('scheduled_on, count(*) as total, sum(case when voucher_checked_at is null then 1 else 0 end) as unchecked, sum(case when voucher_checked_at is not null then 1 else 0 end) as checked')
            ->groupBy('scheduled_on')
            ->orderBy('scheduled_on')
            ->get()
            ->map(fn (ConstructionSchedule $schedule): array => [
                'date' => $schedule->scheduled_on->toDateString(),
                'total' => (int) $schedule->total,
                'checked' => (int) $schedule->checked,
                'unchecked' => (int) $schedule->unchecked,
            ])
            ->values();
    }
}
