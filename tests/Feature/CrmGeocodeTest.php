<?php

use App\Application\Crm\GeocodeAddress;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

test('the geocoder returns no candidates when GSI is down', function (): void {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(app(GeocodeAddress::class)->candidates('大阪'))->toBe([]);

    Http::fake([GeocodeAddress::ENDPOINT.'*' => Http::response('oops', 500)]);

    expect(app(GeocodeAddress::class)->candidates('大阪'))->toBe([]);
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
