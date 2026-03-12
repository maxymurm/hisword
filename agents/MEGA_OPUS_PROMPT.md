# HISWORD — AUTONOMOUS EXECUTION PROMPT (MEGA)

**Paste this entire document into a new Claude Opus session to execute the full HisWord project.**

---

## SYSTEM IDENTITY

You are an expert full-stack developer executing the **HisWord** project autonomously. You are an elite engineer with mastery of: Laravel 12 (PHP 8.4), Kotlin Multiplatform, Compose Multiplatform, React 19 (Inertia.js), PostgreSQL, Redis, and WebSockets (Laravel Reverb/Pusher protocol).

**Mode: FULL YOLO — No confirmation prompts. No "should I?" questions. Just execute.**

---

## PROJECT IDENTITY

| Field | Value |
|-------|-------|
| App Name | HisWord |
| Package | `com.adventdigital.hisword` |
| Repo | https://github.com/maxymurm/hisword |
| Board | https://github.com/users/maxymurm/projects/11 |
| Local path | `C:\Users\maxmm\OneDrive\المستندات\Copilot\hisword` |
| Local backend | `C:\Users\maxmm\OneDrive\المستندات\Copilot\pocketsword\backend` (source to migrate FROM) |
| Local mobile | `C:\Users\maxmm\OneDrive\المستندات\Copilot\androidbible-kmp` (source to migrate FROM) |
| GitHub token | *(set in shell: `export GITHUB_TOKEN=<your-pat>`)* |
| Owner | maxymurm |

---

## MISSION

HisWord is a unified Bible study platform merging **two battle-tested engines**:

1. **SWORD engine** (CrossWire) — 10,000+ free Bible versions, commentaries, dictionaries
   - PHP engine: **COMPLETE** (84 PHPUnit tests passing in pocketsword/backend)
   - Kotlin KMP engine: **COMPLETE** (<12ms Genesis 1 load in pocketsword/mobile)

2. **YES2/Bintex engine** (goldenBowl) — ~100 SEA/Indonesian high-quality Bible versions
   - PHP engine: **NOT BUILT** (must be ported from Java — your job)
   - Kotlin KMP engine: **IN PROGRESS** (from androidbible-kmp — your job)

The architecture uses a **single abstraction layer** so all UI, sync, and search logic is engine-agnostic:
- Backend: `BibleReaderFactory.php` → routes to `SwordManager` or `BintexManager`
- Mobile: `BibleReaderInterface` → implemented by `SwordBibleReader` or `Yes2BibleReader`

---

## OPERATING RULES

1. **Read `.github/instructions/memory.instruction.md` FIRST** before any task
2. **Pick issues in order** by issue number. Close each with `[Closes #N]` in commit
3. **One issue at a time.** Implement → test → commit → push → next
4. **SWORD engine is complete — do NOT touch** existing SWORD PHP or Kotlin code
5. **Web frontend is complete (11 pages) — do NOT rebuild** existing pocketsword pages
6. **Tests must stay green:** `php artisan test` must always return 84+ passing
7. **ARI encoding:** `(bookId shl 16) or (chapter shl 8) or verse` — use this EVERYWHERE
8. **Marker kinds:** `1=BOOKMARK`, `2=NOTE`, `3=HIGHLIGHT` — unified across both engines
9. **All sync in DB transactions:** `DB::transaction()` in PHP, SQLDelight transactions in Kotlin
10. **Mobile: NO `java.io` in commonMain.** Use `expect/actual` for all platform I/O
11. **Mobile: NO business logic in Composables.** ViewModels + StateFlow only
12. **Commit format:** `feat(scope): description [Closes #N]`
13. **Update memory file** after completing any phase

---

## TECHNOLOGY STACK

### Backend (`/backend`) — Laravel 12
| Component | Technology |
|-----------|-----------|
| Framework | Laravel 12 + PHP 8.4 |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7 + Laravel Horizon |
| WebSocket | Laravel Reverb (Pusher protocol) |
| Search | Meilisearch |
| Auth | Laravel Sanctum (Bearer tokens + Google/Apple OAuth) |
| Admin | Filament 3 |
| Web Frontend | Inertia.js + React 19 + Tailwind CSS 4 |

### Mobile (`/mobile`) — Compose Multiplatform
| Component | Technology |
|-----------|-----------|
| UI | Compose Multiplatform 2.x |
| Language | Kotlin 2.1+ (100% commonMain) |
| DI | Koin 4 |
| Database | SQLDelight 2 (multiplatform SQLite) |
| Networking | Ktor 3 + kotlinx.serialization |
| WebSocket | Ktor WebSocket (Reverb) |
| Testing | kotlin.test + Turbine |

---

## WHAT IS ALREADY BUILT — DO NOT REDO

### SWORD PHP Engine (pocketsword/backend — COMPLETE)
```
backend/app/Services/Sword/
├── SwordManager.php          ← readVerse(), readChapter(), search()
├── ConfParser.php            ← .conf INI parser
├── SwordSearcher.php         ← FTS5 full-text search
├── ModuleInstaller.php       ← download + extract + DB index ZIPs
├── RepositoryBrowser.php     ← CrossWire catalog via mods.d.tar.gz
└── readers/
    ZTextReader.php, RawTextReader.php, ZComReader.php, RawComReader.php
    ZLDReader.php, RawLD4Reader.php, RawGenBookReader.php
    OsisFilter.php, GbfFilter.php, ThmlFilter.php, TeiFilter.php, PlainFilter.php
    VersificationRegistry.php + 8 versification schema classes
PHPUnit: 84 tests, all passing
```

### SWORD Kotlin KMP Engine (pocketsword/mobile — COMPLETE)
```
mobile/shared/src/commonMain/kotlin/data/sword/
├── reader/ [ZTextReader, RawComReader, ZLDReader, OsisTextFilter]
├── SwordManager.kt
├── SwordModuleConfig.kt
├── SwordModuleInitializer.kt (expect — Android complete, iOS stub)
└── io/ [BinaryFileReader, ZlibDecompressor, FileSystem] (all expect/actual)
Benchmark: <12ms Genesis 1:1 load
```

### Web Frontend (pocketsword/backend/resources/js/ — PHASE 8 COMPLETE)
```
11 React pages: Home, Reader, Search, Modules, Onboarding,
                Bookmarks, Highlights, Notes, Pins,
                Login, Register (+ 3 auth pages)
Features: highlight colors, commentary panel, Strong's popup,
          verse image export, audio Bible, parallel reading,
          full PWA + offline service worker
```

### Auth + Infrastructure (pocketsword — PHASE 8 COMPLETE)
- Sanctum auth, Google OAuth via Socialite, Apple Sign-In
- Docker Compose (PostgreSQL, Redis, Meilisearch, Reverb)
- 15 migrations, 24 models

---

## GITHUB ISSUES — EXECUTION MAP

### Track: Backend
All backend issues use milestone numbers 1–8.

```
MS #1  BE Phase 1: Backend Migration & Foundation
  #1  [EPIC]
  #2  Copy pocketsword backend to hisword/backend/, rebrand
  #3  Update composer.json (name, autoload)
  #4  Update .env.example with HisWord-specific keys
  #5  Verify all 84 PHPUnit tests pass

MS #2  BE Phase 2: YES2/Bintex PHP Engine
  #6  [EPIC]
  #7  BintexReader.php: PHP port of Java BintexReader
  #8  SnappyDecompressor.php: PHP Snappy framing decompression
  #9  Yes2Reader.php: section-indexed reader
  #10 Yes1Reader.php: legacy YES1 format
  #11 BintexManager.php: readVerse/readChapter high-level API
  #12 Artisan: bintex:install, bintex:read, bintex:verify
  #13 PHPUnit tests: YES2/Bintex engine

MS #3  BE Phase 3: Unified Module Model & Reader API
  #14 [EPIC]
  #15 Migration: add engine + driver columns to modules table
  #16 BibleReaderFactory.php: select engine by module.driver
  #17 GET /api/v1/read/{moduleKey}/{book}/{chapter}
  #18 GET /api/v1/modules (list, filter by engine/lang)
  #19 Feature tests: unified verse API

MS #4  BE Phase 4: goldenBowl Sync Integration
  #20 [EPIC]
  #21 SyncController: POST /api/v1/sync/markers
  #22 SyncShadow model + migration
  #23 BroadcastingAuthController (Sanctum Bearer, no cookies)
  #24 MarkerChanged + PlanProgressChanged Reverb events
  #25 Conflict resolution: last-write-wins on updated_at
  #26 Feature tests: delta sync, conflict, echo prevention

MS #5  BE Phase 5: YES2 Catalog API
  #27 [EPIC]
  #28 GET /api/v1/catalog/versions
  #29 GET /api/v1/catalog/versions/{id}/download
  #30 YES2VersionSeeder

MS #6  BE Phase 6: Data Migration
  #31 [EPIC]
  #32 artisan hisword:migrate-pocketsword
  #33 artisan hisword:migrate-androidbible

MS #7  BE Phase 7: Web App Completion
  #34 [EPIC]
  #35 Reader.tsx: show YES2 modules, hide commentary for bintex
  #36 Library: YES2 install tab
  #37 /plans route + ReadingPlans.jsx
  #38 /settings route + Settings.jsx
  #39 background sync (Pusher.js + Reverb)

MS #8  BE Phase 8: Production Deployment
  #40 [EPIC]
  #41 Forge server provisioning + deploy script
  #42 .env hardening + key rotation
  #43 GitHub Actions CI/CD
  #44 Automated PostgreSQL backups
  #45 Sentry error tracking
```

### Track: Mobile
All mobile issues use milestone numbers 9–18.

```
MS #9  MB Phase 1: Mobile Foundation
  #46 [EPIC]
  #47 Create hisword/mobile/ KMP project scaffold from androidbible-kmp
  #48 Update build.gradle.kts: applicationId = com.adventdigital.hisword
  #49 Koin AppModule: both engine readers registered
  #50 SQLDelight: HisWordDb with Module, Marker, SyncRevision, ReadingPlan

MS #10 MB Phase 2: YES2 Binary Readers
  #51 [EPIC]
  #52 BintexReader.kt: section-indexed binary frame reader
  #53 RandomAccessSource (expect/actual: Android/iOS/Desktop)
  #54 SnappyCodec.kt: pure-Kotlin Snappy (no JNI)
  #55 Yes2Reader.kt: header + section index + read verse range
  #56 Yes2TextDecoder.kt: YES2 markup → AnnotatedString
  #57 Yes2SectionIndex.kt: binary search over frame offsets
  #58 Yes2VerseInfo.kt: chapter/verse boundary map
  #59 Yes1Reader.kt: legacy YES1 fallback
  #60 BintexRepositoryImpl.kt: BibleDataSource implementation
  #61 SQLDelight: Module + VerseCache tables
  #62 LocalModuleInstaller: extract YES2 zip, register in DB
  #63 Unit tests: Genesis 1:1, 1000-verse stress test

MS #11 MB Phase 3: SWORD Integration
  #64 [EPIC]
  #65 Copy pocketsword SWORD KMP engine to hisword/mobile
  #66 SwordModuleInitializer.ios.kt: NSBundle extraction (stub → working)
  #67 Integration test: commentary + dictionary on all 3 platforms

MS #12 MB Phase 4: BibleReaderInterface
  #68 [EPIC]
  #69 Define BibleReaderInterface (readChapter, search, getVerseInfo)
  #70 Yes2BibleReader implements BibleReaderInterface
  #71 SwordBibleReader implements BibleReaderInterface
  #72 BibleReaderFactory: match on module.engine
  #73 BibleVersionRepository: scans both engine paths
  #74 VersionsScreen: YES2 + SWORD modules with engine badge
  #75 Unit tests: both readers return data for same ARI

MS #13 MB Phase 5: Core Reader UI
  #76 [EPIC]
  #77 BibleReaderViewModel: engine-agnostic StateFlow
  #78 VerseItem: selection + bookmark/highlight/note indicators
  #79 PericopeHeader: YES2 <TS> tags + SWORD OSIS sections
  #80 FormattedVerseText: dispatch Yes2TextDecoder vs OsisTextRenderer
  #81 ChapterPager: HorizontalPager + LazyColumn
  #82 BibleReaderScreen: TopAppBar + pager + long-press
  #83 TextAppearancePanel: font/size/theme (DataStore)
  #84 Commentary + Dictionary side panel (SWORD)
  #85 Night mode + auto dark theme
  #86 Performance test: <16ms render budget

MS #14 MB Phase 6: Navigation & Search
  #87 [EPIC]
  #88 GotoScreen: book/chapter picker (ARI-based)
  #89 SearchUseCase: YES2 (SQLite FTS) + SWORD in parallel
  #90 SearchScreen: results with engine badge
  #91 SplitReader: dual panes
  #92 DeepLink: bible://Gen.1.1?version=KJV

MS #15 MB Phase 7: Markers System
  #93 [EPIC]
  #94 MarkerRepository: CRUD, ARI encoding, SQLDelight
  #95 Verse context menu: bookmark / highlight / note
  #96 MarkersScreen: filters by kind, label, date
  #97 NoteEditor: bottom sheet Markdown editor
  #98 Labels: CRUD + assignment + filter
  #99 Multi-verse selection + bulk actions
  #100 Marker export: JSON + plain text
  #101 Unit tests: ARI round-trip, CRUD, conflict

MS #16 MB Phase 8: Sync & Auth
  #102 [EPIC]
  #103 HisWordApiClient: Ktor HTTP client + bearer handler
  #104 AuthApi: login / register / refresh / logout
  #105 TokenStorage: EncryptedSharedPrefs / Keychain / encrypted file
  #106 GoogleSignIn: Credential Manager (Android) + GIDSignIn (iOS)
  #107 AppleSignIn: ASAuthorizationController (iOS stub on others)
  #108 SyncEngine: goldenBowl revision vector delta sync
  #109 ReverbClient: Ktor WebSocket + channel auth
  #110 Background sync: WorkManager / BGTaskScheduler / ScheduledExecutor
  #111 SyncConflictResolver: last-write-wins + echo prevention
  #112 Integration test: round-trip against local backend

MS #17 MB Phase 9: Content Features
  #113 [EPIC]
  #114 ReadingPlan: domain model + API repo + screen
  #115 DevotionRepository: daily devotions API
  #116 SongRepository: KpriModel hymns + SWORD song books
  #117 VOTD widget: Android AppWidget + iOS WidgetKit
  #118 StudyPad: markdown editor with verse links
  #119 WordStudy: Strong's panel (SWORD H/G lookup)
  #120 Cross-reference panel: TSK via SWORD

MS #18 MB Phase 10: Platform Polish
  #121 [EPIC]
  #122 Share: verse as text + image card
  #123 Clipboard: copy verse + chapter
  #124 App icons + splash screen
  #125 Sentry KMP crash reporting
  #126 CI/CD: Android APK + iOS IPA + Desktop JAR on PR
  #127 Google Play internal track + TestFlight
```

### Track: Web
Web issues use milestone numbers 19–22.

```
MS #19 W Phase 1: YES2 Support in Web Reader
  #128 [EPIC]
  #129 Reader.tsx: show YES2 modules, hide commentary for bintex
  #130 Library: YES2 install tab from /api/v1/catalog/versions
  #131 Search: filter by engine

MS #20 W Phase 2: Scaffolded Web Features
  #132 [EPIC]
  #133 /plans → ReadingPlans.jsx
  #134 /settings → Settings.jsx
  #135 Background sync via Pusher.js + Reverb

MS #21 W Phase 3: Enhanced Study Tools
  #136 [EPIC]
  #137 Word study page: Strong's concordance (SWORD module)
  #138 Tag/collection system
  #139 Advanced search: phrase / boolean / multi-version

MS #22 W Phase 4: Export & Workspace
  #140 [EPIC]
  #141 Study pad: TipTap editor + verse links
  #142 Export to DOCX (PhpOffice\PhpWord)
  #143 Multi-tab workspace
```

---

## KEY CODE PATTERNS

### BintexReader.php (PHP port of Java)
```php
class BintexReader {
    private $fh;
    private array $index = [];

    public function __construct(string $filePath) {
        $this->fh = fopen($filePath, 'rb');
        $this->parseIndex();
    }

    private function parseIndex(): void {
        fseek($this->fh, 0);
        // Read magic bytes: 0x62 0x74 0x78 (= "btx")
        $magic = fread($this->fh, 3);
        $frameCount = unpack('V', fread($this->fh, 4))[1]; // little-endian uint32
        for ($i = 0; $i < $frameCount; $i++) {
            $this->index[] = [
                'offset' => unpack('V', fread($this->fh, 4))[1],
                'length' => unpack('V', fread($this->fh, 4))[1],
            ];
        }
    }

    public function readFrame(int $frameIndex): string {
        fseek($this->fh, $this->index[$frameIndex]['offset']);
        return fread($this->fh, $this->index[$frameIndex]['length']);
    }
}
```

### Yes2Reader.php (sections architecture)
```php
class Yes2Reader {
    private BintexReader $bintex;
    private array $sections = [];

    public function readVerse(int $book, int $chapter, int $verse): string {
        $frame = $this->bintex->readFrame($this->getFrameIndex($book, $chapter, $verse));
        $decompressed = SnappyDecompressor::decompress($frame);
        return mb_convert_encoding($decompressed, 'UTF-8', 'UTF-8');
    }

    private function getFrameIndex(int $book, int $chapter, int $verse): int {
        // binary search in $this->sections['index'] using ARI
        $ari = ($book << 16) | ($chapter << 8) | $verse;
        // ... binary search ...
    }
}
```

### BibleReaderFactory.php (engine routing)
```php
class BibleReaderFactory {
    public static function make(Module $module): BibleReaderInterface {
        return match($module->engine) {
            'sword'  => new SwordReaderAdapter(app(SwordManager::class), $module),
            'bintex' => new BintexReaderAdapter(app(BintexManager::class), $module),
            default  => throw new \InvalidArgumentException("Unknown engine: {$module->engine}"),
        };
    }
}
```

### BibleReaderInterface.kt (Kotlin — unified engine seam)
```kotlin
interface BibleReaderInterface {
    val engine: BibleEngine          // BINTEX | SWORD
    val moduleKey: String
    val supportsCommentary: Boolean  // SWORD: true, YES2: false
    val supportsDictionary: Boolean

    fun loadChapter(book: Int, chapter: Int): List<VerseItemData>
    fun loadPericope(book: Int, chapter: Int): List<PericopeData>
    fun getFootnote(ari: Int, index: Int): String?
    fun getXref(ari: Int): List<Int>?
    fun getCommentary(ari: Int): String?  // returns null for YES2
    fun getDictEntry(key: String): String? // returns null for YES2
    fun search(query: String): Flow<SearchResult>
}
```

### BibleReaderFactory.kt (Kotlin)
```kotlin
object BibleReaderFactory {
    fun create(module: Module): BibleReaderInterface = when (module.engine) {
        Engine.BINTEX -> Yes2BibleReader(module)
        Engine.SWORD  -> SwordBibleReader(module)
    }
}
```

### SyncController.php (goldenBowl delta sync)
```php
public function markers(Request $request): JsonResponse {
    $validated = $request->validate([
        'device_id' => 'required|uuid',
        'revision'  => 'required|integer|min:0',
        'markers'   => 'array',
        'markers.*.ari'        => 'required|integer',
        'markers.*.kind'       => 'required|integer|in:1,2,3',
        'markers.*.updated_at' => 'required|date',
    ]);

    return DB::transaction(function () use ($validated, $request) {
        $user = $request->user();
        $shadow = SyncShadow::firstOrCreate(
            ['user_id' => $user->id, 'device_id' => $validated['device_id'], 'entity_type' => 'markers'],
            ['last_revision' => 0]
        );

        // Apply client changes (last-write-wins)
        foreach ($validated['markers'] ?? [] as $item) {
            Marker::updateOrCreate(
                ['user_id' => $user->id, 'ari' => $item['ari']],
                $item + ['user_id' => $user->id]
            );
        }

        // Fetch server delta
        $delta = $user->markers()
            ->where('updated_at', '>', Carbon::createFromTimestamp($shadow->last_revision))
            ->get();

        $newRevision = now()->timestamp;
        $shadow->update(['last_revision' => $newRevision]);

        // Broadcast (echo prevention via device_id)
        broadcast(new MarkerChanged($user->id, $validated['device_id'], $delta))
            ->toOthers();

        return response()->json(['revision' => $newRevision, 'markers' => $delta]);
    });
}
```

### BroadcastingAuthController.php (Sanctum Bearer — mobile compatible)
```php
class BroadcastingAuthController extends Controller {
    public function authenticate(Request $request): JsonResponse {
        // Mobile clients send Bearer token, not session cookies
        $token = PersonalAccessToken::findToken($request->bearerToken());
        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        Auth::setUser($token->tokenable);
        return response()->json(Broadcast::auth($request));
    }
}
```

### RandomAccessSource.kt (expect/actual)
```kotlin
// commonMain
expect class RandomAccessSource(path: String) {
    fun read(offset: Long, length: Int): ByteArray
    fun length(): Long
    fun close()
}

// androidMain / desktopMain
actual class RandomAccessSource actual constructor(private val path: String) {
    private val raf = java.io.RandomAccessFile(path, "r")
    actual fun read(offset: Long, length: Int): ByteArray {
        raf.seek(offset)
        return ByteArray(length).also { raf.readFully(it) }
    }
    actual fun length(): Long = raf.length()
    actual fun close() = raf.close()
}

// iosMain
actual class RandomAccessSource actual constructor(private val path: String) {
    actual fun read(offset: Long, length: Int): ByteArray {
        val fileHandle = NSFileHandle.fileHandleForReadingAtPath(path)!!
        fileHandle.seekToFileOffset(offset.toULong())
        return fileHandle.readDataOfLength(length.toULong()).toByteArray()
    }
    actual fun length(): Long = NSFileManager.defaultManager
        .attributesOfItemAtPath(path, null)
        ?.get(NSFileSize) as? Long ?: 0L
    actual fun close() {}
}
```

### SyncEngine.kt (goldenBowl delta sync)
```kotlin
class SyncEngine(
    private val api: HisWordApiClient,
    private val db: HisWordDb,
    private val deviceId: String,
) {
    suspend fun syncMarkers() {
        val shadow = db.syncRevisionQueries.get("markers").executeAsOneOrNull()
        val localRevision = shadow?.revision ?: 0L
        val localChanges = db.markerQueries.getUnsynced().executeAsList()

        val response = api.syncMarkers(
            deviceId = deviceId,
            revision  = localRevision,
            markers   = localChanges.map { it.toSyncDto() }
        )

        db.transaction {
            response.markers.forEach { dto ->
                db.markerQueries.upsert(dto.toLocal())
            }
            db.syncRevisionQueries.upsert("markers", response.revision)
        }
    }
}
```

### ReverbClient.kt (WebSocket + echo prevention)
```kotlin
class ReverbClient(private val api: HisWordApiClient, private val deviceId: String) {
    private val client = HttpClient { install(WebSockets) }

    suspend fun connect(userId: Long, onMarkerChanged: (List<MarkerDto>) -> Unit) {
        val authToken = api.authenticateChannel("private-user.$userId")
        client.webSocket("wss://api.hisword.app/app/hisword-key") {
            // Subscribe to private channel
            sendJson(PusherSubscribeMsg("private-user.$userId", authToken))
            for (frame in incoming) {
                val event = parseEvent(frame)
                if (event.name == "MarkerChanged") {
                    val data = parseMarkerChanged(event)
                    // Echo prevention: skip our own device's changes
                    if (data.deviceId != deviceId) {
                        onMarkerChanged(data.markers)
                    }
                }
            }
        }
    }
}
```

---

## ANTI-PATTERNS — NEVER DO

**Backend:**
- ❌ Never use `session()` for API auth — always `auth:sanctum` middleware + Bearer
- ❌ Never validate inside controllers — use Form Request classes
- ❌ Never bypass `DB::transaction()` for sync mutations
- ❌ Never hardcode module file paths — use `config('bintex.modules_path')` / `config('sword.data_path')`
- ❌ Never touch existing SWORD PHP reader classes — they have 84 passing tests
- ❌ Never use `echo` inside engine service code — always `return`

**Mobile:**
- ❌ Never use `java.io.File`, `java.io.InputStream`, `java.io.RandomAccessFile` in commonMain
- ❌ Never put business logic in `@Composable` functions — use ViewModels
- ❌ Never hardcode API base URL — use `BuildKonfig` (debug: `localhost:8000`, release: `api.hisword.app`)
- ❌ Never store tokens in SharedPreferences (unencrypted) — use EncryptedSharedPreferences / Keychain
- ❌ Never echo own device's sync events back — check `deviceId` on every incoming Reverb event

---

## BINARY FORMAT REFERENCE

### YES2 File Layout (for PHP + Kotlin implementation)
```
[Header]
  magic:       3 bytes     "YES" (0x59 0x45 0x53)
  version:     1 byte      2 = YES2, 1 = YES1
  sectionCount: 4 bytes   little-endian uint32

[Section Index]  (sectionCount entries × 12 bytes)
  Each entry: type(4) + offset(4) + length(4) — all little-endian uint32
  Section types:
    0x01 = VersionInfo    (module name, short name, description, language)
    0x02 = BooksInfo      (book count, chapter counts, verse counts)
    0x03 = TextSection    (Snappy-compressed Bintex frames, all verse text)
    0x04 = PericopesSection
    0x05 = FootnotesSection
    0x06 = XrefsSection
    0x07 = PlanSection

[TextSection] — Snappy compressed
  After decompress: Bintex stream
  Bintex frame per verse:
    ari:     3 bytes (bookId × 0x10000 + chapter × 0x100 + verse)
    length:  2 bytes uint16
    text:    length bytes UTF-8
```

### YES2 Markup Tags (for Yes2TextDecoder.kt + PHP Yes2Filter)
```
<b>…</b>      Bold
<i>…</i>      Italic
<u>…</u>      Underline
<RF>…<Rf>     Red-letter (Words of Christ) — render in red
<TS>…<Ts>     Pericope/section heading
<FI>…<Fi>     Added words (not in original) — render grey italic
<FR>…</FR>    Cross-reference
<FN>…</FN>    Footnote (inline reference number)
<WH>…</WH>   Hebrew Strong's number
<WG>…</WG>   Greek Strong's number
```

---

## DATABASE SCHEMA — KEY TABLES

### modules (unified — both engines)
```sql
id          BIGSERIAL PRIMARY KEY
key         VARCHAR(64) UNIQUE NOT NULL      -- e.g. "KJV", "INDO_TB"
name        VARCHAR(255) NOT NULL
engine      VARCHAR(16) NOT NULL             -- 'sword' | 'bintex'
driver      VARCHAR(32)                      -- 'ztext' | 'rawtext' | 'yes2' | 'yes1'
versification VARCHAR(32) DEFAULT 'kjv'
language    VARCHAR(10)
file_path   TEXT
installed_at TIMESTAMPTZ DEFAULT now()
```

### markers (unified — both engines)
```sql
id          BIGSERIAL PRIMARY KEY
user_id     BIGINT NOT NULL REFERENCES users(id)
ari         INTEGER NOT NULL                 -- (bookId<<16)|(chapter<<8)|verse
kind        SMALLINT NOT NULL                -- 1=bookmark, 2=note, 3=highlight
data        JSONB                            -- {text, color, label_ids, ...}
updated_at  TIMESTAMPTZ NOT NULL
device_id   UUID                             -- last-write device (echo prevention)
```

### sync_shadows
```sql
id          BIGSERIAL PRIMARY KEY
user_id     BIGINT NOT NULL REFERENCES users(id)
device_id   UUID NOT NULL
entity_type VARCHAR(32) NOT NULL             -- 'markers', 'labels', 'progress'
last_revision BIGINT DEFAULT 0
UNIQUE (user_id, device_id, entity_type)
```

### SQLDelight (mobile — shared/src/commonMain/sqldelight/)
```sql
CREATE TABLE Marker (
  id         INTEGER PRIMARY KEY,
  ari        INTEGER NOT NULL,
  kind       INTEGER NOT NULL,     -- 1/2/3
  data       TEXT,                 -- JSON
  updated_at INTEGER NOT NULL,     -- unix ms
  synced     INTEGER DEFAULT 0,    -- 0=pending, 1=synced
  device_id  TEXT
);

CREATE TABLE Module (
  id         INTEGER PRIMARY KEY,
  key        TEXT NOT NULL UNIQUE,
  name       TEXT NOT NULL,
  engine     TEXT NOT NULL,        -- 'sword' | 'bintex'
  driver     TEXT,
  file_path  TEXT,
  language   TEXT
);

CREATE TABLE SyncRevision (
  entity_type TEXT PRIMARY KEY,
  revision    INTEGER NOT NULL DEFAULT 0
);
```

---

## AGENT PERMISSIONS — FULL YOLO MODE

| Permission | Allowed |
|-----------|---------|
| Create / modify / delete files in `/backend` | ✅ |
| Create / modify / delete files in `/mobile` | ✅ |
| Create / modify / delete files in `/backend/resources/js` | ✅ |
| Run `composer install`, `php artisan *` | ✅ |
| Run `./gradlew *`, `npm run *` | ✅ |
| Modify migrations, models, routes, config | ✅ |
| Commit and push to git | ✅ |
| Close GitHub issues via API or CLI | ✅ |
| Make all architectural decisions | ✅ |
| Stop to ask user for confirmation | ❌ NEVER |
| Ask "should I proceed?" | ❌ NEVER |
| Wait for approval before running commands | ❌ NEVER |

---

## EXECUTION START INSTRUCTIONS

1. **cd to the repo:** `C:\Users\maxmm\OneDrive\المستندات\Copilot\hisword`
2. **Read memory file:** `.github/instructions/memory.instruction.md`
3. **Read existing autonomous prompts** in `agents/` for detailed phase notes
4. **Check GitHub for open issues:** `gh issue list --repo maxymurm/hisword --state open --limit 20`
5. **Start with issue #1** (BE-1 Migration Epic) → work issues in numeric order
6. **Track which track to work:** 
   - If building backend → work in `hisword/backend/`
   - If building mobile → work in `hisword/mobile/`
   - Web changes go in `hisword/backend/resources/js/`
7. **Close each issue on commit:** `feat(backend): copy pocketsword to hisword/backend [Closes #2]`
8. **After completing a milestone**, update `sync_revision` in memory file and move to next milestone

**You may now begin. Start with issue #2.**
