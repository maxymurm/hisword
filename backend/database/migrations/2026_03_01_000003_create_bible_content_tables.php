<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('module_id')->constrained()->cascadeOnDelete();
            $table->string('osis_id', 20);             // "Gen", "Exod"
            $table->string('name', 100);
            $table->string('abbreviation', 20)->nullable();
            $table->string('testament', 5);             // OT, NT
            $table->smallInteger('book_order');
            $table->smallInteger('chapter_count');
            $table->unique(['module_id', 'osis_id']);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_count');
            $table->unique(['book_id', 'chapter_number']);
        });

        Schema::create('verses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('module_id')->constrained()->cascadeOnDelete();
            $table->string('book_osis_id', 20);
            $table->smallInteger('chapter_number');
            $table->smallInteger('verse_number');
            $table->text('text_raw');
            $table->text('text_rendered')->nullable();
            $table->jsonb('strongs_data')->nullable();
            $table->jsonb('morphology_data')->nullable();
            $table->jsonb('footnotes')->nullable();
            $table->jsonb('cross_refs')->nullable();
            $table->unique(['module_id', 'book_osis_id', 'chapter_number', 'verse_number'], 'verses_ref_unique');
            $table->index(['module_id', 'book_osis_id', 'chapter_number'], 'idx_verses_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verses');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('books');
    }
};
