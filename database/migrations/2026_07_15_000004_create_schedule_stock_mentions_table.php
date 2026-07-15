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
     * Offsets are Unicode code-point offsets into the revision's
     * content_snapshot. quantity is null only when the parsed number
     * overflows decimal(12,3).
     */
    public function up(): void
    {
        Schema::create('schedule_stock_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('schedule_content_revisions')->cascadeOnDelete();
            $table->string('schedule_type', 50);
            $table->unsignedBigInteger('schedule_id');
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->string('stock_name_snapshot');
            $table->string('matched_text');
            $table->decimal('quantity', 12, 3)->nullable();
            $table->unsignedInteger('start_offset');
            $table->unsignedInteger('end_offset');
            $table->unsignedInteger('quantity_start_offset')->nullable();
            $table->unsignedInteger('quantity_end_offset')->nullable();
            $table->string('identification_method', 30);
            $table->string('status', 30);
            $table->timestamps();

            $table->index(['schedule_type', 'schedule_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_stock_mentions');
    }
};
