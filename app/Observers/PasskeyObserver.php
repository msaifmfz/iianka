<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogger;
use Laravel\Passkeys\Passkey;

class PasskeyObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Handle the Passkey "created" event.
     */
    public function created(Passkey $passkey): void
    {
        $this->auditLogger->success(
            event: 'settings.passkeys.created',
            description: 'A user registered a passkey.',
            metadata: [
                'passkey_id' => $passkey->getKey(),
                'name' => $passkey->name,
            ],
            actor: $passkey->user,
        );
    }

    /**
     * Handle the Passkey "deleted" event.
     */
    public function deleted(Passkey $passkey): void
    {
        $this->auditLogger->success(
            event: 'settings.passkeys.deleted',
            description: 'A user removed a passkey.',
            metadata: [
                'passkey_id' => $passkey->getKey(),
                'name' => $passkey->name,
            ],
            actor: $passkey->user,
        );
    }
}
