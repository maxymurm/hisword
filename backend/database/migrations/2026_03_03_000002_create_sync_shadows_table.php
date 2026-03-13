<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_shadows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('entity_type', 30);    // bookmark, highlight, note, pin, reading_plan_progress
            $table->uuid('entity_id');
            $table->jsonb('shadow_data');          // Last-synced snapshot of the entity
            $table->timestamp('shadow_at');        // When this shadow was captured
            $table->unique(['device_id', 'entity_type', 'entity_id'], 'sync_shadows_device_entity_unique');
            $table->index(['user_id', 'device_id'], 'sync_shadows_user_device_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_shadows');
    }
};
