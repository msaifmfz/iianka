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
     * Immutable inventory ledger. Rows are never updated or deleted;
     * corrections are represented as new rows.
     */
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('source_revision_id')->nullable()->constrained('schedule_content_revisions')->nullOnDelete();
            $table->foreignId('source_mention_id')->nullable()->constrained('schedule_stock_mentions')->nullOnDelete();
            $table->string('stock_name_snapshot');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
