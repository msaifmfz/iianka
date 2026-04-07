<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->string('meeting_place')->nullable()->change();
            $table->text('content')->nullable()->change();
            $table->string('navigation_address')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('construction_schedules')->whereNull('meeting_place')->update(['meeting_place' => '']);
        DB::table('construction_schedules')->whereNull('content')->update(['content' => '']);
        DB::table('construction_schedules')->whereNull('navigation_address')->update(['navigation_address' => '']);

        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->string('meeting_place')->nullable(false)->change();
            $table->text('content')->nullable(false)->change();
            $table->string('navigation_address')->nullable(false)->change();
        });
    }
};
