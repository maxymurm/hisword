<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HisWord API Routes (v1)
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api/v1 via RouteServiceProvider.
| Auth routes are public; all others require Sanctum token.
|
*/

Route::prefix('v1')->group(function () {

    // ── Public Auth Routes (rate limited) ─────────
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword']);
    });

    // ── Protected Routes (rate limited) ────────────
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::get('/user', [\App\Http\Controllers\Api\V1\AuthController::class, 'user']);
            Route::put('/user', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateUser']);
            Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::post('/logout-all', [\App\Http\Controllers\Api\V1\AuthController::class, 'logoutAll']);
        });

        // Bible Content
        Route::get('read/{moduleKey}/{book}/{chapter}', [\App\Http\Controllers\Api\V1\BibleController::class, 'read'])
            ->where('chapter', '[0-9]+');
        Route::get('modules/available', [\App\Http\Controllers\Api\V1\ModuleController::class, 'available']);
        Route::apiResource('modules', \App\Http\Controllers\Api\V1\ModuleController::class)->only(['index', 'show']);
        Route::post('modules/{module}/install', [\App\Http\Controllers\Api\V1\ModuleController::class, 'install']);
        Route::post('modules/{module}/uninstall', [\App\Http\Controllers\Api\V1\ModuleController::class, 'uninstall']);
        Route::get('modules/{module}/books', [\App\Http\Controllers\Api\V1\BibleController::class, 'books']);

        // Module Sources
        Route::apiResource('module-sources', \App\Http\Controllers\Api\V1\ModuleSourceController::class);
        Route::get('books/{book}/chapters', [\App\Http\Controllers\Api\V1\BibleController::class, 'chapters']);
        Route::get('chapters/{chapter}/verses', [\App\Http\Controllers\Api\V1\BibleController::class, 'verses']);
        Route::get('verses/{verse}', [\App\Http\Controllers\Api\V1\BibleController::class, 'showVerse']);

        // Search
        Route::get('search', [\App\Http\Controllers\Api\V1\SearchController::class, 'search']);
        Route::get('search/suggest', [\App\Http\Controllers\Api\V1\SearchController::class, 'suggest']);

        // Commentaries
        Route::get('commentaries', [\App\Http\Controllers\Api\V1\CommentaryController::class, 'index']);
        Route::get('commentaries/{module}', [\App\Http\Controllers\Api\V1\CommentaryController::class, 'show']);
        Route::get('commentaries/{module}/entry', [\App\Http\Controllers\Api\V1\CommentaryController::class, 'entry']);

        // Dictionaries
        Route::get('dictionaries', [\App\Http\Controllers\Api\V1\DictionaryController::class, 'index']);
        Route::get('dictionaries/{module}', [\App\Http\Controllers\Api\V1\DictionaryController::class, 'show']);
        Route::get('dictionaries/{module}/entry/{key}', [\App\Http\Controllers\Api\V1\DictionaryController::class, 'entry']);
        Route::get('dictionaries/{module}/entries', [\App\Http\Controllers\Api\V1\DictionaryController::class, 'entries']);

        // User Content (CRUD)
        Route::apiResource('bookmarks', \App\Http\Controllers\Api\V1\BookmarkController::class);
        Route::apiResource('bookmark-folders', \App\Http\Controllers\Api\V1\BookmarkFolderController::class);
        Route::apiResource('highlights', \App\Http\Controllers\Api\V1\HighlightController::class);
        Route::apiResource('notes', \App\Http\Controllers\Api\V1\NoteController::class);
        Route::apiResource('pins', \App\Http\Controllers\Api\V1\PinController::class);

        // History
        Route::get('history', [\App\Http\Controllers\Api\V1\HistoryController::class, 'index']);
        Route::post('history', [\App\Http\Controllers\Api\V1\HistoryController::class, 'store']);
        Route::delete('history', [\App\Http\Controllers\Api\V1\HistoryController::class, 'destroyAll']);
        Route::delete('history/{history}', [\App\Http\Controllers\Api\V1\HistoryController::class, 'destroy']);

        // Sync
        Route::post('sync/push', [\App\Http\Controllers\Api\V1\SyncController::class, 'push']);
        Route::post('sync/pull', [\App\Http\Controllers\Api\V1\SyncController::class, 'pull']);

        // Devices
        Route::apiResource('devices', \App\Http\Controllers\Api\V1\DeviceController::class);

        // Reading Plans
        Route::get('reading-plans/active', [\App\Http\Controllers\Api\V1\ReadingPlanController::class, 'active']);
        Route::get('reading-plans', [\App\Http\Controllers\Api\V1\ReadingPlanController::class, 'index']);
        Route::get('reading-plans/{plan}', [\App\Http\Controllers\Api\V1\ReadingPlanController::class, 'show']);
        Route::post('reading-plans/{plan}/subscribe', [\App\Http\Controllers\Api\V1\ReadingPlanController::class, 'subscribe']);
        Route::put('reading-plans/{plan}/progress', [\App\Http\Controllers\Api\V1\ReadingPlanController::class, 'updateProgress']);

        // User Preferences
        Route::get('settings', [\App\Http\Controllers\Api\V1\UserPreferenceController::class, 'index']);
        Route::put('settings', [\App\Http\Controllers\Api\V1\UserPreferenceController::class, 'update']);
        Route::post('settings/reset', [\App\Http\Controllers\Api\V1\UserPreferenceController::class, 'reset']);
        Route::get('settings/diff', [\App\Http\Controllers\Api\V1\UserPreferenceController::class, 'diff']);

        // Data Export/Import
        Route::post('export', [\App\Http\Controllers\Api\V1\DataExportController::class, 'export']);
        Route::post('import', [\App\Http\Controllers\Api\V1\DataExportController::class, 'import']);
        Route::post('import/preview', [\App\Http\Controllers\Api\V1\DataExportController::class, 'importPreview']);

        // Devotionals
        Route::get('devotionals', [\App\Http\Controllers\Api\V1\DevotionalController::class, 'index']);
        Route::get('devotionals/{module}/today', [\App\Http\Controllers\Api\V1\DevotionalController::class, 'today']);
        Route::get('devotionals/{module}/entry/{date}', [\App\Http\Controllers\Api\V1\DevotionalController::class, 'forDate']);

        // Verse of the Day
        Route::get('verse-of-the-day', [\App\Http\Controllers\Api\V1\VerseOfDayController::class, 'today']);
        Route::get('verse-of-the-day/{date}', [\App\Http\Controllers\Api\V1\VerseOfDayController::class, 'forDate']);

        // Cross-References & Footnotes
        Route::get('cross-references', [\App\Http\Controllers\Api\V1\CrossReferenceController::class, 'forVerse']);
        Route::get('cross-references/chapter', [\App\Http\Controllers\Api\V1\CrossReferenceController::class, 'forChapter']);

        // Audio Bible
        Route::get('audio/{module}/available', [\App\Http\Controllers\Api\V1\AudioController::class, 'available']);
        Route::get('audio/{module}/{book}', [\App\Http\Controllers\Api\V1\AudioController::class, 'bookChapters']);
        Route::get('audio/{module}/{book}/{chapter}', [\App\Http\Controllers\Api\V1\AudioController::class, 'stream'])
            ->where('chapter', '[0-9]+');
        Route::get('audio/next', [\App\Http\Controllers\Api\V1\AudioController::class, 'next']);

        // Push Notifications
        Route::post('notifications/subscribe', [\App\Http\Controllers\Api\V1\NotificationController::class, 'subscribe']);
        Route::post('notifications/unsubscribe', [\App\Http\Controllers\Api\V1\NotificationController::class, 'unsubscribe']);
        Route::put('notifications/preferences', [\App\Http\Controllers\Api\V1\NotificationController::class, 'updatePreferences']);
        Route::get('notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'history']);
        Route::get('notifications/unread-count', [\App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead']);
    });
});
