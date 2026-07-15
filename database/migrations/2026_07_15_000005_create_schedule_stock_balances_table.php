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
     * Currently applied stock usage per schedule. Rows are deleted when the
     * applied quantity returns to zero.
     */
    public function up(): void
    {
        Schema::create('schedule_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('schedule_type', 50);
            $table->unsignedBigInteger('schedule_id');
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->decimal('applied_quantity', 12, 3)->default(0);
            $table->foreignId('latest_revision_id')->nullable()->constrained('schedule_content_revisions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['schedule_type', 'schedule_id', 'stock_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_stock_balances');
    }
};
