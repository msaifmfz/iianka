<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMapClientRequest;
use App\Models\Client;
use App\Models\ClientPlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MapClientController extends Controller
{
    public function store(StoreMapClientRequest $request): RedirectResponse
    {
        $place = DB::transaction(function () use ($request): ClientPlace {
            $actorId = $request->user()?->id;
            $client = Client::query()->create([
                ...$request->safe()->only(['name', 'short_label', 'note']),
                'created_by_user_id' => $actorId,
            ]);
            $place = $client->places()->create([
                ...$request->safe()->only(['kind', 'address', 'lat', 'lng']),
                'name' => $request->validated('place_name'),
                'created_by_user_id' => $actorId,
            ]);

            $this->auditSuccess('clients.created', 'A CRM client was created.', $client);
            $this->auditSuccess('client_places.created', 'A CRM client place was created.', $place, [
                'client_id' => $client->id,
            ]);

            return $place;
        });

        $this->flashToast('顧客と地点を追加しました。');

        return to_route('crm.map', ['place' => $place->id]);
    }
}
