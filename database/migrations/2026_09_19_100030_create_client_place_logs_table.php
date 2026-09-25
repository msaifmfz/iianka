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
        Schema::create('client_place_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->text('summary');
            $table->string('reaction')->nullable();
            $table->timestamps();

            $table->index(['client_place_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_place_logs');
    }
};
