<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Laragear\WebAuthn\Models\WebAuthnCredential;

return WebAuthnCredential::migration()->with(function (Blueprint $table): void {
    // Here you can add custom columns to the WebAuthn credentials table.
    //
    // $table->string('alias')->nullable();
});
