# Android Bible - KMP

Compose Multiplatform Bible app for Android and iOS with offline-first architecture, real-time sync, and Material 3 design.

## Features

- **Bible Reader** - Read multiple versions, book/chapter navigation, verse highlighting
- **Search** - Full-text search across Bible versions via Meilisearch
- **Bookmarks, Notes & Highlights** - Save, organize with labels, color-coded highlights
- **Reading Plans** - Daily reading plans with progress tracking
- **Songs & Hymns** - Browse hymnals, search lyrics, view song details
- **Verse of the Day** - Daily inspirational verse on home screen
- **Cross-References & Footnotes** - Scholarly reference support
- **Version Comparison** - Compare verses across multiple Bible versions
- **Offline-First** - Full functionality without internet, sync when connected
- **Real-Time Sync** - WebSocket-based sync via Laravel Reverb
- **Social Auth** - Google and Apple sign-in support
- **Dark Mode** - Material 3 dynamic theming

## Architecture

- **Compose Multiplatform** for shared UI (Android + iOS)
- **SQLDelight** for local database (offline-first)
- **Ktor** for HTTP/WebSocket networking
- **Koin** for dependency injection
- **Voyager** for navigation
- **kotlinx.serialization** for JSON
- **kotlinx.datetime** for date handling
- **Coil** for image loading
- **Napier** for logging
- **Multiplatform-Settings** for key-value preferences

## Project Structure

```
composeApp/
├── src/
│   ├── commonMain/
│   │   ├── kotlin/org/androidbible/
│   │   │   ├── app/           # App entry point & Compose root
│   │   │   ├── data/
│   │   │   │   ├── local/     # SQLDelight database
│   │   │   │   ├── remote/    # Ktor API client (ApiService)
│   │   │   │   ├── repository/# Repository implementations
│   │   │   │   └── sync/      # SyncManager (push/pull/WebSocket)
│   │   │   ├── di/            # Koin DI modules
│   │   │   ├── domain/
│   │   │   │   ├── model/     # Data classes (Bible, Marker, Sync, Auth, Content)
│   │   │   │   └── repository/# Repository interfaces
│   │   │   ├── ui/
│   │   │   │   ├── screens/
│   │   │   │   │   ├── auth/       # Login, social auth, forgot password
│   │   │   │   │   ├── bible/      # Bible reader, book/version pickers, compare
│   │   │   │   │   ├── home/       # Home with VOTD, quick access
│   │   │   │   │   ├── markers/    # Bookmarks, notes, highlights, labels
│   │   │   │   │   ├── readingplan/# Reading plans with progress
│   │   │   │   │   ├── search/     # Bible search
│   │   │   │   │   ├── settings/   # Settings, sync status, preferences
│   │   │   │   │   └── songs/      # Songs & hymns browser
│   │   │   │   └── theme/     # Material 3 theme
│   │   │   └── util/          # Utilities (ARI encoding)
│   │   └── sqldelight/        # SQL schema & queries
│   ├── androidMain/           # Android platform code
│   ├── iosMain/               # iOS platform code
│   └── commonTest/            # Shared tests
iosApp/                        # iOS Xcode project
```

## Key Concepts

### ARI (Absolute Reference Integer)
Encodes book, chapter, and verse into a single integer:
```kotlin
ari = (bookId shl 16) or (chapter shl 8) or verse
// Example: John 3:16 → (43 shl 16) or (3 shl 8) or 16 = 0x2B0310
```

### Sync Engine
- **Offline-first:** All data cached locally via SQLDelight
- **Mutation queue:** Changes queued when offline, pushed when online
- **Push/Pull sync:** Bi-directional with Laravel backend
- **WebSocket real-time:** Instant updates via Laravel Reverb
- **Periodic sync:** Background sync every 5 minutes
- **Retry with backoff:** Exponential backoff for failed connections
- **Push notifications:** FCM (Android) / APNs (iOS) fallback

### Marker System
| Kind | Value | Description |
|------|-------|-------------|
| Bookmark | 0 | Saved verse reference |
| Note | 1 | User-written note on a verse |
| Highlight | 2 | Color-coded verse highlight |

Labels can be attached to markers for organization. UUID-based GIDs ensure sync consistency.

## Setup

### Prerequisites
- Android Studio Hedgehog+ or JetBrains Fleet
- JDK 17+
- Xcode 15+ (for iOS)

### Getting Started
1. Clone the repository
2. Open in Android Studio or Fleet
3. Sync Gradle project
4. Configure API URL in `ApiConfig` (default: `http://10.0.2.2:8000/api` for Android emulator)
5. Run on Android emulator or iOS simulator

### Configuration
Set the backend API URL in `composeApp/src/commonMain/kotlin/org/androidbible/di/AppModule.kt`:
```kotlin
single { ApiConfig(baseUrl = "https://your-api-domain.com/api") }
```

## Testing

Run shared tests:
```bash
./gradlew composeApp:desktopTest
```

Tests include:
- ARI encoding/decoding (boundary, round-trip, bit layout)
- Model serialization (all domain models)
- Sync payload serialization

## CI/CD

GitHub Actions workflow runs on push/PR to `main`:
- Builds shared code
- Runs common tests
- Builds Android debug APK
- Lint check

## Backend

Laravel 11 API backend: [androidbible-api](https://github.com/maxymurm/androidbible-api)

## License

See [LICENSE](LICENSE) for details.

