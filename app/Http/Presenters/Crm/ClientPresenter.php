<?php

declare(strict_types=1);

namespace App\Http\Presenters\Crm;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientPlace;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Shapes CRM clients, their contacts and their places for the Inertia pages.
 */
final readonly class ClientPresenter
{
    /**
     * The identity every pin, legend row and list row needs.
     *
     * @return array{id: int, name: string, short_label: string}
     */
    public function summary(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'short_label' => $client->short_label,
        ];
    }

    /**
     * Expects `places_count` and `places_max_last_logged_at` from the index query.
     *
     * @return array<string, mixed>
     */
    public function listItem(Client $client): array
    {
        $lastLoggedAt = $client->getAttribute('places_max_last_logged_at');

        return [
            ...$this->summary($client),
            'places_count' => (int) $client->getAttribute('places_count'),
            'last_logged_at' => is_string($lastLoggedAt) ? Date::parse($lastLoggedAt)->toIso8601String() : null,
        ];
    }

    /**
     * Expects `contacts` and `places` loaded.
     *
     * @return array<string, mixed>
     */
    public function detail(Client $client): array
    {
        return [
            ...$this->summary($client),
            'note' => $client->note,
            'contacts' => $client->contacts->map($this->contact(...))->values()->all(),
            'places' => $client->places->map($this->place(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contact(ClientContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'title' => $contact->title,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'note' => $contact->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function place(ClientPlace $place): array
    {
        return [
            'id' => $place->id,
            'client_id' => $place->client_id,
            'kind' => $place->kind->value,
            'kind_label' => $place->kind->label(),
            'name' => $place->name,
            'address' => $place->address,
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'archived_at' => $place->archived_at?->toIso8601String(),
            'last_logged_at' => $place->last_logged_at?->toIso8601String(),
        ];
    }

    /**
     * The map carries one summary per author, never the full history.
     * Expects latestActivity.user and staffActivitySummaries.user loaded.
     *
     * @return array{
     *     id: int, client_id: int, kind: string, name: string, lat: float, lng: float,
     *     last_logged_at: string|null, archived_at: string|null,
     *     latest_activity: array{occurred_at: string, user: array{id: int, name: string}|null}|null,
     *     staff_activities: list<array{occurred_at: string, user: array{id: int, name: string}|null}>
     * }
     */
    public function pin(ClientPlace $place): array
    {
        return [
            'id' => $place->id,
            'client_id' => $place->client_id,
            'kind' => $place->kind->value,
            'name' => $place->name,
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'last_logged_at' => $place->last_logged_at?->toIso8601String(),
            'archived_at' => $place->archived_at?->toIso8601String(),
            'latest_activity' => $place->latestActivity instanceof ClientPlaceLog
                ? $this->activitySummary($place->latestActivity)
                : null,
            'staff_activities' => $place->staffActivitySummaries
                ->map($this->activitySummary(...))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{occurred_at: string, user: array{id: int, name: string}|null}
     */
    private function activitySummary(ClientPlaceLog $log): array
    {
        return [
            'occurred_at' => $log->occurred_at->toIso8601String(),
            'user' => $log->user instanceof User
                ? ['id' => $log->user->id, 'name' => $log->user->name]
                : null,
        ];
    }

    /**
     * The attachment ceilings the log form enforces before upload, so the
     * client never has to restate constants that live in PHP.
     *
     * @return array{max_per_log: int, max_file_bytes: int, max_recording_seconds: int, image_extensions: list<string>}
     */
    public function attachmentLimits(): array
    {
        return [
            'max_per_log' => ClientPlaceLogAttachment::MAX_PER_LOG,
            'max_file_bytes' => ClientPlaceLogAttachment::MAX_FILE_KILOBYTES * 1024,
            'max_recording_seconds' => ClientPlaceLogAttachment::MAX_RECORDING_SECONDS,
            'image_extensions' => ClientPlaceLogAttachment::IMAGE_EXTENSIONS,
        ];
    }

    /**
     * A selected place with its client's combined timeline. Each log keeps
     * its own place and author. Expects client.contacts, latestActivity.user,
     * staffActivitySummaries.user and log relationships loaded.
     *
     * `$logs` is the newest page, so `$totalLogs` says how many the client
     * has in all and whether the panel should offer the older ones.
     *
     * @param  Collection<int, ClientPlaceLog>  $logs  Client-wide logs, newest first.
     * @return array<string, mixed>
     */
    public function placeDetail(ClientPlace $place, User $viewer, Collection $logs, int $totalLogs): array
    {
        return [
            'place' => $this->place($place),
            'staff_activities' => $place->staffActivitySummaries
                ->map($this->activitySummary(...))
                ->values()
                ->all(),
            'latest_activity' => $place->latestActivity instanceof ClientPlaceLog
                ? $this->activitySummary($place->latestActivity)
                : null,
            'client' => $this->summary($place->client),
            'contacts' => $place->client->contacts
                ->map(fn (ClientContact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'title' => $contact->title,
                ])
                ->values()
                ->all(),
            'logs' => array_map(fn (ClientPlaceLog $log): array => $this->log($log, $viewer), $logs->all()),
            'logs_total' => $totalLogs,
            'older_logs_count' => max(0, $totalLogs - $logs->count()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function log(ClientPlaceLog $log, User $viewer): array
    {
        return [
            'id' => $log->id,
            'place' => [
                'id' => $log->place->id,
                'name' => $log->place->name,
                'kind_label' => $log->place->kind->label(),
                'archived_at' => $log->place->archived_at?->toIso8601String(),
            ],
            'type' => $log->type->value,
            'type_label' => $log->type->label(),
            'occurred_at' => $log->occurred_at->toIso8601String(),
            'summary' => $log->summary,
            'reaction' => $log->reaction?->value,
            'reaction_label' => $log->reaction?->label(),
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'contact' => $log->contact ? ['id' => $log->contact->id, 'name' => $log->contact->name] : null,
            'attachments' => $log->attachments
                ->map(fn (ClientPlaceLogAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'kind' => $attachment->kind->value,
                    'name' => $attachment->name,
                    'url' => $attachment->url(),
                    'duration_seconds' => $attachment->duration_seconds,
                ])
                ->values()
                ->all(),
            'can_edit' => $log->isEditableBy($viewer),
        ];
    }
}
