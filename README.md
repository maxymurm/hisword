# HisWord

A comprehensive, cross-platform Bible study application combining two battle-tested engines:

- **YES2/Bintex engine** — high-quality Indonesian/SEA Bible versions from the goldenBowl catalog
- **SWORD engine** — 10,000+ free Bible versions, commentaries, dictionaries, and lexicons from CrossWire

Available on **Android**, **iOS**, **Desktop**, and **Web**.

---

## Repository Structure

```
hisword/
├── backend/            ← Laravel 12 unified API + Web (PHP 8.4)
├── mobile/             ← Compose Multiplatform app (Android + iOS + Desktop)
├── docs/               ← Architecture, migration, API reference
│   ├── ARCHITECTURE.md ← Dual-engine design
│   ├── MIGRATION.md    ← Legacy repo migration guide
│   └── PROJECT_DOCUMENTATION.md
├── agents/             ← AI agent automation docs
│   ├── AUTONOMOUS_PROMPT_BACKEND.md
│   ├── AUTONOMOUS_PROMPT_MOBILE.md
│   └── AUTONOMOUS_PROMPT_WEB.md
└── .github/
    └── instructions/memory.instruction.md
```

---

## Legacy Source Repos

| Repo | Role | Status |
|------|------|--------|
| [androidbible-api](https://github.com/maxymurm/androidbible-api) | goldenBowl Laravel 11 backend (YES2 sync, markers, plans) | Phases 1–12 complete |
| [androidbible-kmp](https://github.com/maxymurm/androidbible-kmp) | BibleCMP Compose Multiplatform (YES2 port) | Phases 1–12 complete, 13–20 planned |
| [pocketsword](https://github.com/maxymurm/pocketsword) | SWORD engine (PHP + Kotlin KMP) + web frontend | Phase 8 complete, 84 PHP tests passing |

---

## Technology Stack

### Backend (`/backend`)
- **Framework:** Laravel 12 + PHP 8.4
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis 7 + Laravel Horizon
- **WebSocket:** Laravel Reverb (Pusher protocol)
- **Search:** Meilisearch
- **Auth:** Laravel Sanctum (Bearer tokens + Google/Apple OAuth)
- **Admin:** Filament 3
- **Web Frontend:** Inertia.js + React 19 + Tailwind CSS 4

### Mobile (`/mobile`)
- **Platform:** Compose Multiplatform (Android + iOS + Desktop)
- **Language:** Kotlin 2.1+ (100% commonMain)
- **DI:** Koin 4
- **Database:** SQLDelight 2 (multiplatform SQLite)
- **Networking:** Ktor 3
- **Serialization:** kotlinx.serialization

---

## Developer

Maxwell Murunga (@maxymurm) / Advent Digital

---

## Project Board

https://github.com/users/maxymurm/projects/11

**143 issues** across 22 milestones:
- BE Phase 1–8: Backend migration, YES2 PHP engine, unified module model, sync, catalog, data migration, web, deployment
- MB Phase 1–10: Mobile foundation, YES2 readers, SWORD integration, BibleReaderInterface, reader UI, navigation, markers, sync/auth, content, release
- W Phase 1–4: YES2 web support, reading plans/settings, study tools, export/workspace
