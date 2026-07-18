<?php

declare(strict_types=1);

use App\Domain\Stock\ValueObjects\StockTerm;
use Illuminate\Support\Carbon;

test('containing assigns days 1-20 to the previous month term', function (string $date, string $expectedStart): void {
    expect(StockTerm::containing(Carbon::parse($date))->startsOn()->toDateString())->toBe($expectedStart);
})->with([
    'day 20 belongs to previous month term' => ['2026-07-20', '2026-06-21'],
    'day 21 starts a new term' => ['2026-07-21', '2026-07-21'],
    'first of month belongs to previous month term' => ['2026-07-01', '2026-06-21'],
    'january 20 crosses the year boundary' => ['2026-01-20', '2025-12-21'],
    'december 21 starts the december term' => ['2026-12-21', '2026-12-21'],
]);

test('fromMonth builds the term starting on that month 21st', function (): void {
    $term = StockTerm::fromMonth('2026-06-01');

    expect($term->startsOn()->toDateString())->toBe('2026-06-21')
        ->and($term->endsOn()->toDateString())->toBe('2026-07-20')
        ->and($term->endExclusive()->toDateString())->toBe('2026-07-21')
        ->and($term->monthParam())->toBe('2026-06-01')
        ->and($term->label())->toBe('2026年6月度')
        ->and($term->rangeLabel())->toBe('6/21〜7/20');
});

test('next and previous move one term month', function (): void {
    $term = StockTerm::fromMonth('2026-06-01');

    expect($term->next()->startsOn()->toDateString())->toBe('2026-07-21')
        ->and($term->previous()->startsOn()->toDateString())->toBe('2026-05-21')
        ->and($term->next()->previous()->startsOn()->toDateString())->toBe('2026-06-21');
});

test('buckets cover the term as half-open ranges', function (string $month, array $expected): void {
    $buckets = StockTerm::fromMonth($month)->buckets();

    $actual = array_map(fn (array $bucket): array => [
        $bucket['starts_on']->toDateString(),
        $bucket['end_exclusive']->toDateString(),
        $bucket['label'],
    ], $buckets);

    expect($actual)->toBe($expected);
})->with([
    '31-day start month' => ['2026-01-01', [
        ['2026-01-21', '2026-02-01', '21日〜月末'],
        ['2026-02-01', '2026-02-11', '1日〜10日'],
        ['2026-02-11', '2026-02-21', '11日〜20日'],
    ]],
    'february term in a non-leap year' => ['2026-02-01', [
        ['2026-02-21', '2026-03-01', '21日〜月末'],
        ['2026-03-01', '2026-03-11', '1日〜10日'],
        ['2026-03-11', '2026-03-21', '11日〜20日'],
    ]],
    'february term in a leap year' => ['2028-02-01', [
        ['2028-02-21', '2028-03-01', '21日〜月末'],
        ['2028-03-01', '2028-03-11', '1日〜10日'],
        ['2028-03-11', '2028-03-21', '11日〜20日'],
    ]],
    '30-day start month' => ['2026-06-01', [
        ['2026-06-21', '2026-07-01', '21日〜月末'],
        ['2026-07-01', '2026-07-11', '1日〜10日'],
        ['2026-07-11', '2026-07-21', '11日〜20日'],
    ]],
    'december term crosses the year boundary' => ['2026-12-01', [
        ['2026-12-21', '2027-01-01', '21日〜月末'],
        ['2027-01-01', '2027-01-11', '1日〜10日'],
        ['2027-01-11', '2027-01-21', '11日〜20日'],
    ]],
]);

test('current uses the business date in tokyo', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'Asia/Tokyo'));

    expect(StockTerm::current()->startsOn()->toDateString())->toBe('2026-06-21');

    Carbon::setTestNow(Carbon::parse('2026-07-21 00:00:00', 'Asia/Tokyo'));

    expect(StockTerm::current()->startsOn()->toDateString())->toBe('2026-07-21');

    Carbon::setTestNow();
});
