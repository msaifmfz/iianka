<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceSheetExporter;
use App\Services\BusinessDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $this->selectedMonth($request);
        [$startsOn, $endsOn] = $this->period($month);
        $canManage = $request->user()?->canManageContent() === true;
        $days = $this->days($startsOn, $endsOn);
        $users = $this->visibleUsers();
        $records = $this->periodRecords($startsOn, $endsOn);

        return Inertia::render('attendance-records/index', [
            'filters' => [
                'month' => $month->toDateString(),
                'previous_month' => $month->copy()->subMonthNoOverflow()->toDateString(),
                'next_month' => $month->copy()->addMonthNoOverflow()->toDateString(),
            ],
            'days' => $days,
            'users' => $users,
            'records' => $this->recordPayload($records),
            'userTotals' => $this->userTotals($records),
            'stats' => [
                'working' => $records->where('status', AttendanceRecord::STATUS_WORKING)->count(),
                'early_out' => $records->where('status', AttendanceRecord::STATUS_EARLY_OUT)->count(),
                'leave' => $records->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
                'unmarked' => max(0, ($users->count() * $days->count()) - $records->count()),
            ],
            'canManage' => $canManage,
        ]);
    }

    public function export(Request $request, AttendanceSheetExporter $exporter): StreamedResponse
    {
        [$startsOn, $endsOn] = $this->period($this->selectedMonth($request));

        $spreadsheet = $exporter->build(
            $startsOn,
            $endsOn,
            $this->visibleUsers(),
            $this->periodRecords($startsOn, $endsOn),
        );

        $filename = "attendance_{$startsOn->toDateString()}_{$endsOn->toDateString()}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet): void {
            new Xlsx($spreadsheet)->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

        $record->load('user:id,name,email,is_hidden_from_workers');

        $this->flashToast('出勤状況を更新しました。', resource: [
            'type' => 'attendance_cell',
            'id' => "{$record->user_id}-{$record->work_date->toDateString()}",
            'action' => 'saved',
            'label' => "{$record->user->name} / {$record->work_date->toDateString()}",
        ]);

        return back();
    }

    public function destroy(AttendanceRecord $attendanceRecord): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('attendance_records.deleted', 'An attendance record was deleted.', $attendanceRecord, [
            'user_id' => $attendanceRecord->user_id,
            'work_date' => $attendanceRecord->work_date->toDateString(),
        ]);

        $attendanceRecord->delete();

        $this->flashToast('出勤状況を未設定に戻しました。');

        return back();
    }

    private function selectedMonth(Request $request): Carbon
    {
        $requestedMonth = $request->query('month');

        if (! is_string($requestedMonth) || $requestedMonth === '') {
            return $this->defaultMonth();
        }

        try {
            return Carbon::parse($requestedMonth)->startOfMonth();
        } catch (Throwable) {
            return $this->defaultMonth();
        }
    }

    /**
     * The attendance period runs from the 21st of the selected month to the
     * 20th of the next.
     *
     * @return array{Carbon, Carbon}
     */
    private function period(Carbon $month): array
    {
        return [
            $month->copy()->day(21),
            $month->copy()->addMonthNoOverflow()->day(20),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function visibleUsers(): Collection
    {
        return User::query()
            ->visibleToWorkers()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_hidden_from_workers']);
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    private function periodRecords(Carbon $startsOn, Carbon $endsOn): Collection
    {
        return AttendanceRecord::query()
            ->with('user:id,name,email,is_hidden_from_workers')
            ->whereHas('user', fn (Builder $query) => $query->visibleToWorkers())
            ->whereDate('work_date', '>=', $startsOn->toDateString())
            ->whereDate('work_date', '<=', $endsOn->toDateString())
            ->orderBy('work_date')
            ->get();
    }

    private function defaultMonth(): Carbon
    {
        $today = BusinessDate::today();

        if ($today->day <= 20) {
            return $today->subMonthNoOverflow()->startOfMonth();
        }

        return $today->startOfMonth();
    }

    /**
     * @return Collection<int, array{date: string, day: int, weekday: string, is_weekend: bool, is_today: bool}>
     */
    private function days(Carbon $startsOn, Carbon $endsOn): Collection
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $today = BusinessDate::today()->toDateString();

        return collect()
            ->range(0, (int) $startsOn->diffInDays($endsOn))
            ->map(function (int $offset) use ($startsOn, $today, $weekdays): array {
                $date = $startsOn->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'day' => $date->day,
                    'weekday' => $weekdays[$date->dayOfWeek],
                    'is_weekend' => $date->isWeekend(),
                    'is_today' => $date->toDateString() === $today,
                ];
            });
    }

    /**
     * Per-user 出勤日数 and 早退 counts for the month.
     *
     * Derived here rather than in the page because which statuses count as a
     * worked day is a payroll rule, not a presentation one: keeping it beside
     * AttendanceRecord::WORKED_DAY_STATUSES gives it one home and lets a feature
     * test prove it, which nothing on the client currently can.
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return list<array{user_id: int, worked_days: int, early_out_days: int}>
     */
    private function userTotals(Collection $records): array
    {
        $totals = [];

        foreach ($records as $record) {
            $userId = (int) $record->user_id;
            $totals[$userId] ??= [
                'user_id' => $userId,
                'worked_days' => 0,
                'early_out_days' => 0,
            ];

            if ($record->countsAsWorkedDay()) {
                $totals[$userId]['worked_days']++;
            }

            if ($record->status === AttendanceRecord::STATUS_EARLY_OUT) {
                $totals[$userId]['early_out_days']++;
            }
        }

        return array_values($totals);
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
