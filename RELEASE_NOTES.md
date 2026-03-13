# Android Bible — Release Notes

## v1.0.0 — Initial Release

### Overview
Complete rewrite of the Android Bible app as a Compose Multiplatform (KMP) application
supporting Android and iOS with shared business logic, offline-first architecture,
and real-time sync.

### Features

#### Bible Reading
- Multiple Bible version support (YES2 format + SWORD modules)
- Chapter-by-chapter navigation with swipe gestures
- Verse-by-verse display with pericope (section) headers
- Cross-references and footnotes
- Full-text search across all loaded versions
- Go-to-verse quick navigation
- Deep link support (`bible://Gen.1.1?version=KJV`)

#### Study Tools
- **Bookmarks** — Save and organize favorite verses
- **Highlights** — Color-coded verse highlighting
- **Notes** — Add personal notes to any verse
- **Labels** — Organize markers with custom labels and colors
- **Word Study** — Strong's Hebrew/Greek dictionary lookup with occurrence search
- **Study Pad** — Markdown-based research notebook with `[[Gen 1:1]]` verse links
- **Cross References** — TSK and API-sourced cross-reference viewer

#### Content
- **Reading Plans** — Multi-day Bible reading plans with progress tracking
- **Daily Devotionals** — Date-based devotional reader with verse references
- **Songs & Hymns** — Song book browser with search and SWORD GenBook support
- **Verse of the Day** — Daily verse on home screen with widget support

#### Sync & Account
- **User Authentication** — Email/password + social login (Google, Apple)
- **Real-time Sync** — WebSocket-based sync via Laravel Reverb
- **Offline Queue** — All changes queued offline, synced when connected
- **Conflict Resolution** — Last-write-wins with device-aware echo prevention
- **Push Notifications** — FCM (Android) + APNs (iOS) for sync triggers

#### Platform Features
- **Onboarding** — First-launch welcome flow with version selection
- **Splash Screen** — Animated app intro screen
- **Deep Links** — `bible://` URI scheme for verse navigation
- **Share** — Share verses as text via platform share sheet
- **Clipboard** — Copy verse text to clipboard
- **Crash Reporting** — Sentry integration (pluggable)

### Architecture
- **Kotlin Multiplatform** — Shared code for Android + iOS
- **Compose Multiplatform** — Shared UI layer
- **SQLDelight** — Type-safe SQLite with multiplatform support
- **Ktor** — HTTP client + WebSocket
- **Koin** — Dependency injection
- **Voyager** — Type-safe navigation
- **kotlinx-serialization** — JSON serialization
- **kotlinx-datetime** — Multiplatform date/time

### Backend
- Laravel 12 with PHP 8.4
- PostgreSQL 16 + Redis 7
- Sanctum token authentication
- Laravel Reverb for WebSocket
- Meilisearch for full-text search
- Filament 3 admin panel

### Binary Formats
- **YES2 Reader** — Pure Kotlin parser for YES2 compressed Bible format
- **Bintex Reader** — Binary text format decoder
- **Snappy Codec** — Pure Kotlin Snappy decompression
- **SWORD Engine** — zText, RawCom, RawLD4, ZLD, GenBook module readers
- **OSIS/GBF/THML/TEI Filters** — Markup-to-plain-text converters

### Test Coverage
- 350+ unit tests across all layers
- Binary reader tests (BintexReader, SnappyCodec, Yes2Reader)
- Repository model tests (all 8 repositories)
- Sync service tests (events, conflicts, queues)
- SWORD engine tests (readers, filters, versification)
- UI flow tests (navigation, search, bookmarks)
- Integration tests (YES2 pipeline, ARI encoding)
- Platform polish tests (deep links, onboarding)
- Performance and accessibility validation

---

## Build Instructions

### Android
```bash
./gradlew :composeApp:assembleRelease
```

### iOS
```bash
cd iosApp && xcodebuild -scheme iosApp -configuration Release
```

### Run Tests
```bash
./gradlew :composeApp:allTests
```

## Requirements
- Android: API 24+ (Android 7.0)
- iOS: 16.0+
- Kotlin: 2.0+
- Compose Multiplatform: 1.6+
