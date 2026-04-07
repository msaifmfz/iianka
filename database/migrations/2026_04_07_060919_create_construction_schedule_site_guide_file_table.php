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
        Schema::create('construction_schedule_site_guide_file', function (Blueprint $table) {
            $table->foreignId('construction_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_guide_file_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['construction_schedule_id', 'site_guide_file_id'], 'schedule_guide_file_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_schedule_site_guide_file');
    }
};
