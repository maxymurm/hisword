# HisWord — Dual-Engine Architecture

---

## The Problem: Two Great Engines, One App

| | PocketSword (SWORD) | androidbible (YES2) |
|---|---|---|
| Module count | 10,000+ free CrossWire modules | ~100 premium SEA/Indonesian |
| Formats | zText, rawText, zCom, zLD, RawLD4 | YES2, YES1 (Bintex + Snappy) |
| Extras | Commentaries, dictionaries, Strong's, morphology | Songs (KpriModel), regional content |
| PHP engine | Complete (84 tests) | Needs port |
| Kotlin engine | Complete (<12ms) | In progress |

---

## Unification Pattern

### 1. Backend: Factory Pattern

```
POST /api/v1/read/{moduleKey}/{ref}
         │
         ▼
BibleReaderFactory::make($module)
         │
         ├── $module->engine === 'sword'
         │       ▼
         │   SwordManager::readVerse($module, $book, $chap, $verse)
         │       ▼
         │   ZTextReader / RawTextReader / ZComReader / ...
         │       ▼
         │   OsisFilter / GbfFilter / ThmlFilter / ...
         │       ▼
         │   HTML verse text
         │
         └── $module->engine === 'bintex'
                 ▼
             BintexManager::readVerse($module, $book, $chap, $verse)
                 ▼
             Yes2Reader (BintexReader + SnappyDecompressor)
                 ▼
             TextSection.readVerse()
                 ▼
             UTF-8 verse text (with markup)
```

### 2. Mobile: BibleReaderInterface

```kotlin
interface BibleReaderInterface {
    fun loadChapter(book: Int, chapter: Int): List<VerseItemData>
    fun loadPericope(book: Int, chapter: Int): List<PericopeData>
    fun getFootnote(ari: Int, index: Int): String?
    fun getXref(ari: Int): List<Int>?
    fun getCommentary(ari: Int): String?      // SWORD only, returns null for YES2
    fun getDictionary(strongsRef: String): String?  // SWORD only, null for YES2
    val supportsCommentary: Boolean
    val supportsDictionary: Boolean
    val engine: BibleEngine      // SWORD or BINTEX
}

object BibleReaderFactory {
    fun make(module: ModuleEntity): BibleReaderInterface = when (module.engine) {
        BibleEngine.SWORD  -> SwordBibleReader(swordManager, module)
        BibleEngine.BINTEX -> Yes2BibleReader(yes2Reader, module)
    }
}
```

### 3. Versification Alignment

SWORD modules declare versification (KJV, NRSV, Catholic, Synodal, German).
All are mapped to a canonical ARI space for:
- Marker storage (markers reference ARI, not module-specific verse numbers)
- Cross-module navigation ("go to same verse in different version")

```kotlin
// SwordVersification.kt (from pocketsword, already complete)
object VersificationRegistry {
    fun getVersification(module: SwordModuleConfig) = when (module.versification) {
        "KJV"      -> KjvVersification
        "NRSV"     -> NrsvVersification
        "Catholic" -> CatholicVersification
        // ...
    }
}

// Convert SWORD book/chap/verse → ARI (canonical)
fun toAri(book: Int, chapter: Int, verse: Int): Int =
    (book shl 16) or (chapter shl 8) or verse
```

---

## Module Model (Unified DB Schema)

```sql
CREATE TABLE modules (
    id              BIGSERIAL PRIMARY KEY,
    key             VARCHAR(64)  UNIQUE NOT NULL,  -- e.g. 'KJV', 'tb_ind', 'ESV'
    engine          VARCHAR(16)  NOT NULL CHECK (engine IN ('sword','bintex')),
    driver          VARCHAR(32)  NOT NULL,          -- 'ztext'|'rawtext'|'yes2'|'yes1'
    short_name      VARCHAR(64)  NOT NULL,
    long_name       VARCHAR(255),
    locale          VARCHAR(16),
    description     TEXT,
    category        VARCHAR(32),                    -- 'bible'|'commentary'|'dictionary'|'lexicon'
    data_path       VARCHAR(512) NOT NULL,          -- FS path to binary data
    versification   VARCHAR(32)  DEFAULT 'KJV',
    source_type     VARCHAR(16)  DEFAULT 'OSIS',    -- SWORD only
    cipher_key      VARCHAR(128),                   -- encrypted SWORD modules
    repository_source VARCHAR(64),                  -- 'crosswire'|'goldenbowl'
    installed_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE installed_versions (
    id        BIGSERIAL PRIMARY KEY,
    user_id   BIGINT REFERENCES users(id),
    module_id BIGINT REFERENCES modules(id),
    is_active BOOL DEFAULT TRUE,
    ordering  INT  DEFAULT 0,
    UNIQUE (user_id, module_id)
);
```

---

## Markup Rendering

| Engine | Source Markup | Mobile Renderer | Output |
|--------|--------------|-----------------|--------|
| YES2 | Custom inline tags (red-letter, bold) | `FormattedVerseText.kt` → YES2 branch | AnnotatedString |
| SWORD | OSIS XML | `OsisTextFilter.kt` (from pocketsword) | AnnotatedString |
| SWORD | GBF | `GbfFilter.kt` | AnnotatedString |
| SWORD | ThML | `ThmlFilter.kt` | AnnotatedString |

The `FormattedVerseText` composable dispatches by engine:
```kotlin
@Composable
fun FormattedVerseText(verse: VerseItemData) {
    val annotated = when (verse.engine) {
        BibleEngine.BINTEX -> Yes2MarkupParser.parse(verse.rawText)
        BibleEngine.SWORD  -> OsisTextFilter.parse(verse.rawText)
    }
    Text(annotated)
}
```

---

## Sync Architecture (goldenBowl Protocol)

Markers reference ARI — so they work across engine types. A user bookmarks an ARI; the ARI is engine-neutral. The same bookmark shows regardless of which version the user switches to.

```
User creates bookmark (ARI=123456, kind=BOOKMARK)
                │
                ▼
        SQLDelight insert (Marker table)
        SyncRevision = 0 (dirty)
                │
                ▼
SyncEngine.sync() → POST /api/sync/
{
  "revision": N,
  "device_id": "...",
  "markers": [{ "gid": "UUID", "action": "upsert", "ari": 123456, "kind": 1, ... }]
}
                │
                ▼
        Server: SyncService upserts Marker for user
        Server: increments revision
        Server: broadcasts to other devices via Reverb
        Server: returns { server_revision, markers[], labels[], progress_marks[] }
                │
                ▼
SyncEngine applies server changes (ConflictResolver: last-write-wins by modifyTime)
```

---

## Module Download Flows

### YES2 (goldenBowl catalog)
```
GET /api/v1/catalog/versions                  → list available YES2 files
GET /api/v1/catalog/versions/{id}/download   → stream .yes file

Mobile VersionRepository:
1. Fetch catalog from HisWord API
2. Download .yes file to documents directory
3. Verify checksum
4. INSERT into InstalledVersion (SQLDelight)
5. Refresh BibleVersionRepository
```

### SWORD (CrossWire + HisWord proxy)
```
GET /api/v1/catalog/sword                     → CrossWire catalog (via RepositoryBrowser)
GET /api/v1/catalog/sword/{key}/download     → ZIP download

Mobile SwordModuleRepository:
1. Fetch CrossWire catalog
2. Download ZIP via Ktor
3. SwordModuleInitializer: extract to documentsDirectory/sword/
4. Parse .conf file → SwordModuleConfig
5. INSERT into Module table (SQLDelight)
6. Refresh BibleVersionRepository
```

---

## Feature Matrix

| Feature | YES2 | SWORD | Notes |
|---------|------|-------|-------|
| Bible reading | ✅ | ✅ | Both engines |
| Pericope headings | ✅ | limited | YES2 has rich pericopes |
| Footnotes | ✅ | ✅ | Both |
| Cross-references | ✅ | ✅ | Both |
| Commentary | ❌ | ✅ | SWORD-only (MHCC, TSK, etc.) |
| Dictionary/Lexicon | ❌ | ✅ | SWORD-only (Strong's, Thayer) |
| Strong's numbers | ❌ | ✅ | SWORD-only (tagged modules) |
| Morphology | ❌ | ✅ | SWORD-only |
| Songs/Hymns | ✅ | ❌ | KpriModel — YES2 repo only |
| Indonesian content | ✅ | limited | goldenBowl catalog |
| Free global content | ❌ | ✅ | CrossWire 10,000+ |
| Full-text search | ✅ | ✅ | Both (Meilisearch / FTS5) |
| Markers/Sync | ✅ | ✅ | Engine-agnostic (ARI-based) |
| Reading plans | ✅ | ❌ | HisWord backend |
| Devotionals | ✅ | ❌ | HisWord backend |
