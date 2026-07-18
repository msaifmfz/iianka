<?php

declare(strict_types=1);

namespace App\Application\Reception;

use App\Models\ReceptionCase;
use App\Models\ReceptionCaseSequence;
use App\Services\BusinessDate;
use Illuminate\Support\Facades\DB;

class ReceptionCaseNumberGenerator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $today = BusinessDate::today();
            $sequenceDate = $today->toDateString();

            // Ensure today's row exists. The first-of-day insert can lose a race
            // (unique constraint on sequence_date); the caller's retry loop handles
            // that. Once it exists, every increment goes through the locked read below.
            ReceptionCaseSequence::query()->firstOrCreate(
                ['sequence_date' => $sequenceDate],
                ['last_number' => 0],
            );

            // Lock the row before reading so the value reflects committed state:
            // concurrent creators serialize here, each seeing the true prior number.
            // (firstOrCreate's read is unlocked and increment()'s in-memory value is
            // loaded_value + 1, so without this a blocked-then-resumed writer would
            // reuse a stale number and collide on the case_number unique index.)
            $sequence = ReceptionCaseSequence::query()
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return sprintf(
                '%s-%s-%04d',
                ReceptionCase::CASE_NUMBER_PREFIX,
                $today->format('Ymd'),
                $sequence->last_number,
            );
        });
    }
}
