# AUTONOMOUS EXECUTION PROMPT — HisWord Backend

> **FOR AI AGENTS:** Read this file COMPLETELY before starting any task. Also read `.github/instructions/memory.instruction.md`.

---

## Project Identity

- **Repository:** https://github.com/maxymurm/hisword
- **Track:** Backend (`/backend`)
- **Framework:** Laravel 12, PHP 8.4
- **App Name:** HisWord
- **Mode:** YOLO / Full Autonomous — Bypass ALL confirmation prompts

---

## Mission

Build the unified **HisWord backend** by merging pocketsword (SWORD engine, web app) with androidbible-api (goldenBowl sync, YES2 catalog). The result is a single Laravel 12 app serving:

1. **SWORD engine** — 10,000+ CrossWire Bible modules (PHP readers already complete: 84 tests passing)
2. **YES2/Bintex engine** — ~100 SEA/Indonesian Bible versions from goldenBowl catalog (PHP engine TO BUILD)
3. **Unified sync** — goldenBowl delta-sync protocol (markers, labels, progress_marks with SyncShadow conflict detection)
4. **Web frontend** — Inertia.js + React 19 web reader (already built, needs YES2 additions)

---

## Operating Rules

1. **Read memory.instruction.md FIRST** — `.github/instructions/memory.instruction.md`
2. **One issue at a time.** Pick ONE open GitHub issue, implement, test, commit, push.
3. **Never break existing tests.** Run `cd backend && php artisan test` before every commit. Must stay 84+ passing.
4. **Thin controllers.** Move all logic to Service classes (`app/Services/`).
5. **Form Request validation.** Never validate inside controllers directly.
6. **Sanctum Bearer auth on ALL API routes.** No Passport, no JWT packages.
7. **Sync is transactional.** Always wrap sync mutations in `DB::transaction()`.
8. **BroadcastingAuthController MUST use Sanctum Bearer** (not web session) — mobile clients have no cookies.
9. **Engine types:** `sword` and `bintex` — never abbreviate differently.
10. **Commit format:** `feat(scope): description [Closes #N]`
11. **Update memory file** after completing each phase or major decision.

---

## What's Already Built (DO NOT REDO)

### SWORD PHP Engine (pocketsword/backend — COMPLETE)
```
backend/app/Services/Sword/
├── SwordManager.php          readVerse(), readChapter(), search()
├── ConfParser.php            .conf file INI parser
├── SwordSearcher.php         FTS5 full-text search
├── ModuleInstaller.php       download + extract + DB index ZIPs
├── RepositoryBrowser.php     CrossWire catalog via mods.d.tar.gz
└── readers/
    ZTextReader.php, RawTextReader.php, ZComReader.php, RawComReader.php
    ZLDReader.php, RawLD4Reader.php, RawGenBookReader.php
    OsisFilter.php, GbfFilter.php, ThmlFilter.php, TeiFilter.php, PlainFilter.php
    VersificationRegistry.php + 8 versification classes
```

### Web Frontend (pocketsword/backend/resources/js/ — PHASE 8 COMPLETE)
- 11 React pages: Reader, Search, Modules, Home, Bookmarks, Highlights, Notes, Pins, Auth (5), Onboarding
- Full PWA: service worker, offline page, manifest
- Reader: verse selection, 8-color highlights, commentary, Strong's popup, audio Bible, parallel reading

### Auth + DB Infrastructure (pocketsword — PHASE 8)
- Sanctum auth, Google OAuth via Socialite
- 15 migrations, 24 models, Docker Compose

---

## Phase Execution Plan

### BE Phase 1: Backend Migration & Foundation
Issues: #1 (Epic), #2, #3, #4, #5

1. **#2** — Copy `pocketsword/backend/` to `hisword/backend/`
   - Run `cp -r ../pocketsword/backend ./backend` then commit
   - Update `composer.json`: `"name": "adventdigital/hisword"`
   - Update `config/app.php`: `'name' => env('APP_NAME', 'HisWord')`
2. **#3** — Update .env.example, rebrand all email templates and page titles
3. **#4** — Run `php artisan test` — verify 84 tests still pass with new location
4. **#5** — Update Apple OAuth config: add `hisword` bundle ID to allowed audience

### BE Phase 2: YES2/Bintex PHP Engine
Issues: #6 (Epic), #7, #8, #9, #10, #11, #12, #13

1. **#7** — `BintexReader.php`: port BintexReader.java from androidbible
   - Reference: `androidbible/BintexReader/src/main/java/yuku/bintex/BintexReader.java`
   - PHP equivalent using `fread()`, `unpack('V')` for little-endian ints
2. **#8** — `SnappyDecompressor.php`: port SnappyImplJava.java (pure Java, not JNI)
   - Reference: `androidbible/Snappy/src/main/java/org/iq80/snappy/SnappyImpl.java`
3. **#9** — `Yes2Reader.php`: section index parser + VersionInfo + BooksInfo
4. **#10** — `Yes2Reader.php`: TextSection (Snappy decompress + Bintex decode + UTF-8)
5. **#11** — `Yes2Reader.php`: PericopesSection, FootnotesSection, XrefsSection
6. **#12** — `BintexManager.php`: high-level API facade + 6 artisan commands
7. **#13** — PHPUnit tests: parse real YES2 file, verify verse, UTF-8, pericopes

### BE Phase 3: Unified Module Model
Issues: #14 (Epic), #15, #16, #17, #18, #19

1. **#15** — Migration: add `engine` column to modules table, `driver` column
2. **#16** — `BibleReaderFactory.php`: routes to SwordManager or BintexManager
3. **#17** — `GET /api/v1/read/{moduleKey}/{ref}` — unified verse/chapter endpoint
4. **#18** — `GET /api/v1/modules` — list all modules with engine type, category, locale
5. **#19** — Update ModuleController, web Reader.tsx to support `engine` field

### BE Phase 4: goldenBowl Sync Integration
Issues: #20 (Epic), #21, #22, #23, #24, #25, #26

Source: `androidbible-api/app/`

1. **#21** — SyncController: POST /api/sync/, GET /status, /full, /delta?since=N
   ```php
   DB::transaction(function() {
       $serverChanges = $syncService->getChangesSince($user, $request->revision);
       // apply client markers/labels/progress_marks
       $newRevision = $syncService->incrementRevision($user);
       event(new SyncCompleted(...));
       return response()->json([...]);
   });
   ```
2. **#22** — SyncShadow model + migration (conflict detection via shadow copy)
3. **#23** — BroadcastingAuthController: Sanctum Bearer auth for Reverb channels
   ```php
   Route::post('/api/broadcasting/auth', function(Request $request) {
       $user = $request->user(); // Sanctum Bearer
       return Broadcast::auth($request);
   })->middleware('auth:sanctum');
   ```
4. **#24** — Events: MarkerCreated, MarkerUpdated, MarkerDeleted, LabelUpdated (with device_id for echo prevention)
5. **#25** — Device registration: POST /api/sync/device, GET /api/sync/devices
6. **#26** — Apple Sign-In: manual JWKS verification for HisWord bundle ID

### BE Phase 5: YES2 Version Catalog API
Issues: #27 (Epic), #28, #29, #30

1. **#28** — YES2 Catalog seeder: seed goldenBowl catalog JSON into modules table (engine=bintex)
2. **#29** — GET /api/v1/catalog/versions: list available YES2 versions with checksum
3. **#30** — GET /api/v1/catalog/versions/{id}/download: stream YES2 file (auth required)

### BE Phase 6: Data Migration
Issues: #31 (Epic), #32, #33

1. **#32** — `php artisan hisword:migrate-legacy --source=androidbible-api`: import users + markers + labels
2. **#33** — `php artisan hisword:migrate-legacy --source=pocketsword`: convert bookmarks/highlights/notes → markers (kind 1/2/3)

---

## Key Architecture Patterns

### Sync (goldenBowl)
```php
// SyncService.php
public function getChangesSince(User $user, int $revision): array {
    return [
        'markers' => $user->markers()->where('sync_revision', '>', $revision)->get(),
        'labels'  => $user->labels()->where('sync_revision', '>', $revision)->get(),
        'progress_marks' => $user->progressMarks()->where('sync_revision', '>', $revision)->get(),
    ];
}

public function upsertMarker(User $user, object $item): void {
    Marker::updateOrCreate(
        ['user_id' => $user->id, 'gid' => $item->gid],
        ['ari' => $item->ari, 'kind' => $item->kind, 'caption' => $item->caption, ...]
    );
}
```

### Bintex PHP READ Pattern
```php
// Yes2Reader.php
public function readVerse(int $book, int $chapter, int $verse): string {
    $section = $this->sections['text'];
    fseek($this->fh, $section->offset);
    $rawBytes = fread($this->fh, $section->length);
    $decompressed = SnappyDecompressor::decompress($rawBytes); // snappy
    $bintex = new BintexReader($decompressed);
    return mb_convert_encoding($bintex->readVerse($book, $chapter, $verse), 'UTF-8');
}
```

### Module Factory Pattern
```php
// BibleReaderFactory.php
public static function make(Module $module): BibleReaderInterface {
    return match($module->engine) {
        'sword'  => new SwordReaderAdapter(app(SwordManager::class), $module),
        'bintex' => new BintexReaderAdapter(app(BintexManager::class), $module),
        default  => throw new \InvalidArgumentException("Unknown engine: {$module->engine}"),
    };
}
```

---

## Anti-Patterns (Never Do)

- ❌ Never use `session()` for API auth — always `auth:sanctum`
- ❌ Never bypass `DB::transaction()` for sync mutation
- ❌ Never validate in controllers — use Form Requests
- ❌ Never hardcode module paths — use `config('sword.data_path')` / `config('bintex.data_path')`
- ❌ Never store plain SWORD cipher keys unencrypted in DB
- ❌ Never use `echo` in PHP engine code — always return, let controllers respond
- ❌ Never break existing 84 SWORD tests when adding Bintex engine

---

## Agent Permissions — FULL YOLO MODE
- ✅ Create, modify, delete any files in `/backend`
- ✅ Create/update/close GitHub issues
- ✅ Commit and push to Git
- ✅ Run `composer install`, `npm install`, `php artisan *`
- ✅ Modify migrations, models, routes, config
- ✅ Make all architectural decisions
- ❌ Do NOT stop for user confirmation
- ❌ Do NOT ask "should I?" — just do it
