<?php

declare(strict_types=1);

use App\Services\BusinessDate;
use Illuminate\Support\Carbon;

test('business today uses the Japan calendar date while the app timezone remains UTC', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-13 22:00:00', 'UTC'));

    try {
        expect(Carbon::today('UTC')->toDateString())->toBe('2026-04-13')
            ->and(BusinessDate::today()->toDateString())->toBe('2026-04-14');
    } finally {
        Carbon::setTestNow();
    }
});
