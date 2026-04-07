<?php

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
        Schema::create('business_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('scheduled_on')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('time_note')->nullable();
            $table->string('personnel')->nullable();
            $table->string('location');
            $table->string('general_contractor')->nullable();
            $table->string('person_in_charge')->nullable();
            $table->text('content');
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_schedules');
    }
};
