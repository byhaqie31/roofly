# Claude Code Prompt — Roofly Phase 1 Scaffolding

Copy everything below into a fresh Claude Code session inside your `roofly` repo.

---

## The Prompt

```
You are helping me bootstrap a new SaaS project called Roofly — a property rental
management platform for Malaysian landlords. Read PROJECT.md in the repo root
first; it's the source of truth for all architecture decisions, naming, and scope.

We are starting Phase 1 — Foundation. The repo is empty except for README.md and
PROJECT.md. Your job is to scaffold a clean monorepo with Laravel 11 (backend)
and Nuxt 3 (frontend), wired together with Docker Compose, ready for development.

## Phase 1 deliverables

You will set up:

1. **Monorepo structure**
   ├── backend/          (Laravel 11)
   ├── frontend/         (Nuxt 3 + TS)
   ├── docker-compose.yml
   ├── .env.example       (root-level shared vars)
   └── .gitignore

2. **Docker Compose services**
   - backend (PHP 8.3-fpm)
   - frontend (Node 20)
   - mysql (8.0)
   - redis (7-alpine)
   - rabbitmq (3-management)
   - nginx (reverse proxy for backend in dev)

3. **Backend (Laravel 11) — install and configure**
   - Laravel 11 fresh install
   - Sanctum (SPA cookie auth)
   - Spatie Permission (role-based access)
   - Spatie MediaLibrary (file uploads)
   - Spatie ActivityLog (audit trail)
   - Pest for testing
   - Laravel Pint for formatting
   - Scribe for API docs

4. **Frontend (Nuxt 3) — install and configure**
   - Nuxt 3 with TypeScript
   - Tailwind CSS
   - Pinia
   - @nuxtjs/i18n (BM + EN, with sample keys for: auth, dashboard, common)
   - VeeValidate + Zod
   - Lucide icons
   - Sanctum integration via $fetch wrapper composable

5. **Database — Phase 1 entities only**
   Create migrations + Eloquent models + factories for:
   - users (extended Laravel default with: phone, role enum, soft deletes, UUID)
   - properties (id, owner_id, name, address, city, state, postcode, type enum, soft deletes)
   - units (id, property_id, label, bedrooms, bathrooms, sqft, status enum, soft deletes)

   All IDs are UUIDs. Use Laravel's HasUuids trait.
   Soft deletes on all three.
   Add Spatie ActivityLog trait to all three.

6. **Enums** (PHP 8.3 backed enums in app/Enums/)
   - UserRole: Owner, Tenant, Admin
   - PropertyType: Condo, Landed, Shoplot, Room
   - UnitStatus: Vacant, Occupied, Maintenance

7. **Auth flow**
   - POST /api/auth/register (owner-only signup, returns Sanctum token)
   - POST /api/auth/login
   - POST /api/auth/logout
   - GET /api/auth/me
   - Middleware: 'role:owner', 'role:tenant', 'role:admin'

8. **Seeders with realistic Malaysian data**
   - 1 admin: admin@roofly.my / password
   - 1 owner: owner@roofly.my / password (Cik Aminah, KL phone)
   - 1 tenant: tenant@roofly.my / password (Adi)
   - 3 properties for the owner (a condo in Mont Kiara, a landed in Bangsar,
     a shoplot in Bukit Bintang) with realistic Jalan addresses and Malaysian
     postcodes
   - 5 units across the properties with mixed statuses

9. **Frontend pages (shells, no real data wiring yet)**
   - / (redirect based on role)
   - /auth/login
   - /auth/register
   - /owner (dashboard shell with sidebar: Dashboard, Properties, Tenants,
     Agreements, Payments, Maintenance, Reports, Settings)
   - /tenant (dashboard shell with sidebar: Home, Agreement, Payments,
     Tickets, Profile)
   - Layouts: auth (centered card), owner (sidebar + topbar), tenant (sidebar + topbar)
   - Use a clean, minimal design. Off-white background (#FAFAF7), terracotta
     accent (#E76F51), Inter font.
   - Dark mode toggle in topbar (persist to localStorage)
   - Language switcher in topbar (BM/EN)

10. **Tests**
    - Backend: feature tests for auth (register, login, logout, me)
    - Backend: model tests for Property and Unit (relationships, scopes)
    - Frontend: smoke test that login page renders

11. **GitHub Actions workflow** (.github/workflows/ci.yml)
    - On PR: lint + test backend + test frontend
    - On push to main: deploy via SSH (use existing pattern from
      portfolio-v2 — appleboy/ssh-action, secrets: SSH_HOST, SSH_USER,
      SSH_PRIVATE_KEY)

12. **Documentation**
    - Update README.md "Quick start" section with actual working commands
    - Add backend/README.md with API setup notes
    - Add frontend/README.md with frontend setup notes
    - Add docs/ADR-001-uuids.md (and stub others from PROJECT.md section 10)

## Conventions to follow strictly

- **Money:** always cents (integer). Not relevant in Phase 1 yet but set up the
  MoneyCast helper in app/Casts/MoneyCast.php for Phase 2 readiness.
- **UUIDs everywhere.**
- **Soft deletes on everything user-facing.**
- **Activity log on all domain models.**
- **PSR-12** for PHP, **Prettier + ESLint** for TS/Vue.
- **Composition API only** in Vue (`<script setup lang="ts">`).
- **No `any` types** in TypeScript.
- **Conventional commits** for every commit (feat/fix/chore/docs).
- **Sentence case** in UI strings, never Title Case.

## What I want from you (Claude Code)

1. Start by reading PROJECT.md in full so you have the full context.
2. Then propose a step-by-step execution plan and wait for my "go" before
   making any changes. I want to review the plan first.
3. After I approve, work through the plan in commits. After each major
   chunk (e.g. "Docker setup done", "Laravel installed", "auth flow done"),
   stop and let me verify.
4. Use clear, descriptive commit messages following conventional commits.
5. If you hit a decision that PROJECT.md doesn't cover, ask me before
   making it up. Don't silently choose.
6. At the end of Phase 1, give me a checklist of what works and what to
   manually verify.

## Constraints

- Don't install anything not listed above.
- Don't add any UI library beyond Tailwind + Lucide icons (no shadcn-vue,
  no PrimeVue, no Element Plus).
- Don't use Inertia. We're SPA + API.
- Don't add Telescope or Horizon yet — Phase 4+.
- Don't write Phase 2+ code, even if tempting. Stay scoped.

Read PROJECT.md now and propose the plan.
```

---

## How to use this prompt

### 1. Make sure your repo has both files
Before pasting the prompt, your local `roofly/` repo should have:

```
roofly/
├── README.md
├── PROJECT.md          ← the planning doc (next file in this delivery)
└── .github/
    └── assets/
        └── house.svg
```

Commit and push them first:
```bash
git add README.md PROJECT.md .github/
git commit -m "docs: initial README and PROJECT planning doc"
git push origin main
```

### 2. Open Claude Code in the repo

```bash
cd ~/roofly
claude
```

### 3. Paste the prompt

Paste everything inside the triple backticks above into Claude Code. It will:
1. Read `PROJECT.md`
2. Propose a step-by-step plan
3. Wait for your approval before touching files

### 4. Review the plan, then say "go"

Don't just say "yes" — skim the plan and check it matches what you actually want. If something's off, push back. Claude Code is good but PROJECT.md will be your reference doc to keep it honest.

### 5. After Phase 1 is done

Verify these work:
- [ ] `docker compose up -d --build` runs cleanly
- [ ] `docker compose exec backend php artisan test` passes
- [ ] You can hit `http://localhost:3000` and see the login page
- [ ] You can register a new owner account
- [ ] You can log in as the seeded owner and see the dashboard shell
- [ ] You can switch language between BM and EN in the topbar
- [ ] Dark mode toggle works
- [ ] Push to GitHub triggers CI and deploys (if you've set up the VPS path)

### 6. When ready for Phase 2

Make a new prompt referencing `PROJECT.md § Phase 2` and let Claude Code take it from there. The `PROJECT.md` is your anchor — every future Claude Code session starts with "read PROJECT.md first."

---

## Pro tips for the build

**Keep PROJECT.md alive.** When you make decisions during the build (brand color, agreement template wording, etc.), update PROJECT.md right then. Don't wait. Future-you will thank present-you.

**Use ADR files for big decisions.** Architecture Decision Records (`docs/ADR-XXX-decision-name.md`) are great when you make a non-obvious technical choice. Format:
```
# ADR-XXX: <decision>
## Status: Accepted | Superseded
## Context: Why we needed to decide
## Decision: What we chose
## Consequences: Trade-offs
```

**Resist Phase 2 temptation.** When you finish auth and properties, you'll want to immediately add agreements. Don't. Verify Phase 1 is rock solid first — onboarding flow tested, CI green, deployment working — then move on.

**Commit PROJECT.md changes separately** from code changes so the history stays clean.
