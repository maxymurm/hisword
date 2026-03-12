---
applyTo: '**'
lastUpdated: '2026-03-12'
chatSession: 'session-001'
projectName: 'HisWord'
---

# Project Memory — HisWord

> **AGENT INSTRUCTIONS:** Always read this file FIRST before starting any new conversation. Update after completing tasks, making decisions, or when user says "remember this".

---

## Current Focus

**Active Phase:** Phase BE-1 — Backend Migration & Foundation
**Active Issue:** None yet (project just initialized)
**Current Branch:** main
**Last Activity:** 2026-03-12 — Project created by merging androidbible + pocketsword

**Project Origin:**
- `androidbible-api` (goldenBowl, Laravel 11) → merged into `/backend`
- `androidbible-kmp` (BibleCMP, Compose Multiplatform YES2) → merged into `/mobile`
- `pocketsword` (SWORD engine, Laravel 12 + React + KMP) → merged into `/backend` and `/mobile`

---

## Project Identity

- **App Name:** HisWord
- **Package:** `com.adventdigital.hisword`
- **GitHub:** https://github.com/maxymurm/hisword
- **Backend URL (prod):** TBD
- **Developer:** Maxwell Murunga (@maxymurm) / Advent Digital

---

## Tech Stack

### Backend (`/backend`)
- Laravel 12, PHP 8.4
- PostgreSQL 16, Redis 7, Meilisearch
- Laravel Reverb (WebSocket, Pusher protocol)
- Laravel Horizon (queues), Laravel Sanctum (auth), Filament 3 (admin)
- Inertia.js + React 19 + Tailwind CSS 4 (web frontend)

### Mobile (`/mobile`)
- Compose Multiplatform 2.x (Android + iOS + Desktop)
- Kotlin 2.1+, 100% commonMain where possible
- Koin 4 (DI), SQLDelight 2, Ktor 3, kotlinx.serialization
- Pusher/Reverb WebSocket via Ktor

---

## Dual-Engine Architecture

### The Two Engines

| Engine | Format | Source | Modules |
|--------|--------|--------|---------|
| **YES2/Bintex** | `.yes` binary (YES2/YES1) | goldenBowl catalog | ~100 SEA/Indonesian versions |
| **SWORD** | `.bzs/.bzv/.bzz` (zText), `.vss` (rawText), `.idx/.dat` (RawLD4/zLD) | CrossWire repositories | 10,000+ free Bible versions, commentaries, dictionaries, lexicons |

### PHP Backend Engines
```
backend/app/Services/
├── Sword/                    ← COMPLETE (84 PHPUnit tests passing)
│   ├── SwordManager.php      ← High-level API (readVerse, readChapter)
│   ├── ConfParser.php        ← .conf file parser
│   ├── SwordSearcher.php     ← FTS5 full-text search
│   ├── ModuleInstaller.php   ← Download + extract + index ZIPs
│   ├── RepositoryBrowser.php ← CrossWire catalog via mods.d.tar.gz
│   └── readers/              ← ZTextReader, RawTextReader, ZComReader, ZLDReader, etc.
└── Bintex/                   ← TO BUILD (PHP port of androidbible Java readers)
    ├── BintexReader.php       ← Port BintexReader.java
    ├── SnappyDecompressor.php ← Port SnappyImplJava.java (pure Java, NOT JNI)
    ├── Yes2Reader.php         ← Port Yes2Reader.java
    ├── Yes1Reader.php         ← Port Yes1Reader.java
    └── BintexManager.php     ← High-level API (readVerse, readChapter, searchIndex)
```

### Kotlin Mobile Engines
```
mobile/shared/src/commonMain/kotlin/
├── data/
│   ├── sword/                ← COMPLETE (from pocketsword, <12ms Genesis 1)
│   │   ├── reader/           ← ZTextReader, RawComReader, RawLD4Reader, ZLDReader
│   │   ├── osis/             ← OsisTextFilter (OSIS markup → AnnotatedString)
│   │   ├── SwordManager.kt
│   │   ├── SwordModuleConfig.kt
│   │   └── SwordVersification.kt
│   └── bintex/               ← IN PROGRESS (from androidbible-kmp)
│       ├── BintexReader.kt
│       ├── SnappyCodec.kt
│       ├── yes2/Yes2Reader.kt + section/*.kt
│       └── yes1/Yes1Reader.kt
├── reader/                   ← Unified abstraction (TO BUILD)
│   ├── BibleReaderInterface.kt ← Unified interface both engines implement
│   ├── BibleReaderFactory.kt ← Router: bintex vs sword by module type
│   └── FormattedVerseText.kt ← Handles YES2 markup OR OSIS markup
```

### Unified Module Model (Backend DB)
```sql
modules (
    id, key, engine ENUM('sword','bintex'),
    driver VARCHAR,       -- 'ztext'|'rawtext'|'zcom'|'rawld4'|'zld' or 'yes2'|'yes1'
    short_name, long_name, locale, description,
    data_path,            -- local FS path to data files
    versification,        -- 'KJV'|'NRSV'|'Catholic'|'Synodal'|'German' (sword only)
    source_type,          -- 'OSIS'|'GBF'|'THML'|'TEI'|'Plain' (sword) or 'bintex' (yes2)
    cipher_key NULLABLE,  -- SWORD cipher modules
    repository_source,    -- 'crosswire'|'goldenbowl'
    installed_at
)
```

---

## ARI Encoding (YES2 + unified reference system)
```
ari = (bookId shl 16) or (chapter shl 8) or verse
bookId: 1-66 (Genesis=1, Revelation=66)
chapter: 1-150 (depending on book)
verse: 1-176 (depending on chapter)
```
SWORD modules use integer book/chapter/verse (same scheme, just different API) — map via `SwordVersification.kt`.

---

## Sync Protocol (goldenBowl style — from androidbible-api)

### Key Concepts
- **GID**: UUID v4, globally unique per marker/label
- **Revision**: monotonically increasing integer per user
- **SyncShadow**: server-side copy of last-known client state (conflict detection)
- **Echo prevention**: broadcast events carry `device_id`; mobile skips events matching own device

### Sync Request Shape
```json
{
  "revision": 40,
  "device_id": "uuid-v4",
  "sync_set_name": "all",
  "markers": [{ "gid": "...", "action": "upsert|delete", "ari": 123456, "kind": 1, "caption": "...", "verseCount": 1, "labels": ["gid1"] }],
  "labels": [{ "gid": "...", "action": "upsert|delete", "title": "...", "ordering": 1, "backgroundColor": "#FF0000" }],
  "progress_marks": [{ "gid": "...", "action": "upsert|delete", "preset_id": 5, "ari": 123456 }]
}
```

### Broadcast Events (Reverb, Pusher protocol)
- Channel: `private-user.{userId}`
- Events: `marker.created`, `marker.updated`, `marker.deleted`, `label.updated`, `progress.updated`
- Auth: `POST /api/broadcasting/auth` — Sanctum Bearer (NOT web session)

---

## Auth Patterns

### Apple Sign-In (manual JWKS)
```php
// Fetch https://appleid.apple.com/auth/keys
// Verify JWT RS256 using matching kid
// Validate: iss=https://appleid.apple.com, aud=bundle_id, exp not expired
// Extract sub (Apple user ID) + email
```

### Marker Kinds
- `1` = BOOKMARK
- `2` = NOTE
- `3` = HIGHLIGHT

---

## SWORD Module Facts
- Modules arrive as ZIP archives from CrossWire rawzip packages
- `mods.d/<name>.conf` defines: `ModDrv`, `DataPath`, `Versification`, `SourceType`, `CipherKey`
- `ModDrv` → Reader class: `zText`→ZTextReader, `RawText`→RawTextReader, `zCom`→ZComReader, `RawCom`→RawComReader, `zLD`→ZLDReader, `RawLD4`→RawLD4Reader, `RawGenBook`→RawGenBookReader
- `SourceType` → Filter class: `OSIS`→OsisFilter, `GBF`→GbfFilter, `ThML`→ThmlFilter, `TEI`→TeiFilter, `Plain`→PlainFilter
- ~8 versification systems: KJV, KJVA, NRSV, Catholic, Synodal, German, Luther, Vulgate
- Bundled modules (KJV, MHCC, Strong's, Robinson): Android assets `composeApp/src/androidMain/assets/sword/*.zip`

---

## YES2 Binary Facts
- Magic header: `0x98 0x58 0x0d 0x0a 0x00 0x5d 0xe0` + version byte (`0x01`=YES1, `0x02`=YES2)
- Sections: VersionInfo, BooksInfo, Text (Snappy-compressed), Footnotes, Xrefs, Pericopes
- Decompression: Snappy codec (port pure Java `SnappyImplJava`, NOT JNI native)
- All text: UTF-8 encoded — always `bytes.decodeToString()` / `String(bytes, Charsets.UTF_8)`
- Source Java: `androidbible` workspace → `AlkitabYes2/src/main/java/`

---

## Project File Map (Target)

```
hisword/
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Api/Auth/          AuthController, SocialAuthController
│   │   │   ├── Api/               SyncController, MarkerController, LabelController
│   │   │   │                      ModuleController, ReaderController (unified)
│   │   │   │                      BroadcastingAuthController (Sanctum Bearer)
│   │   │   └── Web/               All Inertia.js controllers (from pocketsword)
│   │   ├── Models/                User, Marker, Label, Module, Device, etc.
│   │   └── Services/
│   │       ├── Sword/             PHP SWORD engine (COMPLETE)
│   │       ├── Bintex/            PHP YES2 engine (TO BUILD)
│   │       └── SyncService.php    goldenBowl delta sync
│   └── resources/js/             React 19 + Inertia.js web app (from pocketsword)
│
├── mobile/
│   ├── shared/src/commonMain/kotlin/
│   │   ├── data/
│   │   │   ├── sword/            Kotlin SWORD engine (COMPLETE from pocketsword)
│   │   │   ├── bintex/           Kotlin YES2 engine (IN PROGRESS from androidbible-kmp)
│   │   │   ├── model/            Marker, Label, Book, VersionInfo, Ari, Module
│   │   │   ├── db/               SQLDelight queries
│   │   │   └── repository/       MarkerRepo, LabelRepo, ModuleRepo, etc.
│   │   ├── reader/               BibleReaderInterface, BibleReaderFactory
│   │   ├── domain/
│   │   │   ├── usecase/          GetChapterUseCase, SearchUseCase, etc.
│   │   │   └── sync/             SyncEngine, ConflictResolver
│   │   ├── network/
│   │   │   ├── api/              AuthApi.kt, SyncApi.kt, ModuleApi.kt
│   │   │   ├── auth/             TokenStorage.kt (expect/actual secure store)
│   │   │   └── websocket/        ReverbClient.kt
│   │   └── ui/
│   │       ├── reader/           BibleReaderScreen, VerseItem, ChapterPager
│   │       ├── versions/         VersionsScreen (both YES2 + SWORD downloads)
│   │       ├── search/           SearchScreen, SearchFilters
│   │       ├── markers/          MarkersScreen, NoteEditor, HighlightColorPicker
│   │       ├── labels/           LabelsScreen, LabelEditor
│   │       ├── navigation/       GotoScreen, BookGrid, DialerMode, DirectMode
│   │       ├── commentary/       CommentaryPanel (Strong's, dicts - SWORD only)
│   │       ├── plans/            ReadingPlansScreen, PlanProgressView
│   │       ├── songs/            SongsScreen, SongDetailScreen (from KpriModel)
│   │       └── auth/             LoginScreen, RegisterScreen
│   ├── androidApp/
│   ├── iosApp/
│   └── desktopApp/
│
├── docs/
│   ├── ARCHITECTURE.md
│   ├── MIGRATION.md
│   └── PROJECT_DOCUMENTATION.md
└── agents/
    ├── AUTONOMOUS_PROMPT_BACKEND.md
    ├── AUTONOMOUS_PROMPT_MOBILE.md
    └── AUTONOMOUS_PROMPT_WEB.md
```

---

## Recent Decisions

### 2026-03-12 — Project Creation
- **Decision:** Create new `hisword` repo merging androidbible + pocketsword (Option B: unified backend)
- **Rationale:** Same KMP stack in both; SWORD+YES2 give unmatched module breadth; one login/sync for all content
- **Base repos preserved:** androidbible-api, androidbible-kmp, pocketsword all remain intact on GitHub
- **Integration seam:** `BibleReaderInterface` in mobile, `BibleReaderFactory.php` in backend
- **Data migration:** Both sets of users/markers will be migrated to unified HisWord DB
