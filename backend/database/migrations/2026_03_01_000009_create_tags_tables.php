<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->default('gray');
            $table->string('description', 500)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->json('vector_clock')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->uuid('tag_id');
            $table->uuidMorphs('taggable');
            $table->timestamps();

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
