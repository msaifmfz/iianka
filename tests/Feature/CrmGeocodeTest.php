<?php

use App\Application\Crm\GeocodeAddress;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('the geocoder turns GSI results into lat/lng candidates', function (): void {
    Http::fake([
        GeocodeAddress::ENDPOINT.'*' => Http::response([
            [
                'geometry' => ['coordinates' => [135.499435, 34.698826], 'type' => 'Point'],
                'type' => 'Feature',
                'properties' => ['addressCode' => '', 'title' => '大阪府大阪市北区梅田一丁目１番'],
            ],
            ['geometry' => ['coordinates' => 'broken'], 'properties' => ['title' => 'skip me']],
            ['geometry' => ['coordinates' => [181, 91]], 'properties' => ['title' => 'invalid coordinates']],
            ['geometry' => ['coordinates' => ['1e309', 34]], 'properties' => ['title' => 'overflow']],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('crm.geocode', ['address' => '大阪市北区梅田1-1']))
        ->assertOk()
        ->assertExactJson(['candidates' => [
            ['label' => '大阪府大阪市北区梅田一丁目１番', 'lat' => 34.698826, 'lng' => 135.499435],
        ]]);

    Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), GeocodeAddress::ENDPOINT)
        && $request['q'] === '大阪市北区梅田1-1');
});

test('the geocoder caps candidates', function (): void {
    Http::fake([
        GeocodeAddress::ENDPOINT.'*' => Http::response(array_fill(0, 12, [
            'geometry' => ['coordinates' => [135.0, 34.0]],
            'properties' => ['title' => '候補'],
        ])),
    ]);

    expect(app(GeocodeAddress::class)->candidates('大阪'))->toHaveCount(GeocodeAddress::MAX_CANDIDATES);
});

test('the geocoder returns 503 and logs a warning when GSI cannot be reached', function (): void {
    Http::preventStrayRequests();
    Http::fake([GeocodeAddress::ENDPOINT.'*' => fn () => throw new ConnectionException('timeout')]);
    $log = Log::spy();

    $this->actingAs(User::factory()->create())
        ->getJson(route('crm.geocode', ['address' => '大阪']))
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => '住所検索サービスに接続できませんでした。', 'candidates' => []]);

    $log->shouldHaveReceived('warning')
        ->once()
        ->with('GSI address search failed to connect.', ['exception' => ConnectionException::class, 'message' => 'timeout']);
});

test('the geocoder returns 503 and logs the status when GSI answers with an error or a non-list body', function (string $body, int $status): void {
    Http::preventStrayRequests();
    Http::fake([GeocodeAddress::ENDPOINT.'*' => Http::response($body, $status)]);
    $log = Log::spy();

    $this->actingAs(User::factory()->create())
        ->getJson(route('crm.geocode', ['address' => '大阪']))
        ->assertServiceUnavailable()
        ->assertJsonPath('candidates', []);

    $log->shouldHaveReceived('warning')
        ->once()
        ->with('GSI address search returned an error.', ['status' => $status]);
})->with([
    'server error' => ['oops', 500],
    'HTML page with 200' => ['<html>maintenance</html>', 200],
]);

test('the geocoder returns 200 with no candidates when GSI finds no match', function (): void {
    Http::preventStrayRequests();
    Http::fake([GeocodeAddress::ENDPOINT.'*' => Http::response([])]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('crm.geocode', ['address' => '存在しない住所']))
        ->assertOk()
        ->assertExactJson(['candidates' => []]);
});

test('geocoding requires an address and a signed-in user', function (): void {
    Http::fake();

    $this->getJson(route('crm.geocode', ['address' => '大阪']))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson(route('crm.geocode'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('address');

    Http::assertNothingSent();
});
