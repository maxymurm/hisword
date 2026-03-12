# HisWord — Migration Guide

This document explains what was migrated from each legacy repo and what still needs manual migration work.

---

## Source Repos → HisWord

| Legacy Repo | Role | Status |
|-------------|------|--------|
| `androidbible-api` | goldenBowl sync protocol, YES2 metadata | Port to `/backend` (Phase BE-4, BE-5) |
| `androidbible-kmp` | YES2/Bintex KMP port | Port to `/mobile` (Phase MB-2) |
| `pocketsword/backend` | SWORD engine, web frontend, auth | Copy to `/backend` (Phase BE-1) |
| `pocketsword/mobile` | SWORD KMP engine | Copy to `/mobile/shared/data/sword/` (Phase MB-3) |

---

## Backend Migration Tasks

### Phase BE-1: Copy pocketsword/backend → hisword/backend/

```bash
# In hisword/
cp -r ../pocketsword/backend ./backend

# Update composer.json
# "name": "adventdigital/hisword"
# "description": "HisWord - Unified Bible Study App Backend"

# Update config/app.php
# 'name' => env('APP_NAME', 'HisWord'),

# Update .env.example
# APP_NAME=HisWord
# APP_URL=https://hisword.app

# Verify tests pass
cd backend
php artisan test   # should be 84 passing
```

### Phase BE-4: Port goldenBowl sync from androidbible-api

Copy these files from `androidbible-api/`:
```
app/Http/Controllers/Api/Auth/AuthController.php         → backend/app/Http/Controllers/Api/Auth/
app/Http/Controllers/Api/Auth/SocialAuthController.php   → same
app/Http/Controllers/Api/SyncController.php              → backend/app/Http/Controllers/Api/
app/Http/Controllers/Api/BroadcastingAuthController.php  → same
app/Services/SyncService.php                             → backend/app/Services/
app/Models/Marker.php                                    → merge with existing
app/Models/Label.php                                     → merge with existing
app/Models/Device.php                                    → new
app/Events/MarkerCreated.php                             → backend/app/Events/
app/Events/MarkerUpdated.php                             → same
app/Events/MarkerDeleted.php                             → same
app/Events/LabelUpdated.php                              → same
database/migrations/*_create_markers_table.php           → adapt
database/migrations/*_create_labels_table.php            → adapt
database/migrations/*_create_devices_table.php           → new
database/migrations/*_create_sync_shadows_table.php      → new
routes/channels.php                                      → merge
```

Key differences to resolve:
- pocketsword uses `annotations` table (bookmarks/highlights/notes separate columns)
- goldenBowl uses `markers` table with `kind` enum (1=bookmark, 2=note, 3=highlight)
- **Decision:** Use goldenBowl `markers` table pattern (unified Kind enum)
- Migration needed: convert pocketsword annotations → markers (kind=1/2/3)

### Phase BE-5: YES2 Catalog from androidbible-api

Copy/adapt from `androidbible-api/`:
```
app/Http/Controllers/Api/VersionController.php   → adapt as ModuleCatalogController
app/Models/BibleVersion.php (preset model)       → adapt as YES2CatalogEntry
database/migrations/*_create_bible_versions_*    → adapt
resources/static/catalog.json (if present)       → seed for catalog
```

---

## Mobile Migration Tasks

### Phase MB-1: Foundation from androidbible-kmp

```bash
# Create hisword/mobile/ based on androidbible-kmp structure
cp -r ../androidbible-kmp ./mobile

# Update settings.gradle.kts
# rootProject.name = "HisWord"

# Update composeApp/src/androidMain/AndroidManifest.xml
# package="com.adventdigital.hisword"

# Update all Kotlin package declarations
# package yuku.alkitab.base → com.adventdigital.hisword
```

### Phase MB-3: SWORD Engine from pocketsword

Copy from `pocketsword/mobile/shared/src/`:
```
commonMain/.../sword/               → mobile/shared/src/commonMain/kotlin/data/sword/
androidMain/.../sword/              → mobile/shared/src/androidMain/kotlin/data/sword/
iosMain/.../sword/                  → mobile/shared/src/iosMain/kotlin/data/sword/
```

Also copy bundled module ZIPs:
```
pocketsword/mobile/composeApp/src/androidMain/assets/sword/KJV.zip     → mobile/composeApp/src/androidMain/assets/sword/
pocketsword/mobile/composeApp/src/androidMain/assets/sword/MHCC.zip    → same
pocketsword/mobile/composeApp/src/androidMain/assets/sword/Strongs.zip → same
pocketsword/mobile/composeApp/src/androidMain/assets/sword/Robinson.zip → same
```

---

## Data Migration (Phase BE-6)

### androidbible-api Users → HisWord

```sql
-- Export from androidbible-api PostgreSQL
COPY (
    SELECT id, name, email, email_verified_at, password, created_at
    FROM users
) TO '/tmp/androidbible_users.csv' CSV HEADER;

-- Also export markers (kind already uses 1/2/3)
COPY (
    SELECT gid, user_id, ari, kind, caption, verse_count, create_time, modify_time
    FROM markers WHERE deleted = false
) TO '/tmp/androidbible_markers.csv' CSV HEADER;

COPY (SELECT gid, user_id, title, ordering, background_color FROM labels WHERE deleted = false)
TO '/tmp/androidbible_labels.csv' CSV HEADER;

COPY (SELECT marker_gid, label_gid FROM marker_labels)
TO '/tmp/androidbible_marker_labels.csv' CSV HEADER;
```

### pocketsword Users → HisWord

```sql
-- pocketsword uses separate bookmarks/highlights/notes tables
-- Convert to unified markers (kind 1/2/3)

COPY (SELECT id, name, email, password, created_at FROM users)
TO '/tmp/pocketsword_users.csv' CSV HEADER;

-- Bookmarks → kind=1
COPY (
    SELECT gen_random_uuid() as gid, user_id,
           (book_id << 16 | chapter_id << 8 | verse_id) as ari,
           1 as kind, notes as caption, 1 as verse_count,
           created_at, updated_at
    FROM bookmarks
) TO '/tmp/pocketsword_bookmarks.csv' CSV HEADER;

-- Highlights → kind=3
COPY (
    SELECT gen_random_uuid() as gid, user_id,
           (book_id << 16 | chapter_id << 8 | verse_id) as ari,
           3 as kind, NULL as caption, 1 as verse_count,
           created_at, updated_at
    FROM highlights
) TO '/tmp/pocketsword_highlights.csv' CSV HEADER;

-- Notes → kind=2
COPY (
    SELECT gen_random_uuid() as gid, user_id,
           (book_id << 16 | chapter_id << 8 | verse_id) as ari,
           2 as kind, content as caption, 1 as verse_count,
           created_at, updated_at
    FROM notes
) TO '/tmp/pocketsword_notes.csv' CSV HEADER;
```

### Import into HisWord

```bash
# Run Laravel migration script
php artisan hisword:migrate-legacy-data \
    --androidbible-users=/tmp/androidbible_users.csv \
    --androidbible-markers=/tmp/androidbible_markers.csv \
    --pocketsword-users=/tmp/pocketsword_users.csv \
    --pocketsword-bookmarks=/tmp/pocketsword_bookmarks.csv \
    --pocketsword-highlights=/tmp/pocketsword_highlights.csv \
    --pocketsword-notes=/tmp/pocketsword_notes.csv
```

The migration command should:
1. Merge users by email (androidbible takes priority if email collision)
2. Generate new UUIDs for pocketsword annotations (GIDs)
3. Compute SWORD ARI from book/chapter/verse if needed
4. Log conflicts and skips to `storage/logs/migration.log`

---

## What Is NOT Migrated

| Legacy Feature | Decision |
|----------------|----------|
| pocketsword PWA offline cache | Not migrated (web sessions, no user data) |
| androidbible-api device tokens | Re-register on first login to HisWord |
| pocketsword reading history | Not migrated (no persistent history in DB) |
| SWORD module cipher keys | Re-enter in HisWord settings (security) |
| YES2 downloaded Bible files | Users re-download (files, not DB data) |
