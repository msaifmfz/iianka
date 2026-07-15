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
     * Purchased quantity per stock per term month (a term runs from the 21st
     * to the 20th of the following month; term_starts_on is always a 21st).
     * Mutable aggregate; every change is mirrored as an immutable ledger row
     * in stock_transactions.
     */
    public function up(): void
    {
        Schema::create('stock_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->date('term_starts_on');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['stock_id', 'term_starts_on']);
            $table->index('term_starts_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_purchases');
    }
};
