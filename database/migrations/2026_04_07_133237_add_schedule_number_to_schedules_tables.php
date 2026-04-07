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
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->unsignedInteger('schedule_number')->nullable()->after('scheduled_on');
            $table->index(['scheduled_on', 'schedule_number']);
        });

        Schema::table('business_schedules', function (Blueprint $table): void {
            $table->unsignedInteger('schedule_number')->nullable()->after('scheduled_on');
            $table->index(['scheduled_on', 'schedule_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_schedules', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_on', 'schedule_number']);
            $table->dropColumn('schedule_number');
        });

        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_on', 'schedule_number']);
            $table->dropColumn('schedule_number');
        });
    }
};
