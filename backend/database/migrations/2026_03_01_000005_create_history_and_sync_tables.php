<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_number')->nullable();
            $table->string('module_key', 50);
            $table->float('scroll_position')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'idx_history_user');
        });

        Schema::create('reading_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days');
            $table->jsonb('plan_data');
            $table->boolean('is_system')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reading_plan_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('reading_plans')->cascadeOnDelete();
            $table->date('start_date');
            $table->integer('current_day')->default(1);
            $table->jsonb('completed_days')->default('[]');
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->unique(['user_id', 'plan_id']);
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->jsonb('value');
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->string('operation', 10);
            $table->jsonb('payload')->nullable();
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamp('synced_at')->useCurrent();
            $table->index(['user_id', 'synced_at'], 'idx_sync_logs_user_time');
            $table->index(['user_id', 'device_id', 'synced_at'], 'idx_sync_logs_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('reading_plan_progress');
        Schema::dropIfExists('reading_plans');
        Schema::dropIfExists('history');
    }
};
