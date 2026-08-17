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
        // SQLite does not index a foreign key's child column on its own, and the
        // reception detail page looks every linked schedule up by this column.
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->foreignId('reception_case_id')
                ->nullable()
                ->index()
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('business_schedules', function (Blueprint $table): void {
            $table->foreignId('reception_case_id')
                ->nullable()
                ->index()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The index has to go first: dropConstrainedForeignId() leaves it behind,
        // and SQLite refuses to drop a column a surviving index still references.
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->dropIndex(['reception_case_id']);
            $table->dropConstrainedForeignId('reception_case_id');
        });

        Schema::table('business_schedules', function (Blueprint $table): void {
            $table->dropIndex(['reception_case_id']);
            $table->dropConstrainedForeignId('reception_case_id');
        });
    }
};
