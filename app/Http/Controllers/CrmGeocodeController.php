<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\GeocodeAddress;
use App\Application\Crm\GeocodingUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmGeocodeController extends Controller
{
    /**
     * Answers 200 with the candidates (possibly none) when GSI responds, and
     * 503 when it cannot, so the form can tell "no match" from an outage.
     */
    public function __invoke(Request $request, GeocodeAddress $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255'],
        ]);

        try {
            $candidates = $geocoder->candidates((string) $validated['address']);
        } catch (GeocodingUnavailable) {
            return response()->json([
                'message' => '住所検索サービスに接続できませんでした。',
                'candidates' => [],
            ], 503);
        }

        return response()->json(['candidates' => $candidates]);
    }
}
