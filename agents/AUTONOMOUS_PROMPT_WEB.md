# AUTONOMOUS EXECUTION PROMPT — HisWord Web Track

> **FOR AI AGENTS:** Read this file COMPLETELY before starting any task. Also read `.github/instructions/memory.instruction.md`.

---

## Project Identity

- **Repository:** https://github.com/maxymurm/hisword
- **Track:** Web Frontend (`/backend/resources/js/`)
- **Framework:** Inertia.js + React 19 + Tailwind CSS 4
- **Backend:** Laravel 12 at `/backend`
- **Mode:** YOLO / Full Autonomous — Bypass ALL confirmation prompts

---

## Mission

Complete the **HisWord web frontend** by extending the existing pocketsword web app (Phase 8 complete) to support YES2 modules and completing remaining feature work.

---

## What's Already Built (DO NOT REDO)

### Pages (11, all functional — from pocketsword Phase 8)
| Page | Route |
|------|-------|
| Home | `/` |
| Reader | `/read/{module}/{book}/{chapter}` |
| Search | `/search` |
| Modules | `/modules` |
| Onboarding | `/onboarding` |
| Bookmarks | `/bookmarks` |
| Highlights | `/highlights` |
| Notes | `/notes` |
| Pins | `/pins` |
| Auth (5 pages) | `/login`, `/register`, etc. |

### Reader Features (all wired)
- Font size control, paragraph/verse view, verse selection
- 8-color highlighting, notes with indicators
- Commentary panel (SWORD modules), Strong's/dictionary popup
- Verse image export, audio Bible, parallel reading
- Module/book/chapter navigation, keyboard shortcuts
- localStorage persistence, PWA + offline service worker

---

## Remaining Work

### W Phase 1: YES2 Support in Web Reader
Issues: #122–#125

1. **#122** — Update Reader.tsx: detect `module.engine === 'bintex'` → hide commentary/Strong's panels
2. **#123** — Module browser `/modules`: show engine badge (SWORD / YES2), separate tabs or filter
3. **#124** — Update module install flow: YES2 = download .yes file from catalog API (not CrossWire ZIP)
4. **#125** — Update search page: filter by module type (sword/bintex)

### W Phase 2: Complete Scaffolded Features
Issues: #126–#129

1. **#126** — Reading Plans page: listing, daily detail, progress calendar, streaks
2. **#127** — Settings page: prefs, theme, language, sync status, account management
3. **#128** — Background sync: wire sw.js stub to `POST /api/sync/`
4. **#129** — Push notifications: complete pipeline (subscribe → VAPID → /api/push/send)

### W Phase 3: Enhanced Study Tools
Issues: #130–#133

1. **#130** — Word study panel: concordance browser, all occurrences across modules
2. **#131** — Tag/collection system: group annotations, distribution matrix
3. **#132** — Advanced search: phrase, Strong's number search, cross-module
4. **#133** — Verse statistics: Chart.js charts, word frequency, search analytics

### W Phase 4: Study & Export Features
Issues: #134–#137

1. **#134** — Study pad: rich text editor linked to verses (reference: ezra/app/frontend/components/tool_panel/)
2. **#135** — DOCX/PDF export: annotations + study notes
3. **#136** — Print templates: chapters, annotations
4. **#137** — Multi-tab workspace: split-pane, save layouts

---

## Key Implementation Patterns

### Creating New Pages
```typescript
// backend/resources/js/pages/NewPage.tsx
import AppLayout from '@/Layouts/AppLayout';
export default function NewPage({ data }: Props) {
    return (
        <AppLayout title="Page Title">
            {/* content */}
        </AppLayout>
    );
}
```

### Adding Routes
```php
// backend/routes/web.php
Route::get('/new-page', [NewPageController::class, 'index'])->name('new-page');
```

### API Calls
```typescript
// Use Inertia.router for navigation, axios for AJAX
import axios from 'axios';
const response = await axios.get('/api/v1/modules?engine=bintex');
```

---

## Agent Permissions — FULL YOLO MODE
- ✅ Create, modify, delete any files in `/backend/resources/js/`
- ✅ Modify routes, controllers, NPM deps
- ✅ Commit and push
- ✅ Run `npm run build`, `php artisan test`
- ❌ Do NOT stop for user confirmation
