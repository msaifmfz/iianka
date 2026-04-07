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
            $table->text('voucher_note')->nullable()->after('navigation_address');
            $table->timestamp('voucher_checked_at')->nullable()->after('voucher_note');
            $table
                ->foreignId('voucher_checked_by_user_id')
                ->nullable()
                ->after('voucher_checked_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voucher_checked_by_user_id');
            $table->dropColumn(['voucher_checked_at', 'voucher_note']);
        });
    }
};
