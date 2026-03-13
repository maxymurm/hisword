<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_bibles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module_key', 50)->index();
            $table->string('book_osis_id', 10)->index();
            $table->unsignedSmallInteger('chapter_number');
            $table->string('storage_path'); // S3/R2 path
            $table->string('storage_disk')->default('s3'); // filesystem disk
            $table->unsignedInteger('duration')->nullable(); // seconds
            $table->unsignedInteger('file_size')->nullable(); // bytes
            $table->string('format', 10)->default('mp3'); // mp3, ogg, aac
            $table->string('narrator')->nullable();
            $table->string('language', 10)->default('en');
            $table->json('verse_timings')->nullable(); // [{verse: 1, start: 0.0, end: 5.2}, ...]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module_key', 'book_osis_id', 'chapter_number'], 'audio_bibles_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_bibles');
    }
};
