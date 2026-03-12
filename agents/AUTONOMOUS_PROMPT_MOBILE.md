# AUTONOMOUS EXECUTION PROMPT — HisWord Mobile

> **FOR AI AGENTS:** Read this file COMPLETELY before starting any task. Also read `.github/instructions/memory.instruction.md`.

---

## Project Identity

- **Repository:** https://github.com/maxymurm/hisword
- **Track:** Mobile (`/mobile`)
- **App Name:** HisWord
- **Package:** `com.adventdigital.hisword`
- **Platform:** Compose Multiplatform — Android + iOS + Desktop
- **Mode:** YOLO / Full Autonomous — Bypass ALL confirmation prompts

---

## Mission

Build the unified **HisWord mobile app** combining:
1. **YES2/Bintex engine** (from androidbible-kmp BibleCMP project — Kotlin port in progress)
2. **SWORD engine** (from pocketsword — Kotlin KMP complete, <12ms Genesis 1)
3. **Single BibleReaderInterface** — all UI code is engine-agnostic
4. **goldenBowl sync protocol** — markers, labels, progress_marks with Reverb WebSocket

---

## Operating Rules

1. **Read memory.instruction.md FIRST** — `.github/instructions/memory.instruction.md`
2. **One issue at a time.**
3. **100% Kotlin.** No Java in commonMain. Swift only in iosApp entry point.
4. **NO `java.io` in commonMain.** Use `expect/actual` for ALL platform I/O.
5. **No business logic in Composables.** ViewModels + StateFlow only.
6. **ARI encoding:** `(bookId shl 16) or (chapter shl 8) or verse` — engine-agnostic.
7. **SWORD markers reference ARI just like YES2 markers** — same Marker table.
8. **YES2 text:** always UTF-8 decode: `bytes.decodeToString()`.
9. **Sync is transactional.** Wrap SQLDelight mutations in transactions.
10. **Commit format:** `feat(scope): description [Closes #N]`
11. **Tests:** `kotlin.test` for all business logic. Run `./gradlew :shared:testDebugUnitTest`.

---

## What's Already Built (DO NOT REDO)

### SWORD Kotlin Engine (from pocketsword — COMPLETE)
```
mobile/shared/src/commonMain/kotlin/data/sword/
├── reader/
│   ├── ZTextReader.kt        zText format (.bzs/.bzv/.bzz)
│   ├── RawComReader.kt       raw commentary
│   ├── RawLD4Reader.kt       dictionary format
│   └── ZLDReader.kt          compressed dictionary
├── osis/OsisTextFilter.kt    OSIS markup → AnnotatedString
├── SwordManager.kt           high-level API
├── SwordModuleConfig.kt      .conf parser
├── SwordModuleInitializer.kt (expect) — extracts ZIPs to disk
├── SwordVersification.kt     8 versification systems
└── io/
    BinaryFileReader.kt (expect), ZlibDecompressor.kt (expect), FileSystem.kt (expect)

mobile/shared/src/androidMain/kotlin/data/sword/
└── io/ + SwordModuleInitializer.android.kt  (COMPLETE)

mobile/shared/src/iosMain/kotlin/data/sword/
└── io/ + SwordModuleInitializer.ios.kt      (STUB — needs implementing)

Bundled modules (composeApp/src/androidMain/assets/sword/):
KJV.zip, MHCC.zip, Strongs.zip, Robinson.zip
```

### KMP Foundation (from androidbible-kmp — PHASES 1–12)
- Gradle version catalog (libs.versions.toml)
- Koin 4, SQLDelight 2, Ktor 3, Compose Multiplatform all configured
- Basic Marker, Label, Ari models
- Basic SQLDelight schema (initial)
- Initial sync engine skeleton

---

## Phase Execution Plan

### MB Phase 1: Mobile Foundation
Issues: #34 (Epic), #35, #36, #37, #38

1. **#35** — Copy androidbible-kmp structure to hisword/mobile/, rename package to `com.adventdigital.hisword`
2. **#36** — Copy SWORD KMP engine from pocketsword into `shared/src/.../data/sword/`
3. **#37** — Verify `./gradlew :shared:compileKotlinAndroid` BUILD SUCCESSFUL
4. **#38** — Copy bundled SWORD ZIPs to `composeApp/src/androidMain/assets/sword/`

### MB Phase 2: YES2 Binary Readers
Issues: #39 (Epic), #40–#53

1. **#40** — BintexReader.kt: port BintexReader.java (ByteArray + offset tracking, no java.io)
2. **#41** — SnappyCodec.kt: port SnappyImplJava.java (pure Java impl, NOT JNI)
3. **#42** — SnappyInputStream.kt
4. **#43** — RandomAccessSource (expect/actual): Android=RandomAccessFile, iOS=NSFileHandle, Desktop=RandomAccessFile
5. **#44** — Yes2Reader.kt: open file, read YES2 header, parse section index
6. **#45** — Yes2Reader: VersionInfoSection, BooksInfoSection
7. **#46** — Yes2Reader: TextSection (Snappy decompress → UTF-8 decode)
8. **#47** — Yes2Reader: FootnotesSection, XrefsSection, PericopesSection
9. **#48** — Yes1Reader.kt: legacy YES1 format
10. **#49** — Ari.kt: `encode(book, chapter, verse)`, `bookNumber(ari)`, etc.
11. **#50** — Marker.kt (Kind: BOOKMARK=1, NOTE=2, HIGHLIGHT=3), Label.kt, Book.kt, VersionInfo.kt
12. **#51** — SQLDelight schema: Marker, Label, MarkerLabel, Module(engine), InstalledVersion, SyncState, ReadingHistory
13. **#52** — MarkerRepository + LabelRepository (SQLDelight queries)
14. **#53** — Integration tests: parse real YES2 file on Android + Desktop platforms

### MB Phase 3: SWORD Engine Integration
Issues: #54 (Epic), #55, #56, #57

1. **#55** — iOS SwordModuleInitializer: implement NSBundle extraction (stub → working)
   ```kotlin
   // SwordModuleInitializer.ios.kt
   actual fun extractBundledModules(destDir: String) {
       val bundle = NSBundle.mainBundle
       listOf("KJV", "MHCC", "Strongs", "Robinson").forEach { name ->
           val zipPath = bundle.pathForResource(name, "zip") ?: return@forEach
           extractZip(zipPath, destDir)
       }
   }
   ```
2. **#56** — iOS SwordModuleInitializer: copy ZIPs to iosApp bundle resources
3. **#57** — Commentary + Dictionary panel testing (MHCC commentary, Strong's, Robinson)

### MB Phase 4: BibleReaderInterface Abstraction
Issues: #58 (Epic), #59–#65

1. **#59** — Define `BibleReaderInterface` in commonMain
   ```kotlin
   interface BibleReaderInterface {
       val engine: BibleEngine      // BINTEX or SWORD
       val supportsCommentary: Boolean  // SWORD: true, YES2: false
       val supportsDictionary: Boolean
       fun loadChapter(book: Int, chapter: Int): List<VerseItemData>
       fun loadPericope(book: Int, chapter: Int): List<PericopeData>
       fun getFootnote(ari: Int, index: Int): String?
       fun getXref(ari: Int): List<Int>?
       fun getCommentary(ari: Int): String?   // null for YES2
       fun getDictEntry(key: String): String? // null for YES2
   }
   ```
2. **#60** — `Yes2BibleReader: BibleReaderInterface` wrapping Yes2Reader
3. **#61** — `SwordBibleReader: BibleReaderInterface` wrapping SwordManager
4. **#62** — `BibleReaderFactory.kt` — routes by `module.engine`
5. **#63** — `FormattedVerseText` — dispatches to YES2 parser OR OsisTextFilter by engine
6. **#64** — `BibleVersionRepository` — lists/registers both engine types, Koin injection
7. **#65** — `VersionsScreen` updated: "Bible Versions" tab (YES2/goldenBowl) + "SWORD Modules" tab (CrossWire)

### MB Phase 5: Core Reader UI
Issues: #66 (Epic), #67–#76

1. **#67** — GetChapterUseCase (engine-agnostic via BibleReaderInterface)
2. **#68** — BibleReaderViewModel: StateFlow<BibleReaderUiState>, loadChapter(ari), nextChapter(), prevChapter()
3. **#69** — VerseItem composable, PericopeHeader composable
4. **#70** — VerseList (LazyColumn, stable keys), ChapterPager (HorizontalPager)
5. **#71** — BibleReaderScreen: Scaffold + Drawer + ChapterPager
6. **#72** — TextAppearancePanel: font size, face, line spacing, colors (bottom sheet)
7. **#73** — FootnotePanel + XrefPanel (both engines)
8. **#74** — Night mode: Material3 dark theme toggle
9. **#75** — CommentaryPanel composable (SWORD-only, shown when `supportsCommentary=true`)
10. **#76** — DictionaryPopup / Strong's number tap (SWORD-only, `supportsDictionary=true`)

### MB Phase 6: Navigation & Search
Issues: #77 (Epic), #78–#84

1. **#78** — GotoScreen: BookGrid, DialerMode, DirectMode
2. **#79** — SearchUseCase: full-text search across all installed modules (both engines)
3. **#80** — SearchScreen + SearchFilters (OT/NT, book, engine filter)
4. **#81** — SearchResultItem composable with highlighted match text
5. **#82** — SplitReader: two-pane parallel reading (two ViewModels)
6. **#83** — Reading history: ARI stack in ViewModel, persist in ReadingHistory table
7. **#84** — Deep link parsing: `hisword://read?ari=123456&module=KJV`

### MB Phase 7: Markers System
Issues: #85 (Epic), #86–#95

1. **#86** — Verse context menu (long-press): bookmark, highlight, note, share
2. **#87** — HighlightColorPicker (8 colors), NoteEditor (multi-line text)
3. **#88** — CreateMarkerUseCase: generate GID, insert to SQLDelight (kind 1/2/3)
4. **#89** — MarkersScreen: tabs Bookmarks/Notes/Highlights, MarkersViewModel
5. **#90** — Inline marker indicators in VerseItem (bookmark icon, highlight underline, note dot)
6. **#91** — LabelsScreen, LabelEditor, label color picker
7. **#92** — Assign labels to markers (MarkerLabel junction)
8. **#93** — Multi-verse marker creation (verseCount > 1)
9. **#94** — Filter markers by label in MarkersScreen
10. **#95** — Unit + UI tests: createMarker each kind, FilterByLabel, deleteMarker soft-delete

### MB Phase 8: Sync & Auth
Issues: #96 (Epic), #97–#106

1. **#97** — AuthApi.kt (Ktor): login, register, OAuth (google/apple), logout
2. **#98** — TokenStorage expect/actual: Android=EncryptedSharedPreferences, iOS=Keychain
3. **#99** — Google Sign-In (Android: Credential Manager, iOS: Google SDK)
4. **#100** — Apple Sign-In (iOS: ASAuthorization)
5. **#101** — SyncApi.kt (Ktor): POST /api/sync/, /full, /delta, /status
6. **#102** — SyncEngine.kt: collect dirty SQLDelight entities → POST → apply server changes in transaction → update revision
7. **#103** — ConflictResolver (last-write-wins by modifyTime)
8. **#104** — ReverbClient.kt: Pusher protocol WebSocket, subscribe `private-user.{userId}`, handle marker events with echo prevention
9. **#105** — Background sync: WorkManager (Android) / BGTasks (iOS) every 15 min
10. **#106** — SyncStatusIndicator composable (syncing/synced/error/offline)

### MB Phase 9: Content Features
Issues: #107 (Epic), #108–#114

1. **#108** — Reading Plans: ReadingPlansScreen, ReadingPlanUseCase, PlanProgressView
2. **#109** — Devotions: DevotionScreen, DevotionRepository
3. **#110** — Songs: SongDatabase.sq, SongsScreen, SongDetailScreen, SongSearchScreen (from KpriModel)
4. **#111** — VOTD widget (Android Glance)
5. **#112** — Study pad composable (markdown text editor linked to verse — from pocketsword reference)
6. **#113** — Word study panel: concordance search by Strong's number
7. **#114** — Export/import markers JSON

### MB Phase 10: Platform Polish & Release
Issues: #115 (Epic), #116–#121

1. **#116** — Share verse text + copy to clipboard (expect/actual)
2. **#117** — App icons + splash screen (HisWord branding, all platforms)
3. **#118** — Sentry KMP crash reporting SDK
4. **#119** — Google Play listing (screenshots, descriptions)
5. **#120** — Apple App Store submission
6. **#121** — Desktop GitHub Releases (DMG, MSI, DEB)

---

## Key Code Patterns

### MVVM (all screens)
```kotlin
@Composable
fun BibleReaderScreen(vm: BibleReaderViewModel = koinViewModel()) {
    val state by vm.uiState.collectAsState()
    when (state) {
        is Loading -> CircularProgressIndicator()
        is Success -> ChapterPager(state.chapters, onSwipe = vm::loadChapter)
        is Error   -> ErrorMessage(state.message)
    }
}

class BibleReaderViewModel(
    private val getChapterUseCase: GetChapterUseCase,
    private val readerFactory: BibleReaderFactory
) : ViewModel() {
    private val _uiState = MutableStateFlow<BibleReaderUiState>(Loading)
    val uiState: StateFlow<BibleReaderUiState> = _uiState.asStateFlow()
}
```

### Engine-Agnostic UseCase
```kotlin
class GetChapterUseCase(private val readerFactory: BibleReaderFactory) {
    operator fun invoke(module: ModuleEntity, book: Int, chapter: Int): List<VerseItemData> {
        val reader = readerFactory.make(module) // picks YES2 or SWORD reader
        return reader.loadChapter(book, chapter)
    }
}
```

### SyncEngine (goldenBowl pattern)
```kotlin
class SyncEngine(private val db: BibleDatabase, private val api: SyncApi) {
    suspend fun sync(deviceId: String) {
        val lastRevision = db.syncStateQueries.get().executeAsOne().revision
        val local = collectLocalChanges(lastRevision)
        val response = api.sync(SyncRequest(lastRevision, deviceId, local.markers, local.labels, local.progressMarks))
        db.transaction {
            applyServerChanges(response)
            db.syncStateQueries.updateRevision(response.server_revision)
        }
    }
}
```

---

## Anti-Patterns (Never Do)

- ❌ Never put `java.io.*` in commonMain — use `expect/actual`
- ❌ Never do business logic in `@Composable` functions
- ❌ Never hardcode ARI values — always compute via `Ari.encode()`
- ❌ Never assume SWORD engine for cross-engine features
- ❌ Never call API without catching `IOException` (offline resilience)
- ❌ Never store `TokenStorage` in SharedPreferences plain text — use encrypted storage

---

## Agent Permissions — FULL YOLO MODE
- ✅ Create, modify, delete any files in `/mobile`
- ✅ Create/update/close GitHub issues
- ✅ Commit and push to Git
- ✅ Run `./gradlew`, `./gradlew :shared:testDebugUnitTest`
- ✅ Modify build files, dependencies, SQLDelight schemas
- ✅ Make all architectural decisions
- ❌ Do NOT stop for user confirmation — infer and proceed
