<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $month = Carbon::parse($request->query('month', today()->toDateString()))->startOfMonth();
        $startsOn = $month->copy()->startOfMonth();
        $endsOn = $month->copy()->endOfMonth();
        $canManage = $request->user()?->canManageContent() === true;

        $users = User::query()
            ->visibleToWorkers()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_hidden_from_workers']);

        $records = AttendanceRecord::query()
            ->with('user:id,name,email,is_hidden_from_workers')
            ->whereHas('user', fn ($query) => $query->visibleToWorkers())
            ->whereDate('work_date', '>=', $startsOn->toDateString())
            ->whereDate('work_date', '<=', $endsOn->toDateString())
            ->orderBy('work_date')
            ->get();

        return Inertia::render('attendance-records/index', [
            'filters' => [
                'month' => $month->toDateString(),
                'previous_month' => $month->copy()->subMonthNoOverflow()->toDateString(),
                'next_month' => $month->copy()->addMonthNoOverflow()->toDateString(),
            ],
            'days' => $this->days($startsOn, $endsOn),
            'users' => $users,
            'records' => $this->recordPayload($records),
            'stats' => [
                'working' => $records->where('status', AttendanceRecord::STATUS_WORKING)->count(),
                'leave' => $records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                'unmarked' => max(0, ($users->count() * $startsOn->daysInMonth) - $records->count()),
            ],
            'canManage' => $canManage,
        ]);
    }

    public function store(StoreAttendanceRecordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $workDate = Carbon::parse($validated['work_date'])->startOfDay();

        $record = AttendanceRecord::query()->updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'work_date' => $workDate,
            ],
            [
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
            ],
        );

        $this->auditSuccess('attendance_records.updated', 'An attendance record was updated.', $record, [
            'user_id' => $record->user_id,
            'work_date' => $record->work_date->toDateString(),
            'status' => $record->status,
        ]);

        return back()->with('status', '出勤状況を更新しました。');
    }

    public function destroy(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        abort_unless($request->user()?->canManageContent() === true, 403);

        $this->auditSuccess('attendance_records.deleted', 'An attendance record was deleted.', $attendanceRecord, [
            'user_id' => $attendanceRecord->user_id,
            'work_date' => $attendanceRecord->work_date->toDateString(),
        ]);

        $attendanceRecord->delete();

        return back()->with('status', '出勤状況を未設定に戻しました。');
    }

    /**
     * @return Collection<int, array{date: string, day: int, weekday: string, is_weekend: bool, is_today: bool}>
     */
    private function days(Carbon $startsOn, Carbon $endsOn): Collection
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        return collect()
            ->range(0, (int) $startsOn->diffInDays($endsOn))
            ->map(function (int $offset) use ($startsOn, $weekdays): array {
                $date = $startsOn->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'weekday' => $weekdays[$date->dayOfWeek],
                    'is_weekend' => $date->isWeekend(),
                    'is_today' => $date->isToday(),
                ];
            });
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private function recordPayload(Collection $records): Collection
    {
        return $records->map(fn (AttendanceRecord $record): array => [
            'id' => $record->id,
            'user_id' => $record->user_id,
            'work_date' => $record->work_date->toDateString(),
            'status' => $record->status,
            'note' => $record->note,
            'user' => [
                'id' => $record->user->id,
                'name' => $record->user->name,
                'email' => $record->user->email,
                'is_hidden_from_workers' => $record->user->is_hidden_from_workers,
            ],
        ])->values();
    }
}
