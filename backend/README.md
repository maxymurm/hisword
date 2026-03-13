# HisWord — Backend

Laravel 12 backend with a pure-PHP SWORD binary engine for offline Bible reading.

## SWORD Engine

The PHP SWORD engine reads CrossWire `.dat/.idx/.bzz/.bdt/.bks` files directly — no C library, no FFI, no pre-extraction. Located in `app/Services/Sword/`.

### Architecture

```
SwordManager              → High-level API (readChapter, readVerse, readDictionary)
├── Readers/              → Binary format drivers
│   ├── ZTextReader       → zText compressed Bibles (KJV, ESV, etc.)
│   ├── ZComReader        → zCom compressed commentaries
│   ├── ZLDReader         → zLD compressed lexicons
│   ├── RawLDReader       → rawLD4 dictionaries (Strong's)
│   ├── RawComReader      → rawCom uncompressed commentaries
│   ├── RawTextReader     → rawText uncompressed Bibles
│   └── RawGenBookReader  → rawGenBook tree-structured modules
├── Filters/              → Markup conversion (OSIS/GBF/ThML/TEI → HTML)
│   ├── OsisFilter        → OSIS XML → HTML (Strong's, red letters, divine name)
│   ├── GbfFilter         → GBF → HTML
│   ├── ThmlFilter        → ThML → HTML
│   ├── TeiFilter         → TEI → HTML
│   └── PlainFilter       → Strip to plain text
├── Versification/        → Verse-numbering systems
│   ├── KjvVersification  → KJV (default, 31,102 verses)
│   ├── CatholicVersification, GermanVersification, etc.
│   └── VersificationRegistry → Auto-detect from module .conf
├── ConfParser            → SWORD .conf file parser
├── SwordSearcher         → FTS5 full-text search engine
├── ModuleInstaller       → Download/install modules from repositories
└── RepositoryBrowser     → Browse CrossWire/remote module repositories
```

### Supported Module Types

| Driver | Format | Example Modules |
|--------|--------|-----------------|
| zText | Compressed Bible | KJV, ESV, NASB |
| zCom | Compressed Commentary | — |
| rawCom | Uncompressed Commentary | MHCC |
| rawLD4 | 4-byte Dictionary | StrongsRealGreek, StrongsRealHebrew |
| zLD | Compressed Lexicon | Robinson |
| rawText | Uncompressed Bible | — |
| rawGenBook | Tree-structured | — |

### Full-Text Search

FTS5-based search engine (`SwordSearcher`) indexes SWORD modules into per-module SQLite databases at `storage/app/sword-search/{module}.db`.

```bash
# Index a module (31,102 KJV verses in ~2.6s)
php artisan sword:index KJV

# Force reindex
php artisan sword:index KJV --force
```

Search queries return results in <3ms with snippet highlighting. Supports exact phrases (`"in the beginning"`) and prefix matching (`salvat*`).

### Artisan Commands

```bash
php artisan sword:list                    # List installed modules
php artisan sword:info KJV                # Show module metadata
php artisan sword:read KJV Gen 1          # Read a chapter
php artisan sword:install KJV             # Install a module
php artisan sword:remove KJV              # Remove a module
php artisan sword:index KJV [--force]     # Build FTS5 search index
php artisan sword:refresh-sources         # Refresh remote repository lists
php artisan sword:install-bundled         # Install bundled default modules
```

### Module Storage

Modules are stored at `storage/app/sword-modules/{ModuleName}/` with the standard SWORD directory layout. The `.conf` files go in `storage/app/sword-modules/mods.d/`.

## Tests

84 passing tests, 2 skipped. Run with:

```bash
php artisan test --filter=Sword
```

### Test Coverage

| Test File | Tests | Coverage |
|-----------|-------|----------|
| `SwordManagerTest` | 14 | readChapter, readVerse, Strong's, red letters, divine name, dictionary, commentary, performance |
| `ZTextReaderTest` | 10 | Binary reading, verse counts, sequential keys, out-of-range handling |
| `ZComReaderTest` | 2 | Commentary reading (MHCC) |
| `ZLDReaderTest` | 3 | Compressed lexicon (Robinson) |
| `RawLDReaderTest` | 4 | Dictionary reading (Strong's Greek/Hebrew) |
| `OsisFilterTest` | 19 | OSIS→HTML: Strong's, red letters, divine name, poetry, footnotes, cross-refs |
| `KjvVersificationTest` | 18 | Flat index, verse counts, book navigation, testament detection |
| `ConfParserTest` | 5 | .conf parsing, metadata extraction, multiline values |

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan sword:install-bundled   # Install default modules (KJV, MHCC, Strong's)
php artisan sword:index KJV         # Build search index
```

## Tech Stack

- **PHP** 8.4 / **Laravel** 12
- **React** 19 + **Inertia.js** (frontend)
- **Tailwind CSS** 4
- **SQLite** (FTS5 for search)
- **PHPUnit** 11
