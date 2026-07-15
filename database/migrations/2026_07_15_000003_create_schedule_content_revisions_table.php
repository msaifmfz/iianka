<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only log of parsed schedule content versions. Deliberately no
     * unique(schedule_type, schedule_id, content_hash): content edited
     * A -> B -> A must produce a third revision. Duplicate-save idempotency is
     * handled by the content_hash column on the schedule row itself.
     */
    public function up(): void
    {
        Schema::create('schedule_content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('schedule_type', 50);
            $table->unsignedBigInteger('schedule_id');
            $table->char('content_hash', 64);
            $table->longText('content_snapshot');
            $table->string('parser_version', 50);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['schedule_type', 'schedule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_content_revisions');
    }
};
