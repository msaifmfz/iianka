<?php

use App\Models\ClientContact;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;

test('a deleted client cannot be accessed through child resource URLs', function (): void {
    $attachment = ClientPlaceLogAttachment::factory()->create();
    $log = $attachment->log;
    $place = $log->place;
    $client = $place->client;
    $contact = ClientContact::factory()->for($client)->create();
    $client->delete();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('crm.places.edit', $place))->assertNotFound();
    $this->patch(route('crm.places.update', $place), [])->assertNotFound();
    $this->delete(route('crm.places.destroy', $place))->assertNotFound();
    $this->post(route('crm.places.archive', $place))->assertNotFound();
    $this->delete(route('crm.places.unarchive', $place))->assertNotFound();
    $this->post(route('crm.places.logs.store', $place), [])->assertNotFound();
    $this->patch(route('crm.logs.update', $log), [])->assertNotFound();
    $this->delete(route('crm.logs.destroy', $log))->assertNotFound();
    $this->get(route('crm.attachments.show', $attachment))->assertNotFound();
    $this->delete(route('crm.attachments.destroy', $attachment))->assertNotFound();
    $this->patch(route('crm.contacts.update', $contact), [])->assertNotFound();
    $this->delete(route('crm.contacts.destroy', $contact))->assertNotFound();

    $this->assertModelExists($place);
    $this->assertModelExists($log);
    $this->assertModelExists($attachment);
    $this->assertModelExists($contact);
});
