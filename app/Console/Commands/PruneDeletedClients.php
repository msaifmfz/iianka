<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Crm\PlaceLogRecorder;
use App\Models\Client;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:prune-deleted-clients
    {--days=90 : Only prune clients deleted at least this many days ago}
    {--force : Skip the confirmation prompt}')]
#[Description('Permanently remove long-deleted CRM clients with their places, history and attachment files')]
class PruneDeletedClients extends Command
{
    /**
     * Deleting a client only soft-deletes it, so the database cascade never
     * runs and its attachment files stay on the private disk with no route
     * left that can reach them. This reclaims them once the retention window
     * has passed. It is deliberately not scheduled: how long a deleted client
     * stays recoverable is a business decision, not a default.
     */
    public function handle(PlaceLogRecorder $recorder, AuditLogger $auditLogger): int
    {
        $days = (int) $this->option('days');

        if ($days < 0) {
            $this->components->error('The --days option cannot be negative.');

            return self::FAILURE;
        }

        $clients = Client::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($days))
            ->get();

        if ($clients->isEmpty()) {
            $this->components->info("No CRM clients have been deleted for {$days} days or more.");

            return self::SUCCESS;
        }

        $this->components->warn("{$clients->count()} CRM client(s) will be removed permanently, with every place, history entry, photo and recording:");

        foreach ($clients as $client) {
            $this->components->twoColumnDetail($client->name, 'deleted '.$client->deleted_at?->diffForHumans());
        }

        if (! $this->option('force') && ! $this->confirm('This cannot be undone. Continue?')) {
            $this->components->info('Nothing was removed.');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $recorder->purgeClient($client);

            $auditLogger->record(
                event: 'clients.purged',
                outcome: 'success',
                description: 'A soft-deleted CRM client was permanently removed from the console.',
                metadata: [
                    'source' => 'crm:prune-deleted-clients',
                    'retention_days' => $days,
                ],
                // The row is gone by now, so the subject is recorded by hand.
                subjectType: Client::class,
                subjectId: $client->id,
                subjectLabel: $client->name,
                actorType: 'console',
            );
        }

        $this->components->info("Removed {$clients->count()} CRM client(s).");

        return self::SUCCESS;
    }
}
