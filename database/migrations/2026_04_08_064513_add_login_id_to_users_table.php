<?php

declare(strict_types=1);

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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('login_id')->nullable()->after('name');
        });

        DB::table('users')
            ->select(['id'])
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'login_id' => sprintf('user-%d', $user->id),
                    ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('login_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['login_id']);
            $table->dropColumn('login_id');
        });
    }
};
