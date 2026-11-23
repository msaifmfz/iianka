<?php

declare(strict_types=1);

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $connection
 * @param  array<string, mixed>  $input
 * @return Closure(): array{int, string|false|null}
 */
function concurrentQuotationStoreTask(bool $first, array $connection, int $editorId, array $input, string $directory): Closure
{
    return static function () use ($first, $connection, $editorId, $input, $directory): array {
        config(['database.connections.quotation_concurrency' => $connection, 'session.driver' => 'array']);
        DB::setDefaultConnection('quotation_concurrency');
        Auth::setUser(User::query()->findOrFail($editorId));

        $waitFor = static function (string $marker) use ($directory): void {
            retry(500, static function () use ($directory, $marker): void {
                if (! is_file($directory.'/'.$marker)) {
                    throw new RuntimeException('Timed out waiting for '.$marker);
                }
            }, 10);
        };

        // Hold the first write open until the second request reaches its insert.
        if ($first) {
            Quotation::created(static function () use ($directory, $waitFor): void {
                touch($directory.'/first-inserted');
                $waitFor('second-inserting');
            });
        } else {
            $waitFor('first-inserted');
            Quotation::creating(static function () use ($directory): void {
                touch($directory.'/second-inserting');
            });
        }

        $request = Request::create(route('quotations.store'), 'POST', $input, server: ['HTTP_ACCEPT' => 'application/json']);
        $response = app(Kernel::class)->handle($request);

        return [$response->getStatusCode(), parse_url((string) $response->headers->get('Location'), PHP_URL_PATH)];
    };
}

test('concurrent quotation saves receive consecutive numbers for the same issue date', function (): void {
    $directory = sys_get_temp_dir().'/quotation-'.Str::uuid();
    File::makeDirectory($directory);
    $connection = [
        ...config('database.connections.sqlite'),
        'url' => null,
        'database' => $directory.'/database.sqlite',
        'busy_timeout' => 10000,
    ];

    try {
        File::put($connection['database'], '');
        config(['database.connections.quotation_concurrency' => $connection]);
        expect(Artisan::call('migrate', ['--database' => 'quotation_concurrency', '--force' => true]))->toBe(0);
        $editor = User::factory()->editor()->connection('quotation_concurrency')->create();
        $input = [...Quotation::factory()->for($editor, 'creator')->make()->input, 'issued_on' => '2026-11-01'];

        $responses = Concurrency::driver('process')->run([
            concurrentQuotationStoreTask(true, $connection, $editor->id, $input, $directory),
            concurrentQuotationStoreTask(false, $connection, $editor->id, $input, $directory),
        ], timeout: 20);

        expect($responses)->toBe([
            [302, route('quotations.show', 1, absolute: false)],
            [302, route('quotations.show', 2, absolute: false)],
        ]);
        expect(Quotation::on('quotation_concurrency')->orderBy('id')->pluck('number')->all())
            ->toBe(['Q-20261101-00001', 'Q-20261101-00002']);
    } finally {
        DB::purge('quotation_concurrency');
        File::deleteDirectory($directory);
    }
});
