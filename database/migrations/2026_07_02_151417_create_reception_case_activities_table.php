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
        Schema::create('reception_case_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reception_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->text('memo')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->foreignId('from_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_case_activities');
    }
};
