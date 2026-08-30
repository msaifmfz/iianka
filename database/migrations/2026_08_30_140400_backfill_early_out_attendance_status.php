<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename the shipped-but-unreleased 'early' status to 'early_out'.
     *
     * `attendance_records.status` is an unconstrained varchar, so a row written
     * while v0.4.9 was in development can still hold 'early'. Nothing rejects
     * such a row: it simply stops satisfying WORKED_DAY_STATUSES and drops out
     * of the user's 出勤日数 without an error, which is a payroll undercount.
     *
     * Literals rather than AttendanceRecord constants on purpose — a migration
     * is a point-in-time snapshot and must keep running after the model's
     * constants are renamed again or the model is removed.
     */
    public function up(): void
    {
        DB::table('attendance_records')
            ->where('status', 'early')
            ->update(['status' => 'early_out']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: reverting would reintroduce a status the application no longer understands.
    }
};
