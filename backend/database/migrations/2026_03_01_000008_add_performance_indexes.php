<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Verses: compound index for chapter lookups (critical for reader) ──
        if (Schema::hasTable('verses') && ! $this->indexExists('verses', 'verses_chapter_lookup_index')) {
            Schema::table('verses', function (Blueprint $table) {
                $table->index(
                    ['module_id', 'book_osis_id', 'chapter_number', 'verse_number'],
                    'verses_chapter_lookup_index'
                );
            });
        }

        // ── Verses: full-text index for search (PostgreSQL) ──
        if (Schema::hasTable('verses') && config('database.default') === 'pgsql') {
            if (! $this->indexExists('verses', 'verses_text_raw_fulltext')) {
                \Illuminate\Support\Facades\DB::statement(
                    "CREATE INDEX verses_text_raw_fulltext ON verses USING gin(to_tsvector('english', text_raw))"
                );
            }
        }

        // ── Highlights: user chapter lookup ──
        if (Schema::hasTable('highlights') && ! $this->indexExists('highlights', 'highlights_user_chapter_index')) {
            Schema::table('highlights', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'book_osis_id', 'chapter_number', 'is_deleted'],
                    'highlights_user_chapter_index'
                );
            });
        }

        // ── Notes: user chapter lookup ──
        if (Schema::hasTable('notes') && ! $this->indexExists('notes', 'notes_user_chapter_index')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'book_osis_id', 'chapter_number', 'is_deleted'],
                    'notes_user_chapter_index'
                );
            });
        }

        // ── Bookmarks: user sorted ──
        if (Schema::hasTable('bookmarks') && ! $this->indexExists('bookmarks', 'bookmarks_user_sorted_index')) {
            Schema::table('bookmarks', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'is_deleted', 'created_at'],
                    'bookmarks_user_sorted_index'
                );
            });
        }

        // ── Pins: user sorted ──
        if (Schema::hasTable('pins') && ! $this->indexExists('pins', 'pins_user_sorted_index')) {
            Schema::table('pins', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'is_deleted', 'created_at'],
                    'pins_user_sorted_index'
                );
            });
        }

        // ── Modules: type + installed lookup ──
        if (Schema::hasTable('modules') && ! $this->indexExists('modules', 'modules_type_installed_index')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->index(
                    ['type', 'is_installed', 'name'],
                    'modules_type_installed_index'
                );
            });
        }

        // ── User Preferences: user + key ──
        if (Schema::hasTable('user_preferences') && ! $this->indexExists('user_preferences', 'user_preferences_user_key_index')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'key'],
                    'user_preferences_user_key_index'
                );
            });
        }

        // ── Notification Logs: user + read status ──
        if (Schema::hasTable('notification_logs') && ! $this->indexExists('notification_logs', 'notification_logs_user_unread_index')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'status', 'created_at'],
                    'notification_logs_user_unread_index'
                );
            });
        }

        // ── Push Subscriptions: user + active ──
        if (Schema::hasTable('push_subscriptions') && ! $this->indexExists('push_subscriptions', 'push_subscriptions_user_active_index')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->index(
                    ['user_id', 'is_active', 'platform'],
                    'push_subscriptions_user_active_index'
                );
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'verses' => ['verses_chapter_lookup_index'],
            'highlights' => ['highlights_user_chapter_index'],
            'notes' => ['notes_user_chapter_index'],
            'bookmarks' => ['bookmarks_user_sorted_index'],
            'pins' => ['pins_user_sorted_index'],
            'modules' => ['modules_type_installed_index'],
            'user_preferences' => ['user_preferences_user_key_index'],
            'notification_logs' => ['notification_logs_user_unread_index'],
            'push_subscriptions' => ['push_subscriptions_user_active_index'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($tableIndexes) {
                    foreach ($tableIndexes as $idx) {
                        $t->dropIndex($idx);
                    }
                });
            }
        }

        if (config('database.default') === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS verses_text_raw_fulltext');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = config('database.default');
            if ($driver === 'sqlite') {
                $indexes = \Illuminate\Support\Facades\DB::select(
                    "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",
                    [$table, $indexName]
                );

                return count($indexes) > 0;
            }

            if ($driver === 'pgsql') {
                $indexes = \Illuminate\Support\Facades\DB::select(
                    "SELECT indexname FROM pg_indexes WHERE tablename=? AND indexname=?",
                    [$table, $indexName]
                );

                return count($indexes) > 0;
            }

            // MySQL
            $indexes = \Illuminate\Support\Facades\DB::select(
                "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                [$indexName]
            );

            return count($indexes) > 0;
        } catch (\Exception) {
            return false;
        }
    }
};
