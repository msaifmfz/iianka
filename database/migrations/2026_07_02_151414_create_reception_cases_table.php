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
        Schema::create('reception_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_number')->unique();
            $table->string('status')->index();
            $table->string('company_name')->nullable();
            $table->string('site_name')->nullable();
            $table->foreignId('reception_document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reception_content')->nullable();
            $table->text('work_memo')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->date('scheduled_on')->nullable()->index();
            $table->foreignId('receptor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'due_on']);
            $table->index(['assigned_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_cases');
    }
};
