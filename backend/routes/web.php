<?php

use App\Http\Controllers\Web\AnnotationsController;
use App\Http\Controllers\Web\AudioController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DeepLinkController;
use App\Http\Controllers\Web\ModuleController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\ReadingPlanController;
use App\Http\Controllers\Web\ReaderController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SupplementaryController;
use App\Http\Controllers\Web\VerseImageController;
use App\Http\Controllers\Web\WordStudyController;
use App\Http\Controllers\Web\TagController;
use App\Http\Controllers\Web\StatisticsController;
use App\Http\Controllers\Web\StudyPadController;
use App\Http\Controllers\Web\ExportController;
use App\Http\Controllers\Web\PrintController;
use App\Http\Controllers\Web\ModuleWizardController;
use App\Http\Controllers\Web\CrossReferenceController as WebCrossRefController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\VerseOfDayController;
use App\Http\Controllers\Web\DevotionalController;
use App\Http\Controllers\Web\DataTransferController;
use App\Http\Controllers\Web\AdminController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/offline', function () {
    return response()->file(public_path('offline.html'));
})->name('offline');

// ── Bible Reader ────────────────────────────────────────────────
Route::get('/read/{module?}/{book?}/{chapter?}', [ReaderController::class, 'show'])
    ->where('chapter', '[0-9]+')
    ->name('reader');
Route::get('/api/read/{module}/{book}/{chapter}', [ReaderController::class, 'verses'])
    ->where('chapter', '[0-9]+')
    ->name('reader.verses');

// ── Search ──────────────────────────────────────────────────────
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/query', [SearchController::class, 'query'])->name('search.query');

// ── Word Study ──────────────────────────────────────────────────
Route::get('/word-study', [WordStudyController::class, 'show'])->name('word-study');

// ── Cross References ────────────────────────────────────────────
Route::get('/cross-references', [WebCrossRefController::class, 'index'])->name('cross-references');
Route::get('/cross-references/lookup', [WebCrossRefController::class, 'lookup'])->name('cross-references.lookup');

// ── Print Templates ─────────────────────────────────────────────
Route::get('/print/{module}/{book}/{chapter}', [PrintController::class, 'chapter'])
    ->where('chapter', '[0-9]+')
    ->name('print.chapter');
Route::get('/print/{module}/{book}/{chapter}/{verseStart}-{verseEnd}', [PrintController::class, 'passage'])
    ->where(['chapter' => '[0-9]+', 'verseStart' => '[0-9]+', 'verseEnd' => '[0-9]+'])
    ->name('print.passage');

// ── Audio Bible ─────────────────────────────────────────────────
Route::get('/audio/{module}/{book}/{chapter}', [AudioController::class, 'stream'])
    ->where('chapter', '[0-9]+')
    ->name('audio.stream');
Route::get('/audio/check/{module}/{book}/{chapter}', [AudioController::class, 'check'])
    ->where('chapter', '[0-9]+')
    ->name('audio.check');

// ── Verse Image Generation ──────────────────────────────────────
Route::post('/verse-image/generate', [VerseImageController::class, 'generate'])->name('verse-image.generate');
Route::get('/verse-image/templates', [VerseImageController::class, 'templates'])->name('verse-image.templates');

// ── Module Library ──────────────────────────────────────────────
Route::get('/modules', [ModuleController::class, 'index'])->name('modules');

// ── Module Wizard ───────────────────────────────────────────────
Route::get('/module-wizard', [ModuleWizardController::class, 'index'])->name('module-wizard');
Route::get('/module-wizard/modules', [ModuleWizardController::class, 'modules'])->name('module-wizard.modules');

// ── Supplementary Content (Commentary & Dictionary) ─────────────
Route::get('/commentary/{module}/{book}/{chapter}', [SupplementaryController::class, 'commentary'])
    ->where('chapter', '[0-9]+')
    ->name('commentary');
Route::get('/dictionary/{module}/{key}', [SupplementaryController::class, 'dictionary'])
    ->where('key', '.+')
    ->name('dictionary');
Route::get('/dictionary/{module}', [SupplementaryController::class, 'dictionaryKeys'])
    ->name('dictionary.keys');
Route::post('/modules/{module}/install', [ModuleController::class, 'install'])->name('modules.install');
Route::post('/modules/{module}/uninstall', [ModuleController::class, 'uninstall'])->name('modules.uninstall');
Route::post('/modules/install-bundled', [ModuleController::class, 'installBundled'])->name('modules.install-bundled');
Route::post('/modules/refresh-sources', [ModuleController::class, 'refreshSources'])->name('modules.refresh-sources');
Route::get('/modules/{module}/progress', [ModuleController::class, 'progress'])->name('modules.progress');
Route::get('/modules/{module}/progress-poll', [ModuleController::class, 'progressPoll'])->name('modules.progress-poll');
Route::get('/modules/{module}/details', [ModuleController::class, 'show'])->name('modules.show');

// ── Onboarding ──────────────────────────────────────────────────
Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
Route::get('/onboarding/status', [OnboardingController::class, 'status'])->name('onboarding.status');

// ── Reading Plans ────────────────────────────────────────────────
Route::get('/plans', [ReadingPlanController::class, 'index'])->name('plans');
Route::get('/plans/{slug}', [ReadingPlanController::class, 'show'])->name('plans.show');

// ── Settings ────────────────────────────────────────────────────
Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

// ── Statistics ──────────────────────────────────────────────────
Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');

// ── Verse of the Day ────────────────────────────────────────────
Route::get('/verse-of-day', [VerseOfDayController::class, 'show'])->name('verse-of-day');

// ── Devotionals ─────────────────────────────────────────────────
Route::get('/devotionals', [DevotionalController::class, 'index'])->name('devotionals');
Route::get('/devotionals/{module}', [DevotionalController::class, 'show'])->name('devotionals.show');

// ── Deep Links & Universal Links ────────────────────────────────
Route::get('/.well-known/assetlinks.json', [DeepLinkController::class, 'assetLinks']);
Route::get('/.well-known/apple-app-site-association', [DeepLinkController::class, 'appleAppSiteAssociation']);
Route::post('/deeplink/share', [DeepLinkController::class, 'generateShareLink'])->name('deeplink.share');
Route::post('/deeplink/bookmark', [DeepLinkController::class, 'bookmarkLink'])->name('deeplink.bookmark');
Route::post('/deeplink/search', [DeepLinkController::class, 'searchLink'])->name('deeplink.search');
Route::get('/link/{module}/{book}/{chapter}', [DeepLinkController::class, 'resolve'])
    ->where('chapter', '[0-9]+')
    ->name('deeplink.resolve');

// ── Guest routes ────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
});

// ── Authenticated routes ────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/verify-email', [AuthController::class, 'showVerifyEmail'])
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [AuthController::class, 'deleteAccount'])->name('profile.destroy');

    // ── Annotations ─────────────────────────────────────────────
    Route::get('/bookmarks', [AnnotationsController::class, 'bookmarks'])->name('bookmarks');
    Route::get('/notes', [AnnotationsController::class, 'notes'])->name('notes');
    Route::get('/highlights', [AnnotationsController::class, 'highlights'])->name('highlights');
    Route::get('/pins', [AnnotationsController::class, 'pins'])->name('pins');

    // ── Reading History ─────────────────────────────────────────
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::delete('/history', [HistoryController::class, 'destroy'])->name('history.destroy');

    // ── Tags & Collections ──────────────────────────────────────
    Route::get('/tags', [TagController::class, 'index'])->name('tags');
    Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
    Route::get('/tags/{tag}', [TagController::class, 'show'])->name('tags.show');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

    // ── Study Pad ───────────────────────────────────────────────
    Route::get('/study-pad', [StudyPadController::class, 'index'])->name('study-pad');
    Route::post('/study-pad', [StudyPadController::class, 'store'])->name('study-pad.store');
    Route::get('/study-pad/{note}', [StudyPadController::class, 'show'])->name('study-pad.show');
    Route::put('/study-pad/{note}', [StudyPadController::class, 'update'])->name('study-pad.update');
    Route::delete('/study-pad/{note}', [StudyPadController::class, 'destroy'])->name('study-pad.destroy');

    // ── Export ──────────────────────────────────────────────────
    Route::get('/export', fn () => \Inertia\Inertia::render('Export'))->name('export');
    Route::get('/export/notes', [ExportController::class, 'exportNotes'])->name('export.notes');
    Route::get('/export/bookmarks', [ExportController::class, 'exportBookmarks'])->name('export.bookmarks');
    Route::get('/export/highlights', [ExportController::class, 'exportHighlights'])->name('export.highlights');

    // ── Data Transfer (Import/Export) ───────────────────────────
    Route::get('/data-transfer', [DataTransferController::class, 'index'])->name('data-transfer');
    Route::get('/data-transfer/export', [DataTransferController::class, 'export'])->name('data-transfer.export');
    Route::post('/data-transfer/preview', [DataTransferController::class, 'preview'])->name('data-transfer.preview');
    Route::post('/data-transfer/import', [DataTransferController::class, 'import'])->name('data-transfer.import');

    // ── Notifications ───────────────────────────────────────────
    Route::post('/notifications/subscribe', [NotificationController::class, 'subscribe'])->name('notifications.subscribe');
    Route::post('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe'])->name('notifications.unsubscribe');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');

    // ── Admin Panel ─────────────────────────────────────────────
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/modules', [AdminController::class, 'modules'])->name('admin.modules');
});
