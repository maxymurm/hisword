# HisWord — Project Documentation

**Last Updated:** 2026-03-12
**Status:** 🟢 Active Development

---

## Overview

HisWord is the unified Bible study app created by merging:
1. **androidbible / BibleCMP** — YES2/Bintex engine, goldenBowl sync, Compose Multiplatform port
2. **PocketSword** — SWORD engine (PHP + Kotlin KMP), React web frontend, advanced study tools

**Why merge?** Both projects independently converged on the exact same KMP technology stack. SWORD gives 10,000+ free CrossWire modules; YES2 gives ~100 premium SEA/Indonesian versions unavailable on CrossWire. Together they make one unmatched Bible app.

---

## Technology Stack

### Backend (`/backend`)
| Component | Technology |
|-----------|------------|
| Framework | Laravel 12, PHP 8.4 |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7 + Laravel Horizon |
| WebSocket | Laravel Reverb (Pusher protocol) |
| Search | Meilisearch |
| Auth | Laravel Sanctum (Bearer/Session) |
| Admin | Filament 3 |
| Web Frontend | Inertia.js + React 19 + Tailwind CSS 4 |
| CI/CD | GitHub Actions + Fastlane |

### Mobile (`/mobile`)
| Component | Technology |
|-----------|------------|
| UI | Compose Multiplatform 2.x |
| Language | Kotlin 2.1+ |
| DI | Koin 4 |
| Database | SQLDelight 2 |
| Networking | Ktor 3 |
| Serialization | kotlinx.serialization |
| Background | WorkManager (Android) / BGTasks (iOS) |

---

## What's Already Built (From Legacy Repos)

### SWORD PHP Engine (`backend/app/Services/Sword/`) — COMPLETE
- All 7 binary readers: ZText, RawText, ZCom, RawCom, ZLD, RawLD4, RawGenBook
- All 5 markup filters: OSIS, GBF, ThML, TEI, Plain
- 8 versification systems: KJV, KJVA, NRSV, Catholic, Synodal, German, Luther, Vulgate
- FTS5 full-text search (SwordSearcher)
- ModuleInstaller + RepositoryBrowser (CrossWire catalog)
- **84 PHPUnit tests passing** (commit c33a924)

### SWORD Kotlin Engine (`mobile/shared/src/commonMain/data/sword/`) — COMPLETE
- Pure Kotlin KMP readers: ZTextReader, RawComReader, RawLD4Reader, ZLDReader
- OsisTextFilter (OSIS → AnnotatedString)
- KjvVersification
- **Genesis 1 loads in <12ms** on-device (commit ac4c22f)

### Web Frontend (`backend/resources/js/`) — PHASE 8 COMPLETE
- 11 React pages: Reader, Search, Modules, Bookmarks, Highlights, Notes, Pins, Home, Auth (5), Onboarding
- Reader: verse selection, 8-color highlights, commentary panel, Strong's popup, audio Bible, export images
- PWA: service worker, offline support, manifest

### goldenBowl Sync Protocol — COMPLETE (in androidbible-api, to be ported)
- Delta sync with revision tracking + SyncShadow conflict resolution
- POST /api/sync/ with markers/labels/progress_marks
- Reverb WebSocket broadcasting with echo prevention
- Sanctum Bearer auth on all routes including /api/broadcasting/auth

### KMP Mobile Scaffolding — PHASES 1–12 COMPLETE (in androidbible-kmp)
- Gradle version catalog, Koin, SQLDelight, Ktor all configured
- Basic Marker, Label, Ari models
- Initial sync engine skeleton

---

## Phase Roadmap

### Backend Track

#### BE Phase 1: Backend Migration & Foundation
**Goals:** Copy pocketsword/backend to hisword/backend/, rebrand HisWord, verify all tests pass
- Copy backend code, update composer.json (name, namespace com.adventdigital.hisword)
- Update .env.example (HisWord config)
- Run 84 PHPUnit tests — must all pass in new location
- Rebrand web routes, page titles, email templates

#### BE Phase 2: YES2/Bintex PHP Engine
**Goals:** Port the Java YES2 binary readers to PHP (mirror what Services/Sword/ does for SWORD)
- `BintexReader.php` — port BintexReader.java
- `SnappyDecompressor.php` — port SnappyImplJava.java (pure Java, NOT JNI)
- `Yes2Reader.php` — open file, section index, VersionInfo, BooksInfo, TextSection, Pericopes, Footnotes, Xrefs
- `Yes1Reader.php` — legacy YES1 format
- `BintexManager.php` — high-level API: `readVerse($module, $book, $chap, $verse)`, `readChapter(...)`, `indexForSearch()`
- 6 artisan commands: `bintex:install`, `bintex:read`, `bintex:list`, `bintex:index`, `bintex:search`, `bintex:test`
- PHPUnit tests for Bintex engine (parse real YES2 file, verify verse text, UTF-8)

#### BE Phase 3: Unified Module Model
**Goals:** Single Module model handles both SWORD and YES2, unified verse-reading API
- Add `engine enum('sword','bintex')` column to modules table
- `BibleReaderFactory.php` — routes to SwordManager or BintexManager by engine
- `GET /api/v1/read/{moduleKey}/{ref}` — unified verse/chapter endpoint
- `GET /api/v1/modules` — list all modules with engine type, category, locale
- Update ModuleController to support both module types

#### BE Phase 4: goldenBowl Sync Integration
**Goals:** Port the proven delta sync protocol from androidbible-api
- SyncController with full POST/GET/delta/status endpoints
- SyncShadow model (conflict detection)
- SyncService (upsert markers, labels, progress_marks, revision tracking)
- BroadcastingAuthController (Sanctum Bearer — NOT session cookies)
- Events: MarkerCreated, MarkerUpdated, MarkerDeleted, LabelUpdated, ProgressUpdated
- Echo prevention: events carry `device_id`, clients skip own-device events

#### BE Phase 5: YES2 Version Catalog API
**Goals:** Serve YES2 files for download (goldenBowl catalog pattern)
- `GET /api/v1/catalog/versions` — list available YES2 versions with locale, size, checksum
- `GET /api/v1/catalog/versions/{id}/download` — stream YES2 file with auth
- YES2Catalog seeder from goldenBowl catalog data
- InstalledVersion tracking per user

#### BE Phase 6: Data Migration from Legacy Repos
**Goals:** Migrate existing user data from androidbible-api and pocketsword into HisWord DB
- Migration script: export androidbible-api users + markers + labels + plans
- Migration script: export pocketsword users + bookmarks + highlights + notes
- Merge by email (deduplicate); preserve all GIDs
- Test: no data loss, verify marker counts match

#### BE Phase 7: Web App Completion
**Goals:** Complete remaining web features from pocketsword Phase 7–8 backlog
- Reading Plans page (issue #121), Settings page (#122)
- Word study panel / Strong's concordance (#124)
- Study pad / personal commentary (#127)
- Tag/collection system (#125)
- DOCX/PDF export (#128)

#### BE Phase 8: Production Deployment
**Goals:** HisWord backend live on DigitalOcean/Forge
- Forge setup (PHP 8.4, PostgreSQL 16, Redis, Meilisearch, Reverb)
- .env hardening, HSTS, rate-limiting, CORS
- Zero-downtime deployment via GitHub Actions
- PostgreSQL backup (daily snapshots)
- Sentry/Flare error reporting

---

### Mobile Track

#### MB Phase 1: Mobile Foundation
**Goals:** Set up hisword/mobile/ with correct package name, dependencies combining both repos
- Structure based on androidbible-kmp (YES2 focus), package `com.adventdigital.hisword`
- Gradle version catalog with all deps: Compose MP, Koin 4, SQLDelight 2, Ktor 3
- Copy pocketsword SWORD engine to `shared/src/commonMain/data/sword/`
- Copy androidbible-kmp progress (binary reader stubs)
- Verify `assembleDebug` BUILD SUCCESSFUL

#### MB Phase 2: YES2 Binary Readers
**Goals:** Complete YES2/Bintex Kotlin port (continuing androidbible-kmp phases 13-14)
- BintexReader.kt, BintexWriter.kt
- SnappyCodec.kt (pure Java port, NOT JNI), SnappyInputStream.kt
- RandomAccessSource expect/actual (Android=RandomAccessFile, iOS=NSFileHandle, Desktop=RandomAccessFile)
- Yes2Reader.kt: section index, VersionInfoSection, BooksInfoSection, TextSection (UTF-8), FootnotesSection, XrefsSection, PericopesSection
- Yes1Reader.kt (legacy)
- Data models: Ari.kt, Marker.kt (Kind: BOOKMARK=1, NOTE=2, HIGHLIGHT=3), Label.kt, Book.kt, VersionInfo.kt
- SQLDelight schema: Marker, Label, MarkerLabel, Module, InstalledVersion, SyncState
- Integration tests: parse real YES2 file on Android + Desktop

#### MB Phase 3: SWORD Engine Integration
**Goals:** SWORD KMP engine fully operational in hisword/mobile (continuing pocketsword issues #77–#79)
- iOS SwordModuleInitializer.ios.kt (NSBundle extraction — currently stub)
- Commentary + Dictionary panel testing (MHCC, Strong's, Robinson)
- Module download from CrossWire on-device test
- All bundled ZIPs working: KJV, MHCC, Strong's, Robinson

#### MB Phase 4: BibleReaderInterface Abstraction
**Goals:** Unified interface so all UI code is engine-agnostic
- `BibleReaderInterface` in commonMain: `loadChapter(book, chapter): List<VerseItemData>`, `loadPericope(ari)`, `loadFootnotes(ari)`, `loadXrefs(ari)`
- `Yes2BibleReader` implements BibleReaderInterface (wraps Yes2Reader + BintexReader)
- `SwordBibleReader` implements BibleReaderInterface (wraps SwordManager)
- `BibleReaderFactory.kt` — routes by `module.engine`
- `FormattedVerseText` — dispatches to YES2 markup parser OR OsisTextFilter
- `BibleVersionRepository` — lists + activates modules from both engines
- `VersionsScreen` updated: "Bible Versions" (goldenBowl YES2 catalog) + "SWORD Modules" (CrossWire catalog) tabs

#### MB Phase 5: Core Reader UI
**Goals:** Full Bible reader screen working for both engine types
- GetChapterUseCase (engine-agnostic via BibleReaderInterface)
- BibleReaderViewModel: StateFlow-driven, loadChapter(ari), nextChapter(), prevChapter()
- VerseItem, PericopeHeader, VerseList (LazyColumn)
- ChapterPager (HorizontalPager with swipe)
- BibleReaderScreen (Scaffold + Drawer + ChapterPager)
- TextAppearancePanel (font size, face, colors)
- Night mode (Material3 dark theme)
- FootnotePanel, XrefPanel

#### MB Phase 6: Navigation & Search
**Goals:** Full navigation system and cross-engine search
- GotoScreen: BookGrid, DialerMode, DirectMode
- SearchUseCase: queries across all installed modules (YES2 + SWORD)
- SearchScreen, SearchFilters (OT/NT, book, regex)
- SplitReader (parallel two-pane)
- DeepLink parsing (HisWord:// scheme for both engines)
- Readinghistory

#### MB Phase 7: Markers System
**Goals:** Markers work across all module types (SWORD + YES2)
- Verse context menu (long-press): bookmark, highlight, note
- HighlightColorPicker (8 colors), NoteEditor, CreateMarkerUseCase
- MarkersScreen (tabs: Bookmarks/Notes/Highlights), MarkersViewModel
- Inline marker indicators in VerseItem
- LabelsScreen, LabelEditor, label assignment (many-to-many)
- SWORD-specific: Commentary panel, Strong's number popup, DictionaryPopup

#### MB Phase 8: Sync, Auth & Content
**Goals:** Full sync with unified HisWord backend
- AuthApi.kt + AuthRepository (Sanctum, Google, Apple Sign-In)
- TokenStorage expect/actual (EncryptedSharedPreferences / Keychain)
- SyncApi.kt + SyncEngine.kt (goldenBowl delta sync)
- ConflictResolver (last-write-wins by modifyTime)
- ReverbClient.kt WebSocket (Pusher protocol, echo prevention)
- Background sync: WorkManager (Android) / BGTasks (iOS)
- Reading Plans: ReadingPlansScreen, PlanProgressView, ReadingPlanUseCase
- Songs (KpriModel from androidbible): SongDatabase.sq, SongsScreen, SongDetailScreen

#### MB Phase 9: Platform Polish & Release
**Goals:** Ship HisWord to all stores
- Share verse text + clipboard (expect/actual)
- Export/import markers JSON
- Android Glance widget (Verse of the Day)
- App icons + splash (HisWord branding)
- Sentry KMP crash reporting
- Google Play listing + App Store submission + Desktop GitHub Releases

---

## API Endpoints Reference

```
POST /api/auth/login               email+password → Sanctum token
POST /api/auth/register
POST /api/auth/oauth/google         Google ID token → Sanctum token
POST /api/auth/oauth/apple          Apple JWT → Sanctum token (JWKS verified)
POST /api/auth/logout
GET  /api/user

POST /api/sync/                     delta sync (markers, labels, progress_marks)
GET  /api/sync/status
GET  /api/sync/full
GET  /api/sync/delta?since=N
POST /api/sync/device
POST /api/broadcasting/auth         Sanctum Bearer (NOT session)

GET  /api/v1/modules                list all installed modules (sword + bintex)
GET  /api/v1/modules/{key}          module metadata
GET  /api/v1/read/{key}/{ref}       read verse/chapter (unified, both engines)
GET  /api/v1/search?q=&module=      FTS5 search

GET  /api/v1/catalog/versions       YES2 version catalog
GET  /api/v1/catalog/versions/{id}/download
GET  /api/v1/catalog/sword          CrossWire module catalog

GET  /api/v1/plans/                 reading plans list
GET  /api/v1/devotion/today         daily devotional
GET  /api/v1/votd                   verse of the day
GET  /api/v1/songs/                 song catalog
```

---

## Setup Instructions

### Backend
```bash
cd hisword/backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed

# SWORD modules
php artisan sword:install-bundled

# Run tests
php artisan test
```

### Mobile
```bash
cd hisword/mobile
./gradlew :shared:compileKotlinAndroid
./gradlew :composeApp:assembleDebug
```

---

## Change Log

### 2026-03-12
- Project created from merger of androidbible-api, androidbible-kmp, and pocketsword
- Architecture: unified backend (Laravel 12) + unified mobile (Compose Multiplatform)
- Dual-engine: SWORD (PHP + Kotlin complete) + YES2/Bintex (PHP to build, Kotlin in progress)
- BibleReaderInterface as the unification seam
