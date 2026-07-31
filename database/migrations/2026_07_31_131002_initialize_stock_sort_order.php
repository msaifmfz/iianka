<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $stockIds = DB::table('stocks')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');

        foreach ($stockIds as $index => $stockId) {
            DB::table('stocks')
                ->where('id', $stockId)
                ->update(['sort_order' => ($index + 1) * 10]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stocks')->update(['sort_order' => 0]);
    }
};
