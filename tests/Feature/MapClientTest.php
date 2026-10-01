<?php

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Models\Client;
use App\Models\ClientPlace;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mapClientPayload(array $overrides = []): array
{
    return [
        'name' => '  新規建設  ',
        'short_label' => ' 新 ',
        'note' => '  ',
        'place_name' => '  大阪本社  ',
        'kind' => 'office',
        'address' => '  大阪市北区  ',
        'lat' => 34.698826123,
        'lng' => 135.499435876,
        ...$overrides,
    ];
}

test('a manager creates a client and active first place together at the selected coordinates', function (string $kind): void {
    $editor = User::factory()->editor()->create();

    $response = $this->actingAs($editor)->post(route('crm.map.clients.store'), mapClientPayload([
        'kind' => $kind,
        'created_by_user_id' => 999,
        'client_id' => 999,
        'archived_at' => '2026-01-01',
    ]));

    $this->assertDatabaseCount('clients', 1);
    $this->assertDatabaseCount('client_places', 1);
    $client = Client::query()->sole();
    $place = ClientPlace::query()->sole();
    $response->assertRedirect(route('crm.map', ['place' => $place->id]));
    $this->assertDatabaseHas('clients', [
        'id' => $client->id, 'name' => '新規建設', 'short_label' => '新',
        'note' => null, 'created_by_user_id' => $editor->id,
    ]);
    $this->assertDatabaseHas('client_places', [
        'id' => $place->id, 'client_id' => $client->id, 'name' => '大阪本社',
        'address' => '大阪市北区', 'archived_at' => null, 'created_by_user_id' => $editor->id,
    ]);
    expect($place->kind)->toBe(ClientPlaceKind::from($kind));
    expect($place->lat)->toBe('34.6988261');
    expect($place->lng)->toBe('135.4994359');
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'clients.created', 'subject_id' => $client->id, 'actor_user_id' => $editor->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'client_places.created', 'subject_id' => $place->id, 'actor_user_id' => $editor->id,
    ]);

    $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page): Assert => $page
        ->component('crm-map/index')
        ->where('clients.0.id', $client->id)
        ->where('places.0.id', $place->id)
        ->where('selectedPlace.place.id', $place->id)
        ->where('selectedPlace.client.id', $client->id));
})->with(['office', 'site', 'other']);

test('upgrading the legacy client schema preserves existing pins and allows map creation', function (): void {
    Schema::table('clients', function (Blueprint $table): void {
        $table->string('color', 7);
    });
    $client = Client::factory()->make();
    $client->forceFill(['color' => '#123456'])->save();
    $existingPlace = ClientPlace::factory()->for($client)->create();
    $editor = User::factory()->editor()->create();
    $migration = require database_path('migrations/2026_10_01_165906_remove_legacy_color_from_clients_table.php');

    $migration->up();
    $response = $this->actingAs($editor)->post(route('crm.map.clients.store'), mapClientPayload());

    $place = ClientPlace::query()->where('id', '!=', $existingPlace->id)->sole();
    $response->assertRedirect(route('crm.map', ['place' => $place->id]));
    $this->assertDatabaseCount('clients', 2);
    $this->assertDatabaseCount('client_places', 2);
    $this->assertDatabaseHas('clients', [
        'id' => $client->id, 'name' => $client->name, 'short_label' => $client->short_label,
    ]);
    $this->assertDatabaseHas('client_places', [
        'id' => $existingPlace->id, 'client_id' => $client->id,
        'lat' => $existingPlace->lat, 'lng' => $existingPlace->lng,
    ]);
    $this->assertDatabaseHas('clients', [
        'id' => $place->client_id, 'name' => '新規建設', 'created_by_user_id' => $editor->id,
    ]);
});

test('map partial reloads include a new client and its selected pin alongside existing clients', function (): void {
    ClientPlace::factory()->create();
    $this->actingAs(User::factory()->editor()->create())->get(route('crm.map'));

    $this->post(route('crm.map.clients.store'), mapClientPayload())->assertRedirect();
    $place = ClientPlace::query()->latest('id')->firstOrFail();

    $this->get(route('crm.map', ['place' => $place->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'crm-map/index',
        'X-Inertia-Partial-Data' => 'clients,places,selectedPlace,filters',
    ])->assertOk()
        ->assertJsonCount(2, 'props.clients')
        ->assertJsonCount(2, 'props.places')
        ->assertJsonPath('props.selectedPlace.place.id', $place->id)
        ->assertJsonPath('props.selectedPlace.client.name', '新規建設');
});

test('invalid map creation leaves neither a client nor a place saved', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->editor()->create())
        ->from(route('crm.map'))
        ->post(route('crm.map.clients.store'), mapClientPayload($overrides))
        ->assertRedirect(route('crm.map'))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('client_places', 0);
    $this->assertDatabaseMissing('audit_logs', ['event' => 'clients.created']);
})->with([
    'missing client name' => [['name' => ''], 'name'],
    'long client name' => [['name' => str_repeat('a', 256)], 'name'],
    'missing short label' => [['short_label' => ''], 'short_label'],
    'long short label' => [['short_label' => 'ABCD'], 'short_label'],
    'long note' => [['note' => str_repeat('a', 5001)], 'note'],
    'missing place name' => [['place_name' => ''], 'place_name'],
    'long place name' => [['place_name' => str_repeat('a', 256)], 'place_name'],
    'long address' => [['address' => str_repeat('a', 256)], 'address'],
    'invalid kind' => [['kind' => 'warehouse'], 'kind'],
    'missing kind' => [['kind' => ''], 'kind'],
    'missing latitude' => [['lat' => ''], 'lat'],
    'missing longitude' => [['lng' => ''], 'lng'],
    'invalid latitude' => [['lat' => 'invalid'], 'lat'],
    'latitude out of range' => [['lat' => 90.1], 'lat'],
    'longitude out of range' => [['lng' => -180.1], 'lng'],
    'infinite longitude' => [['lng' => '1e309'], 'lng'],
]);

test('a failed first place write rolls back the client and success audits', function (): void {
    Exceptions::fake();
    $editor = User::factory()->editor()->create();
    DB::unprepared("CREATE TEMP TRIGGER fail_first_place BEFORE INSERT ON client_places BEGIN SELECT RAISE(FAIL, 'Place write failed'); END");

    try {
        $this->actingAs($editor)->post(route('crm.map.clients.store'), mapClientPayload())->assertServerError();
    } finally {
        DB::unprepared('DROP TRIGGER fail_first_place');
    }

    Exceptions::assertReported(QueryException::class);
    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('client_places', 0);
    $this->assertDatabaseMissing('audit_logs', ['event' => 'clients.created']);
    $this->assertDatabaseMissing('audit_logs', ['event' => 'client_places.created']);
});

test('viewers cannot create a client and place through the map endpoint', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('crm.map.clients.store'), mapClientPayload())->assertForbidden();

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('client_places', 0);
});

test('guests must log in before creating a client and place', function (): void {
    $this->post(route('crm.map.clients.store'), mapClientPayload())->assertRedirect(route('login'));

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('client_places', 0);
});
