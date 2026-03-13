<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('platform', 20); // android, ios, web
            $table->string('name')->nullable();
            $table->string('push_token', 500)->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
