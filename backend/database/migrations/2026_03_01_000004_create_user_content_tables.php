<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmark_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('bookmark_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#FFD700');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_deleted'], 'idx_bookmark_folders_user');
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('bookmark_folders')->nullOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_start');
            $table->smallInteger('verse_end')->nullable();
            $table->string('module_key', 50)->nullable();
            $table->string('label', 500)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_deleted'], 'idx_bookmarks_user');
            $table->index(['book_osis_id', 'chapter_number', 'verse_start'], 'idx_bookmarks_reference');
        });

        Schema::create('highlights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_number');
            $table->string('color', 20);
            $table->string('module_key', 50)->nullable();
            $table->integer('text_range_start')->nullable();
            $table->integer('text_range_end')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_deleted'], 'idx_highlights_user');
            $table->index(['book_osis_id', 'chapter_number'], 'idx_highlights_reference');
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_start');
            $table->smallInteger('verse_end')->nullable();
            $table->string('module_key', 50)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('content');
            $table->string('content_format', 10)->default('markdown');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_deleted'], 'idx_notes_user');
            $table->index(['book_osis_id', 'chapter_number'], 'idx_notes_reference');
        });

        Schema::create('pins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_number');
            $table->string('module_key', 50);
            $table->string('label', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->jsonb('vector_clock')->default('{}');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_deleted'], 'idx_pins_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pins');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('highlights');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('bookmark_folders');
    }
};
