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
        Schema::create('construction_subcontractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('phone')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('construction_schedule_subcontractor', function (Blueprint $table): void {
            $table->foreignId('construction_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('construction_subcontractor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['construction_schedule_id', 'construction_subcontractor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_schedule_subcontractor');
        Schema::dropIfExists('construction_subcontractors');
    }
};
