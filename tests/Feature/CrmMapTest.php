<?php

use App\Http\Controllers\CrmMapController;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDocument;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Headers for the partial reload the map sends when a pin is tapped. Load the
 * page normally first so Inertia knows its asset version (see
 * .ai/rules/feature.md).
 *
 * @return array<string, string>
 */
function selectedPlaceHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'crm-map/index',
        'X-Inertia-Partial-Data' => 'selectedPlace',
    ];
}

test('the map shows active pins of live clients only', function (): void {
    $client = Client::factory()->create();
    $active = ClientPlace::factory()->for($client)->create(['last_logged_at' => '2026-09-01 00:00:00']);
    $archived = ClientPlace::factory()->for($client)->archived()->create();
    $deletedClientPlace = ClientPlace::factory()->create();
    $deletedClientPlace->client->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('crm.map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('crm-map/index')
            ->has('places', 1)
            ->where('places.0.id', $active->id)
            ->where('places.0.client_id', $client->id)
            ->where('places.0.last_logged_at', fn (string $value): bool => str_starts_with($value, '2026-09-01'))
            ->has('clients', 1)
            ->where('selectedPlace', null)
            ->where('filters.archived', false)
            ->where('canManage', false)
            ->has('logTypes', 4)
            ->has('reactions', 3));

    $this->actingAs(User::factory()->create())
        ->get(route('crm.map', ['archived' => 1]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('places', 2)
            ->where('filters.archived', true));

    expect($archived->id)->not->toBe($active->id);
});

test('opening a pin loads its history newest first with edit rights per entry', function (): void {
    $viewer = User::factory()->create();
    $place = ClientPlace::factory()->create();
    $contact = ClientContact::factory()->for($place->client)->create(['name' => '田中']);
    $older = ClientPlaceLog::factory()->for($place, 'place')->for($viewer)->create([
        'occurred_at' => '2026-08-01 01:00:00',
        'client_contact_id' => $contact->id,
        'reaction' => 'positive',
    ]);
    $newer = ClientPlaceLog::factory()->for($place, 'place')->create(['occurred_at' => '2026-09-01 01:00:00']);

    $this->actingAs($viewer)->get(route('crm.map'))->assertOk();

    $this->actingAs($viewer)
        ->get(route('crm.map', ['place' => $place->id]), selectedPlaceHeaders())
        ->assertOk()
        ->assertJsonMissingPath('props.places')
        ->assertJsonPath('props.selectedPlace.place.id', $place->id)
        ->assertJsonPath('props.selectedPlace.client.id', $place->client_id)
        ->assertJsonPath('props.selectedPlace.contacts.0.name', '田中')
        ->assertJsonPath('props.selectedPlace.logs.0.id', $newer->id)
        ->assertJsonPath('props.selectedPlace.logs.0.can_edit', false)
        ->assertJsonPath('props.selectedPlace.logs.1.id', $older->id)
        ->assertJsonPath('props.selectedPlace.logs.1.can_edit', true)
        ->assertJsonPath('props.selectedPlace.logs.1.reaction_label', '好感触')
        ->assertJsonPath('props.selectedPlace.logs.1.contact.name', '田中');
});

test('a deep link opens the panel on first load', function (): void {
    $place = ClientPlace::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('crm.map', ['place' => $place->id]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selectedPlace.place.id', $place->id)
            ->where('canManage', true));
});

test('an unknown or deleted-client place opens nothing', function (): void {
    $place = ClientPlace::factory()->create();
    $place->client->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('crm.map', ['place' => $place->id]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('selectedPlace', null));

    $this->actingAs(User::factory()->create())
        ->get(route('crm.map', ['place' => 999999]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('selectedPlace', null));
});

test('the combined client timeline keeps place threads and authors distinct including archived places', function (): void {
    $viewer = User::factory()->create();
    $office = ClientPlace::factory()->office()->create();
    $site = ClientPlace::factory()->for($office->client)->archived()->create();
    $officeLog = ClientPlaceLog::factory()->for($office, 'place')->for($viewer)->create([
        'occurred_at' => '2026-08-01 01:00:00',
    ]);
    $siteLog = ClientPlaceLog::factory()->for($site, 'place')->create([
        'occurred_at' => '2026-09-01 01:00:00',
    ]);
    $sameTimeLog = ClientPlaceLog::factory()->for($office, 'place')->create([
        'occurred_at' => '2026-09-01 01:00:00',
        'user_id' => null,
    ]);
    ClientPlaceLog::factory()->create(['summary' => '別の顧客の非表示記録']);

    $this->actingAs($viewer)->get(route('crm.map'))->assertOk();

    foreach ([$office, $site] as $selected) {
        $this->get(route('crm.map', ['place' => $selected->id]), selectedPlaceHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'props.selectedPlace.logs')
            ->assertJsonPath('props.selectedPlace.place.id', $selected->id)
            ->assertJsonPath('props.selectedPlace.logs.0.id', $sameTimeLog->id)
            ->assertJsonPath('props.selectedPlace.logs.0.user', null)
            ->assertJsonPath('props.selectedPlace.logs.1.id', $siteLog->id)
            ->assertJsonPath('props.selectedPlace.logs.1.place.id', $site->id)
            ->assertJsonPath('props.selectedPlace.logs.1.place.name', $site->name)
            ->assertJsonPath('props.selectedPlace.logs.1.place.archived_at', $site->archived_at?->toIso8601String())
            ->assertJsonPath('props.selectedPlace.logs.1.user.name', $siteLog->user->name)
            ->assertJsonPath('props.selectedPlace.logs.1.can_edit', false)
            ->assertJsonPath('props.selectedPlace.logs.2.id', $officeLog->id)
            ->assertJsonPath('props.selectedPlace.logs.2.place.id', $office->id)
            ->assertJsonPath('props.selectedPlace.logs.2.user.name', $viewer->name)
            ->assertJsonPath('props.selectedPlace.logs.2.can_edit', true);
    }

    expect($office->logs()->count())->toBe(2)->and($site->logs()->count())->toBe(1);
});

test('guests cannot see the map', function (): void {
    $this->get(route('crm.map'))->assertRedirect(route('login'));
});

test('the panel ships the newest page of history and fetches the rest on request', function (): void {
    $client = Client::factory()->create();
    $place = ClientPlace::factory()->for($client)->create();
    $otherPlace = ClientPlace::factory()->for($client)->create();

    // Interleave the two places so the cap is applied to the client-wide
    // timeline rather than to one place's share of it. Inserted in bulk: 120
    // hydrated factory models would be pure overhead here.
    $rows = [];

    foreach (range(1, 60) as $offset) {
        foreach ([$place->id => $offset * 2, $otherPlace->id => $offset * 2 + 1] as $placeId => $minutes) {
            $rows[] = [
                'client_place_id' => $placeId,
                'type' => 'visit',
                'occurred_at' => now()->subMinutes($minutes),
                'summary' => "記録 {$minutes}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    ClientPlaceLog::query()->insert($rows);

    $this->actingAs(User::factory()->create())->get(route('crm.map'))->assertOk();

    $this->get(route('crm.map', ['place' => $place->id]), selectedPlaceHeaders())
        ->assertOk()
        ->assertJsonCount(CrmMapController::RECENT_LOG_LIMIT, 'props.selectedPlace.logs')
        ->assertJsonPath('props.selectedPlace.logs_total', 120)
        ->assertJsonPath('props.selectedPlace.older_logs_count', 20)
        // Newest first, whichever place the entry belongs to.
        ->assertJsonPath('props.selectedPlace.logs.0.place.id', $place->id)
        ->assertJsonPath('props.selectedPlace.logs.1.place.id', $otherPlace->id);

    $this->get(route('crm.map', ['place' => $place->id, 'logs' => 'all']), selectedPlaceHeaders())
        ->assertOk()
        ->assertJsonCount(120, 'props.selectedPlace.logs')
        ->assertJsonPath('props.selectedPlace.logs_total', 120)
        ->assertJsonPath('props.selectedPlace.older_logs_count', 0);
});

test('a short history is delivered whole with nothing older to fetch', function (): void {
    $place = ClientPlace::factory()->create();
    ClientPlaceLog::factory()->count(3)->for($place, 'place')->create();

    $this->actingAs(User::factory()->create())->get(route('crm.map'))->assertOk();

    $this->get(route('crm.map', ['place' => $place->id]), selectedPlaceHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'props.selectedPlace.logs')
        ->assertJsonPath('props.selectedPlace.logs_total', 3)
        ->assertJsonPath('props.selectedPlace.older_logs_count', 0);
});

test('the map hands the log form the attachment ceilings instead of restating them', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('crm.map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('attachmentLimits.max_per_log', ClientPlaceLogAttachment::MAX_PER_LOG)
            ->where('attachmentLimits.max_file_bytes', ClientPlaceLogAttachment::MAX_FILE_KILOBYTES * 1024)
            ->where('attachmentLimits.max_recording_seconds', ClientPlaceLogAttachment::MAX_RECORDING_SECONDS)
            ->where('attachmentLimits.image_extensions', ClientPlaceLogAttachment::IMAGE_EXTENSIONS)
            ->where('attachmentLimits.document_extensions', ClientPlaceLogAttachment::DOCUMENT_EXTENSIONS)
        );
});

test('a place panel lists the newest documents filed on it or on the whole client', function (): void {
    $place = ClientPlace::factory()->create();
    $siblingPlace = ClientPlace::factory()->for($place->client)->create();
    $onPlace = ClientDocument::factory()->for($place->client)->for($place, 'place')->create(['issued_on' => '2026-09-10']);
    $clientWide = ClientDocument::factory()->for($place->client)->create(['issued_on' => '2026-09-01']);
    ClientDocument::factory()->for($place->client)->for($siblingPlace, 'place')->create();
    ClientDocument::factory()->create();
    ClientDocument::factory()->for($place->client)->count(CrmMapController::RECENT_DOCUMENT_LIMIT)->create(['issued_on' => '2026-01-01']);

    $this->actingAs(User::factory()->create())->get(route('crm.map'))->assertOk();

    $this->get(route('crm.map', ['place' => $place->id]), selectedPlaceHeaders())
        ->assertOk()
        ->assertJsonCount(CrmMapController::RECENT_DOCUMENT_LIMIT, 'props.selectedPlace.documents')
        ->assertJsonPath('props.selectedPlace.documents.0.id', $onPlace->id)
        ->assertJsonPath('props.selectedPlace.documents.1.id', $clientWide->id)
        ->assertJsonPath('props.selectedPlace.documents_total', CrmMapController::RECENT_DOCUMENT_LIMIT + 2);
});
