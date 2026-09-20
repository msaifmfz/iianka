<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Crm\GeocodeAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmGeocodeController extends Controller
{
    public function __invoke(Request $request, GeocodeAddress $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'candidates' => $geocoder->candidates((string) $validated['address']),
        ]);
    }
}
