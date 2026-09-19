<?php

declare(strict_types=1);

namespace App\Application\Crm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a Japanese address into map coordinates with the Geospatial
 * Information Authority of Japan (国土地理院) address search. It is free and
 * needs no API key, but only knows Japanese addresses.
 *
 * Proxied through the server rather than called from the browser so the
 * dependency is testable and the provider can change without a frontend
 * release. A failure returns no candidates: staff can still place the pin
 * by hand.
 */
final readonly class GeocodeAddress
{
    public const string ENDPOINT = 'https://msearch.gsi.go.jp/address-search/AddressSearch';

    public const int MAX_CANDIDATES = 5;

    /**
     * @return list<array{label: string, lat: float, lng: float}>
     */
    public function candidates(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [];
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, ['q' => $address]);
        } catch (ConnectionException $exception) {
            Log::warning('GSI address search failed to connect.', ['message' => $exception->getMessage()]);

            return [];
        }

        if ($response->failed() || ! is_array($response->json())) {
            Log::warning('GSI address search returned an error.', ['status' => $response->status()]);

            return [];
        }

        $candidates = [];

        foreach ($response->json() as $feature) {
            $coordinates = is_array($feature) ? data_get($feature, 'geometry.coordinates') : null;
            $label = is_array($feature) ? data_get($feature, 'properties.title') : null;

            if (! is_array($coordinates) || ! is_numeric($coordinates[0] ?? null) || ! is_numeric($coordinates[1] ?? null) || ! is_string($label)) {
                continue;
            }

            if (abs((float) $coordinates[0]) > 180 || abs((float) $coordinates[1]) > 90) {
                continue;
            }

            // GeoJSON order is [longitude, latitude].
            $candidates[] = [
                'label' => $label,
                'lat' => (float) $coordinates[1],
                'lng' => (float) $coordinates[0],
            ];

            if (count($candidates) === self::MAX_CANDIDATES) {
                break;
            }
        }

        return $candidates;
    }
}
