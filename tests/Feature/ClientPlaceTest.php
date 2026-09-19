<?php

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function placePayload(array $overrides = []): array
{
    return [
        'kind' => 'office',
        'name' => '本社',
        'address' => '大阪府大阪市北区梅田1-1',
        'lat' => 34.698826,
        'lng' => 135.499435,
        ...$overrides,
    ];
}

test('an editor adds a place and lands on the map with it open', function (): void {
    $editor = User::factory()->editor()->create();
    $client = Client::factory()->create();

    $response = $this->actingAs($editor)->post(route('crm.clients.places.store', $client), placePayload());

    $place = ClientPlace::query()->sole();
    $response->assertRedirect(route('crm.map', ['place' => $place->id]));

    expect($place->kind)->toBe(ClientPlaceKind::Office)
        ->and((float) $place->lat)->toBe(34.698826)
        ->and($place->client->is($client))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'client_places.created')->exists())->toBeTrue();
});

test('place input is validated', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('crm.clients.places.store', Client::factory()->create()), placePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'no position' => [['lat' => ''], 'lat'],
    'latitude out of range' => [['lat' => 120], 'lat'],
    'unknown kind' => [['kind' => 'warehouse'], 'kind'],
    'missing name' => [['name' => ''], 'name'],
]);

test('a viewer cannot add, edit, delete or archive places', function (): void {
    $viewer = User::factory()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($viewer)->get(route('crm.clients.places.create', $place->client))->assertForbidden();
    $this->actingAs($viewer)->post(route('crm.clients.places.store', $place->client), placePayload())->assertForbidden();
    $this->actingAs($viewer)->get(route('crm.places.edit', $place))->assertForbidden();
    $this->actingAs($viewer)->patch(route('crm.places.update', $place), placePayload())->assertForbidden();
    $this->actingAs($viewer)->delete(route('crm.places.destroy', $place))->assertForbidden();
    $this->actingAs($viewer)->post(route('crm.places.archive', $place))->assertForbidden();

    expect(ClientPlace::query()->count())->toBe(1)
        ->and($place->refresh()->archived_at)->toBeNull();
});

test('the create form pre-places the pin from a long-press and shows the client other active places', function (): void {
    $client = Client::factory()->create();
    $existing = ClientPlace::factory()->for($client)->create();
    ClientPlace::factory()->for($client)->archived()->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('crm.clients.places.create', ['client' => $client, 'lat' => '34.5', 'lng' => '135.25']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('clients/place-form')
            ->where('place', null)
            ->where('initialPosition', ['lat' => 34.5, 'lng' => 135.25])
            ->has('otherPlaces', 1)
            ->where('otherPlaces.0.id', $existing->id)
            ->has('kinds', 3));
});

test('an editor moves a place and deletes it with its history', function (): void {
    $editor = User::factory()->editor()->create();
    $place = ClientPlace::factory()->create();
    ClientPlaceLog::factory()->for($place, 'place')->count(2)->create();

    $this->actingAs($editor)
        ->get(route('crm.places.edit', $place))
        ->assertInertia(fn (Assert $page): Assert => $page->where('place.id', $place->id));

    $this->actingAs($editor)
        ->patch(route('crm.places.update', $place), placePayload(['lat' => 35.0, 'lng' => 136.0, 'kind' => 'site']))
        ->assertRedirect(route('crm.map', ['place' => $place->id]));

    expect((float) $place->refresh()->lat)->toBe(35.0)
        ->and($place->kind)->toBe(ClientPlaceKind::Site);

    $this->actingAs($editor)
        ->delete(route('crm.places.destroy', $place))
        ->assertRedirect(route('crm.clients.show', $place->client_id));

    expect(ClientPlace::query()->count())->toBe(0)
        ->and(ClientPlaceLog::query()->count())->toBe(0);
});

test('an editor archives and restores a place', function (): void {
    $editor = User::factory()->editor()->create();
    $place = ClientPlace::factory()->create();

    $this->actingAs($editor)->post(route('crm.places.archive', $place))->assertRedirect();
    expect($place->refresh()->archived_at)->not->toBeNull();

    $this->actingAs($editor)->delete(route('crm.places.unarchive', $place))->assertRedirect();
    expect($place->refresh()->archived_at)->toBeNull()
        ->and(AuditLog::query()->where('event', 'client_places.archived')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'client_places.unarchived')->exists())->toBeTrue();
});

test('deleting a place removes its attachment files without touching another place', function (): void {
    Storage::fake(ClientPlaceLogAttachment::DISK);
    $attachment = ClientPlaceLogAttachment::factory()->create();
    $otherAttachment = ClientPlaceLogAttachment::factory()->create();
    $disk = Storage::disk(ClientPlaceLogAttachment::DISK);
    $disk->put($attachment->path, 'photo');
    $disk->put($otherAttachment->path, 'other photo');

    $this->actingAs(User::factory()->editor()->create())
        ->delete(route('crm.places.destroy', $attachment->log->place))
        ->assertRedirect();

    $disk->assertMissing($attachment->path);
    $disk->assertExists($otherAttachment->path);
    $this->assertModelMissing($attachment);
    $this->assertModelExists($otherAttachment);
});

test('invalid query coordinates do not reach the map picker', function (string $lat, string $lng): void {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('crm.clients.places.create', ['client' => Client::factory()->create(), 'lat' => $lat, 'lng' => $lng]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('initialPosition', null));
})->with([
    ['91', '135'],
    ['34', '181'],
    ['1e309', '135'],
]);
