<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform'); // android, ios, web
            $table->text('token'); // FCM token, APNs device token, web push subscription JSON
            $table->json('preferences')->nullable(); // notification type toggles
            $table->string('quiet_hours_start')->nullable(); // HH:MM
            $table->string('quiet_hours_end')->nullable(); // HH:MM
            $table->string('timezone')->default('UTC');
            $table->string('daily_reminder_time')->nullable(); // HH:MM
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->index(['is_active', 'platform']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // verse_of_day, reading_plan, new_module, sync
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // deep link data, etc.
            $table->string('status')->default('sent'); // sent, delivered, read, failed
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('push_subscriptions');
    }
};
