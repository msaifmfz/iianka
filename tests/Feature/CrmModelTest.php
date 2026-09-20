<?php

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;

test('a client owns contacts and places, and a place owns its history', function (): void {
    $client = Client::factory()->create();
    $contact = ClientContact::factory()->for($client)->create();
    $office = ClientPlace::factory()->for($client)->office()->create();
    $log = ClientPlaceLog::factory()
        ->for($office, 'place')
        ->create(['client_contact_id' => $contact->id]);

    expect($client->contacts)->toHaveCount(1)
        ->and($client->places->first()->kind)->toBe(ClientPlaceKind::Office)
        ->and($office->logs->first()->is($log))->toBeTrue()
        ->and($log->type)->toBe(ClientPlaceLogType::Visit)
        ->and($log->contact->is($contact))->toBeTrue();
});

test('the active scope hides archived places', function (): void {
    $client = Client::factory()->create();
    $open = ClientPlace::factory()->for($client)->create();
    ClientPlace::factory()->for($client)->archived()->create();

    expect(ClientPlace::query()->active()->pluck('id')->all())->toBe([$open->id]);
});

test('deleting a client soft deletes it and keeps its places', function (): void {
    $place = ClientPlace::factory()->create();

    $place->client->delete();

    expect(Client::withTrashed()->find($place->client_id)?->trashed())->toBeTrue()
        ->and(ClientPlace::query()->find($place->id))->not->toBeNull();
});
