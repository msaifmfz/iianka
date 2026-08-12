<?php

declare(strict_types=1);

namespace App\Application\Stock\Queries;

use App\Domain\Stock\Enums\ScheduleStockSourceType;
use App\Domain\Stock\ValueObjects\StockTerm;
use App\Models\ConstructionSchedule;
use App\Models\ScheduleStockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The construction schedules behind a term report's usage figures, for the two
 * terms the report shows.
 *
 * Both the references the report rows carry and the schedules a page loads to
 * present them come from one query here, so the two cannot drift apart: every
 * reference resolves to a schedule previewable() returns. The summary totals
 * remain the source of truth for all historical usage; these references are
 * deliberately limited to the two visible terms.
 */
final readonly class UsageScheduleQuery
{
    /**
     * Which schedules consumed which stock, per usage bucket.
     */
    public function references(StockTerm $firstTerm, StockTerm $secondTerm): UsageScheduleReferences
    {
        $schedules = $this->query($firstTerm, $secondTerm)->get(['id', 'scheduled_on']);

        if ($schedules->isEmpty()) {
            return new UsageScheduleReferences([]);
        }

        $balances = ScheduleStockBalance::query()
            ->where('schedule_type', ScheduleStockSourceType::ConstructionSchedule)
            ->whereIn('schedule_id', $schedules->modelKeys())
            ->where('applied_quantity', '!=', 0)
            ->get(['stock_id', 'schedule_id', 'applied_quantity'])
            ->toBase()
            ->groupBy('schedule_id');

        $references = [];

        // Driven by the ordered schedules rather than the balance rows, whose
        // own order is insertion order: every bucket's list comes out in the
        // same order the report displays its schedules in.
        foreach ($schedules as $schedule) {
            $bucketStart = $this->bucketStart($schedule, $firstTerm, $secondTerm);

            if ($bucketStart === null) {
                continue;
            }

            $scheduleBalances = $balances->get($schedule->id);

            if ($scheduleBalances === null) {
                continue;
            }

            foreach ($scheduleBalances as $balance) {
                $references[$balance->stock_id][$bucketStart][] = [
                    'schedule_id' => $balance->schedule_id,
                    'quantity' => $balance->applied_quantity,
                ];
            }
        }

        return new UsageScheduleReferences($references);
    }

    /**
     * The same schedules, loaded ready to be presented.
     *
     * @return EloquentCollection<int, ConstructionSchedule>
     */
    public function previewable(StockTerm $firstTerm, StockTerm $secondTerm): EloquentCollection
    {
        return $this->query($firstTerm, $secondTerm)
            ->with('assignedUsers:id,name,email,is_hidden_from_workers')
            ->get([
                'id',
                'scheduled_on',
                'schedule_number',
                'starts_at',
                'ends_at',
                'time_note',
                'status',
                'location',
                'general_contractor',
                'content',
                'carry_out_note',
            ]);
    }

    /**
     * Construction schedules dated inside the two terms that hold a non-zero
     * stock balance, in display order.
     *
     * @return Builder<ConstructionSchedule>
     */
    private function query(StockTerm $firstTerm, StockTerm $secondTerm): Builder
    {
        $usageScheduleIds = ScheduleStockBalance::query()
            ->select('schedule_id')
            ->where('schedule_type', ScheduleStockSourceType::ConstructionSchedule)
            ->where('applied_quantity', '!=', 0);

        return ConstructionSchedule::query()
            ->whereIn('id', $usageScheduleIds)
            ->where('scheduled_on', '>=', $firstTerm->startsOn()->toDateString())
            ->where('scheduled_on', '<', $secondTerm->endExclusive()->toDateString())
            ->orderBy('scheduled_on')
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * Business dates are compared as `Y-m-d` strings, matching how the report's
     * usage sums and the window filter above bucket them. Comparing the Carbon
     * instances instead would pit UTC midnight (the `scheduled_on` date cast)
     * against Tokyo midnight (the term buckets) and could drift a boundary-day
     * schedule into a bucket its own usage total does not belong to.
     */
    private function bucketStart(
        ConstructionSchedule $schedule,
        StockTerm $firstTerm,
        StockTerm $secondTerm,
    ): ?string {
        $scheduledOn = $schedule->scheduled_on->toDateString();

        foreach ([...$firstTerm->buckets(), ...$secondTerm->buckets()] as $bucket) {
            $bucketStart = $bucket['starts_on']->toDateString();

            if ($scheduledOn >= $bucketStart && $scheduledOn < $bucket['end_exclusive']->toDateString()) {
                return $bucketStart;
            }
        }

        return null;
    }
}
