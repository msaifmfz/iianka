<?php

namespace App\Http\Middleware;

use App\Models\BusinessSchedule;
use App\Models\CleaningDutyRule;
use App\Models\ConstructionSchedule;
use App\Models\InternalNotice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Middleware;
use Override;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    #[Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'attention' => $this->attention($request),
        ];
    }

    /**
     * @return array{schedule_count: int, pending_voucher_count: int, internal_notice_count: int}
     */
    private function attention(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return [
                'schedule_count' => 0,
                'pending_voucher_count' => 0,
                'internal_notice_count' => 0,
            ];
        }

        $today = today()->toDateString();
        $weekday = Carbon::today()->dayOfWeek;

        if ($user->is_admin) {
            return [
                'schedule_count' => ConstructionSchedule::query()->whereDate('scheduled_on', $today)->count()
                    + BusinessSchedule::query()->whereDate('scheduled_on', $today)->count()
                    + InternalNotice::query()->whereDate('scheduled_on', $today)->count()
                    + CleaningDutyRule::query()->where('is_active', true)->where('weekday', $weekday)->count(),
                'pending_voucher_count' => ConstructionSchedule::query()
                    ->whereDate('scheduled_on', '<=', $today)
                    ->whereNull('voucher_checked_at')
                    ->count(),
                'internal_notice_count' => InternalNotice::query()
                    ->whereDate('scheduled_on', $today)
                    ->count(),
            ];
        }

        return [
            'schedule_count' => $user->constructionSchedules()
                ->whereDate('scheduled_on', $today)
                ->count()
                + $user->businessSchedules()
                    ->whereDate('scheduled_on', $today)
                    ->count()
                + $user->internalNotices()
                    ->whereDate('scheduled_on', $today)
                    ->count()
                + $user->cleaningDutyRules()
                    ->where('is_active', true)
                    ->where('weekday', $weekday)
                    ->count(),
            'pending_voucher_count' => $user->constructionSchedules()
                ->whereDate('scheduled_on', '<=', $today)
                ->whereNull('voucher_checked_at')
                ->count(),
            'internal_notice_count' => $user->internalNotices()
                ->whereDate('scheduled_on', $today)
                ->count(),
        ];
    }
}
