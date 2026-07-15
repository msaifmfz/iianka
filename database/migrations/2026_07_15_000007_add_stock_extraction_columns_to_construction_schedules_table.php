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
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->char('content_hash', 64)->nullable()->after('content');
            $table->unsignedInteger('content_version')->default(1)->after('content_hash');
            $table->string('stock_extraction_status', 30)->nullable()->after('content_version');
            $table->timestamp('stock_extracted_at')->nullable()->after('stock_extraction_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_schedules', function (Blueprint $table): void {
            $table->dropColumn([
                'content_hash',
                'content_version',
                'stock_extraction_status',
                'stock_extracted_at',
            ]);
        });
    }
};
