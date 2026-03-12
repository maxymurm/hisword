# HisWord — Agent Automation Guide

## Project Overview

HisWord is a unified Bible study app with three development tracks:

| Track | File | Description |
|-------|------|-------------|
| Backend | `agents/AUTONOMOUS_PROMPT_BACKEND.md` | Laravel 12, PHP SWORD + YES2 engines, sync |
| Mobile | `agents/AUTONOMOUS_PROMPT_MOBILE.md` | Compose Multiplatform, Kotlin SWORD + YES2 |
| Web | `agents/AUTONOMOUS_PROMPT_WEB.md` | Inertia.js + React 19 web reader |

---

## Start Here

1. **Read** `.github/instructions/memory.instruction.md` — always first
2. **Pick your track** — backend, mobile, or web
3. **Read the matching autonomous prompt** — it has the full context and issue list
4. **Pick the next open GitHub issue** — work one at a time
5. **Implement, test, commit, push, close issue**

---

## Repository Structure

```
hisword/
├── backend/                 ← Laravel 12 (copy from pocketsword/backend + add YES2)
├── mobile/                  ← Compose Multiplatform (androidbible-kmp + pocketsword/mobile)
├── docs/
│   ├── ARCHITECTURE.md      ← Dual-engine design (READ THIS)
│   ├── MIGRATION.md         ← What was migrated from legacy repos
│   └── PROJECT_DOCUMENTATION.md
├── agents/
│   ├── AGENTS.md            ← THIS FILE
│   ├── AUTONOMOUS_PROMPT_BACKEND.md
│   ├── AUTONOMOUS_PROMPT_MOBILE.md
│   └── AUTONOMOUS_PROMPT_WEB.md
└── .github/instructions/memory.instruction.md
```

---

## Source Repositories (DO NOT MODIFY)

| Repo | What To Use |
|------|-------------|
| `pocketsword` | Copy backend code (PHP SWORD engine, web frontend) to `hisword/backend/` |
| `pocketsword` | Copy KMP SWORD engine to `hisword/mobile/shared/src/.../data/sword/` |
| `androidbible-api` | Port goldenBowl sync protocol patterns to `hisword/backend/app/` |
| `androidbible-kmp` | Port YES2 binary readers to `hisword/mobile/shared/src/.../data/bintex/` |
| `androidbible` | Java source reference for YES2/Bintex/Snappy port |

---

## Conventions

- **Commit format:** `feat(be): description [Closes #N]` or `feat(mb):` or `feat(web):`
- **Branch:** `main`
- **Tests before commit:** `php artisan test` (backend), `./gradlew :shared:testDebugUnitTest` (mobile)
- **GID:** UUID v4, generated on create
- **ARI:** `(bookId shl 16) or (chapter shl 8) or verse` — used everywhere, engine-agnostic
- **Marker kinds:** 1=Bookmark, 2=Note, 3=Highlight

---

## GitHub
- **Repository:** https://github.com/maxymurm/hisword
- **Project Board:** https://github.com/users/maxymurm/projects/[board_number]
- **GitHub CLI:** `& "C:\Program Files\GitHub CLI\gh.exe"`
- **Token:** Available in memory file
