<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();      // e.g. "KJV", "ESV"
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->string('type', 20);                // bible, commentary, dictionary, devotional, genbook
            $table->string('language', 10);            // ISO code
            $table->string('version', 20)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->boolean('is_installed')->default(false);
            $table->boolean('is_bundled')->default(false);
            $table->jsonb('features')->default('[]');  // ["strongs", "morph", "footnotes"]
            $table->timestamps();
        });

        Schema::create('module_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('caption');
            $table->string('type', 10);               // FTP, HTTP
            $table->string('server');
            $table->string('directory', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_refreshed')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_sources');
        Schema::dropIfExists('modules');
    }
};
