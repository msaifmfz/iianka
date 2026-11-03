<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Intentionally changes no schema: it gives the first automatic-migration
 * deploy a pending migration to snapshot and run.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
