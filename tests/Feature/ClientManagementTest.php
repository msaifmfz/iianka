<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPlace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function clientPayload(array $overrides = []): array
{
    return [
        'name' => '西日本建設',
        'short_label' => '西',
        'note' => '  ',
        ...$overrides,
    ];
}

test('every signed-in user can browse clients with their active place counts', function (): void {
    $client = Client::factory()->create(['name' => 'Alpha']);
    ClientPlace::factory()->for($client)->count(2)->create(['last_logged_at' => '2026-09-01 10:00:00']);
    ClientPlace::factory()->for($client)->archived()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('crm.clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('clients/index')
            ->where('canManage', false)
            ->where('clients.0.name', 'Alpha')
            ->missing('clients.0.color')
            ->where('clients.0.places_count', 2)
            ->where('clients.0.last_logged_at', fn (string $value): bool => str_starts_with($value, '2026-09-01')));
});

test('the client list filters by name', function (): void {
    Client::factory()->create(['name' => '大阪工業']);
    Client::factory()->create(['name' => '福岡商事']);

    $this->actingAs(User::factory()->create())
        ->get(route('crm.clients.index', ['search' => '大阪']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('clients', 1)
            ->where('clients.0.name', '大阪工業'));
});

test('an editor creates a client', function (): void {
    $editor = User::factory()->editor()->create();

    $response = $this->actingAs($editor)->post(route('crm.clients.store'), clientPayload());

    $client = Client::query()->sole();
    $response->assertRedirect(route('crm.clients.show', $client));

    expect($client->note)->toBeNull()
        ->and($client->createdBy->is($editor))->toBeTrue()
        ->and(AuditLog::query()->where('event', 'clients.created')->exists())->toBeTrue();
});

test('client input is validated', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('crm.clients.store'), clientPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'label too long' => [['short_label' => 'ABCD'], 'short_label'],
    'note too long' => [['note' => str_repeat('a', 5001)], 'note'],
]);

test('a viewer cannot create, edit or delete clients', function (): void {
    $viewer = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($viewer)->get(route('crm.clients.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('crm.clients.store'), clientPayload())->assertForbidden();
    $this->actingAs($viewer)->get(route('crm.clients.edit', $client))->assertForbidden();
    $this->actingAs($viewer)->patch(route('crm.clients.update', $client), clientPayload())->assertForbidden();
    $this->actingAs($viewer)->delete(route('crm.clients.destroy', $client))->assertForbidden();

    expect(Client::query()->count())->toBe(1);
});

test('the create form no longer receives a color palette', function (): void {
    Client::factory()->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('crm.clients.create'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('clients/form')
            ->where('client', null)
            ->missing('usedColors'));
});

test('the edit form no longer receives client colors', function (): void {
    $client = Client::factory()->create();
    Client::factory()->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('crm.clients.edit', $client))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('client.id', $client->id)
            ->missing('client.color')
            ->missing('usedColors'));
});

test('an editor updates and deletes a client', function (): void {
    $editor = User::factory()->editor()->create();
    $client = Client::factory()->create();

    // A stray color from an old form is ignored, not stored.
    $this->actingAs($editor)
        ->patch(route('crm.clients.update', $client), clientPayload(['name' => '新名称', 'color' => '#ffffff']))
        ->assertRedirect(route('crm.clients.show', $client));

    expect($client->refresh()->name)->toBe('新名称');

    $this->actingAs($editor)
        ->delete(route('crm.clients.destroy', $client))
        ->assertRedirect(route('crm.clients.index'));

    expect($client->refresh()->trashed())->toBeTrue();
});

test('the client page shows contacts and places, archived places last', function (): void {
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->create(['name' => '山田']);
    ClientPlace::factory()->for($client)->archived()->create(['name' => 'A 旧現場']);
    ClientPlace::factory()->for($client)->create(['name' => 'B 現場']);

    $this->actingAs(User::factory()->create())
        ->get(route('crm.clients.show', $client))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('clients/show')
            ->missing('client.color')
            ->where('client.contacts.0.name', '山田')
            ->where('client.places.0.name', 'B 現場')
            ->where('client.places.1.name', 'A 旧現場'));
});

test('an editor manages contacts and a viewer cannot', function (): void {
    $editor = User::factory()->editor()->create();
    $viewer = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($viewer)
        ->post(route('crm.clients.contacts.store', $client), ['name' => '佐藤'])
        ->assertForbidden();

    $this->actingAs($editor)
        ->post(route('crm.clients.contacts.store', $client), ['name' => '佐藤', 'email' => ''])
        ->assertRedirect();

    $contact = $client->contacts()->sole();
    expect($contact->email)->toBeNull();

    $this->actingAs($editor)
        ->patch(route('crm.contacts.update', $contact), ['name' => '佐藤 次郎', 'title' => '部長'])
        ->assertRedirect();
    expect($contact->refresh()->title)->toBe('部長');

    $this->actingAs($viewer)->delete(route('crm.contacts.destroy', $contact))->assertForbidden();
    $this->actingAs($editor)->delete(route('crm.contacts.destroy', $contact))->assertRedirect();

    expect(ClientContact::query()->count())->toBe(0);
});

test('inline contact creation returns the saved contact and validates permissions and fields', function (): void {
    $client = Client::factory()->create();
    $this->actingAs(User::factory()->create())->postJson(route('crm.clients.contacts.store', $client), ['name' => '佐藤'])->assertForbidden();
    $this->actingAs(User::factory()->editor()->create())->postJson(route('crm.clients.contacts.store', $client), ['name' => '', 'email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    $this->postJson(route('crm.clients.contacts.store', $client), ['name' => '佐藤 花子', 'title' => '主任'])->assertCreated()->assertJsonPath('contact.name', '佐藤 花子')->assertJsonPath('contact.id', $client->contacts()->sole()->id);
    $client->delete();
    $this->postJson(route('crm.clients.contacts.store', $client), ['name' => '不可'])->assertNotFound();
});
