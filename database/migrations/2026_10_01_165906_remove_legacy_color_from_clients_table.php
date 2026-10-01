<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'color')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }

    /**
     * The canonical client schema already omits color; obsolete values cannot be restored.
     */
    public function down(): void {}
};
