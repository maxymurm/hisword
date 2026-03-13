<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'email_verified_at', 'password',
            'avatar_url', 'provider', 'provider_id', 'timezone', 'locale',
            'remember_token', 'created_at', 'updated_at',
        ]));
    }

    public function test_devices_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('devices'));
        $this->assertTrue(Schema::hasColumns('devices', [
            'id', 'user_id', 'device_id', 'platform', 'name',
            'push_token', 'last_sync_at', 'app_version',
        ]));
    }

    public function test_modules_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('modules'));
        $this->assertTrue(Schema::hasColumns('modules', [
            'id', 'key', 'name', 'type', 'language', 'features',
        ]));
    }

    public function test_bible_content_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('books'));
        $this->assertTrue(Schema::hasTable('chapters'));
        $this->assertTrue(Schema::hasTable('verses'));
    }

    public function test_user_content_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('bookmark_folders'));
        $this->assertTrue(Schema::hasTable('bookmarks'));
        $this->assertTrue(Schema::hasTable('highlights'));
        $this->assertTrue(Schema::hasTable('notes'));
        $this->assertTrue(Schema::hasTable('pins'));
    }

    public function test_history_and_sync_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('history'));
        $this->assertTrue(Schema::hasTable('reading_plans'));
        $this->assertTrue(Schema::hasTable('reading_plan_progress'));
        $this->assertTrue(Schema::hasTable('user_preferences'));
        $this->assertTrue(Schema::hasTable('sync_logs'));
    }

    public function test_bookmarks_has_sync_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('bookmarks', [
            'is_deleted', 'vector_clock', 'deleted_at',
        ]));
    }

    public function test_highlights_has_sync_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('highlights', [
            'is_deleted', 'vector_clock', 'deleted_at',
        ]));
    }

    public function test_notes_has_sync_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('notes', [
            'is_deleted', 'vector_clock', 'deleted_at',
        ]));
    }
}
