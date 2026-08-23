# Roofly — Claude project notes

Quick orientation for any new Claude Code session in this repo. Read this before doing anything else.

---

## What this is

**Roofly.my** — a rent-management SaaS for Malaysian landlords. Solo build. The Nuxt frontend runs against either the **Laravel + Sanctum API** (UAT/prod) or a self-contained **demo layer** (`app/demo/` — in-memory seed data, never touches the network), selected once per service by `useEnv().useMock`. Demo is also the live prototype surface: new features are built demo-adapter-first, shipped to UAT behind a `features.*` flag, and get their API adapter when the backend catches up. Design: [docs/superpowers/specs/2026-08-23-demo-adapter-split-design.md](docs/superpowers/specs/2026-08-23-demo-adapter-split-design.md).

---

## Source-of-truth docs (read these before starting any new feature)

| Doc | Purpose |
|---|---|
| [docs/global/PROJECT.md](docs/global/PROJECT.md) | Architecture, schema, phased roadmap, brand language. The canonical "what we're building". |
| [docs/frontend/MOCK-POC.md](docs/frontend/MOCK-POC.md) | The frontend mock-first plan, entity-by-entity. Section per surface (Properties, Tenants, Payments, Maintenance, Dashboard & Reports, Settings) with types, mocks, services, and **brief** schema impact for the future backend. **Frontend-first by intent — keep schema-impact subsections forward-looking, not exhaustive.** |
| [docs/frontend/UI-STANDARDS.md](docs/frontend/UI-STANDARDS.md) | Design tokens, components, layout, dark mode, mobile patterns. Section 11 (Mobile patterns) is a living section — add new responsive guidelines there. |
| [docs/global/BRANCH-PROTECTION.md](docs/global/BRANCH-PROTECTION.md) | Git workflow / merge rules. |
| [docs/backend/API-SPEC.md](docs/backend/API-SPEC.md) | Backend contract per shell → module → endpoint (middleware, request rules, response shapes, audit actions, stubs). Update whenever `routes/api.php`, a FormRequest, or a Resource changes. |
| [docs/frontend/API-MAP.md](docs/frontend/API-MAP.md) | Per shell → page → service method → endpoint, with the demo-adapter equivalent. Update with every new contract method. |


If a question is answered by one of these, defer to that doc and don't re-derive.

---

## Stack

- **Frontend** — Nuxt 4 + Vue 3, Pinia, vee-validate + Zod, Reka UI primitives, Tailwind v3, `@nuxtjs/i18n` (en + ms). Lives in [frontend/](frontend/).
- **Backend** — Laravel 13 + Sanctum + Spatie (Permission, MediaLibrary, ActivityLog), MySQL, Redis, RabbitMQ. Lives in [backend/](backend/); serves the frontend's camelCase contract via API Resources + FormRequests (design: [docs/superpowers/specs/2026-07-21-backend-api-contract-alignment-design.md](docs/superpowers/specs/2026-07-21-backend-api-contract-alignment-design.md)). Tests: `docker exec roofly-backend php artisan test` (sqlite in-memory).
- **Dev** — Docker Compose ([docker-compose.yml](docker-compose.yml)). Frontend container is `roofly-frontend`, exposes :3000 and HMRs against the host.

---

## Current state

**Owner shell — complete in mock form:**
- Dashboard ([pages/owner/index.vue](frontend/app/pages/owner/index.vue)) — 4 stat tiles, 12-month income area chart, "Needs attention" feed combining overdue invoices + expiring agreements + notice-given tenants + new-urgent + reopened tickets.
- Properties ([pages/owner/properties/](frontend/app/pages/owner/properties/)) — list + detail with 5-tab structure (Overview, Details, Ownership, Utilities, Documents), nested UnitsPanel, co-owner repeater with sum=100 + single-primary invariants.
- Tenants ([pages/owner/tenants/](frontend/app/pages/owner/tenants/)) — list + 3-tab detail (Identity, Personal, Emergency contact). 4-state status enum incl. `notice_given`.
- Agreements ([pages/owner/agreements/](frontend/app/pages/owner/agreements/)) — list ([index.vue](frontend/app/pages/owner/agreements/index.vue)) + create ([new.vue](frontend/app/pages/owner/agreements/new.vue)) + 3-tab detail ([[id].vue](frontend/app/pages/owner/agreements/[id].vue): Overview, Terms, Documents). Documents tab gated by `features.documents` and shows the legal-section slot list (signed lease, addendums, inspection, inventory, exit letter) with a Phase-4 upload placeholder.
- Payments ([pages/owner/payments.vue](frontend/app/pages/owner/payments.vue)) — TanStack Table, status pills + month/year filters, record-payment + invoice-view modals, CSV-friendly.
- Maintenance ([pages/owner/maintenance/](frontend/app/pages/owner/maintenance/)) — Kanban (4 columns: New / In progress / Resolved / Reopened) + detail page with comment thread + status transitions + Phase-4 photo stub.
- Reports ([pages/owner/reports.vue](frontend/app/pages/owner/reports.vue)) — year picker, monthly area chart, per-property breakdown with RPGT net gain, working CSV download + Phase-4 PDF stub.
- Settings ([pages/owner/settings.vue](frontend/app/pages/owner/settings.vue)) — 4-tab (Profile, Preferences, Notifications, Plan).

**Tenant shell — complete in mock form (5 surfaces):**
- Home ([pages/tenant/index.vue](frontend/app/pages/tenant/index.vue)) — rent-due hero (earliest unpaid invoice + Pay now), 4 stat tiles (rent / deposit / tenancy-ends / open-issues), "Your home" property card + quick actions, open-issues preview.
- Agreement ([pages/tenant/agreement.vue](frontend/app/pages/tenant/agreement.vue)) — read-only term/money summary + Documents card (reuses owner `AgreementDocumentsPanel`, gated by `features.documents`).
- Payments ([pages/tenant/payments.vue](frontend/app/pages/tenant/payments.vue)) — outstanding summary + invoice cards; `PayInvoiceModal` simulates an FPX pay→paid round-trip (mock) and doubles as the receipt view.
- Issues ([pages/tenant/tickets/](frontend/app/pages/tenant/tickets/)) — list ([index.vue](frontend/app/pages/tenant/tickets/index.vue)) + detail ([[id].vue](frontend/app/pages/tenant/tickets/[id].vue)) with comment thread (tenant comments; status is owner-controlled / read-only here) + `ReportIssueModal` (files against the tenant's own unit).
- Profile ([pages/tenant/profile.vue](frontend/app/pages/tenant/profile.vue)) — view + single-form edit of Identity / Personal / Emergency contact.

[composables/useTenantSession.ts](frontend/app/composables/useTenantSession.ts) resolves the current tenant id — `DEMO_TENANT_ID` (Aminah) in demo, `auth.user.id` against the API. Tenant-scoped service methods are the `…ForTenant` / `getProfile` / `updateProfile` ones on the contracts; they map to `/me/*` in the API adapter and ignore the id there. The "Continue as tenant" demo shortcut is now enabled (`TENANT_ENABLED` in `DemoLoginShortcuts.vue`).

**Backend** — Laravel 13, contract-aligned to the frontend types (Phase 1), owner shell wired to it with Sanctum cookie auth, CSRF/401/422 handling, and a global auth/role route guard (Phase 2). `DemoSeeder` mirrors the frontend demo data. Tenant shell is wired end-to-end too: reads via `/me/agreement|invoices|tickets`, writes via `payForTenant`, `createForTenant`, `addCommentForTenant`, `getProfile`/`updateProfile` (`/me/*`), all in both adapters. Tenant email is read-only on the profile (login identity).

**Admin shell — complete in mock + API form (5 surfaces):** Dashboard ([pages/admin/index.vue](frontend/app/pages/admin/index.vue)) — stat tiles + attention list. Owners ([pages/admin/owners/](frontend/app/pages/admin/owners/)) — list + detail (summary counts only, never money). Tenants ([pages/admin/tenants/](frontend/app/pages/admin/tenants/)) — list + detail. Settings → Admins ([pages/admin/settings.vue](frontend/app/pages/admin/settings.vue)) — invite/edit admin users against `App\Support\AdminPermissions` (13 keys + an Operations preset). Audit ([pages/admin/audit.vue](frontend/app/pages/admin/audit.vue)) — reads `AuditLogger`-written ActivityLog entries (`log_name = admin`). Auth is separate from owner/tenant: `/admin/login` + `/admin/accept-invite`, backed by `layouts/admin.vue` + `layouts/auth-admin.vue`. Gated by `useEnv().features.admin` (env `NUXT_PUBLIC_FEATURE_ADMIN`) — always off in demo, so `demo-roofly` never shows it. Demo admin logins: `admin@roofly.my` (super-admin, all permissions) / `ops@roofly.my` (Operations preset), both password `password`.

---

## Where things live

```
frontend/app/
├── types/         # entity shapes (single source of truth, post-swap stays put)
├── schemas/       # Zod (vee-validate) — shared between create modals & edit forms
├── demo/          # demo-only — NEVER imports useApi
│   ├── auth.ts    #   demoAuth (localStorage session, email prefix → role), DEMO_TENANT_ID
│   ├── data/      #   in-memory seed arrays (propertiesMock, unitsMock, …), admin.ts
│   └── services/  #   demoX: XService — one per entity + dashboard, admin/ subfolder
├── services/
│   ├── contracts/ # XService interfaces + *WithRefs types (both adapters implement these); admin/ subfolder
│   ├── api/       # apiX: XService — Laravel calls via useApi(); NEVER imports ~/demo; admin/ subfolder (+ query.ts helper)
│   └── useX.ts    # auto-imported selector: useEnv().useMock ? demoX : apiX (+ type re-exports)
├── composables/   # useDashboard, useReports, useTheme, useToast, useMoney, useApi, useApiError, useAdminPermissions, useAdminDashboardData
├── components/
│   ├── ui/        # Card, Pill, Button, Input, Select, Modal, Icon, MoneyDisplay,
│   │              # MiniAreaChart, EmptyState, Toaster
│   ├── owner/     # owner-specific (PropertyCard, TenantInviteModal, TicketCard, etc.)
│   ├── tenant/    # tenant-specific (sidebar nav, etc.)
│   ├── admin/     # admin-specific (SidebarNav, StatTile, DataTableShell, AuditTable, WarnOwnerModal, SuspendOwnerModal, AdminFormModal, etc.)
│   ├── topbar/    # ThemeToggle, LangSwitcher, UserMenu
│   └── layout/    # MobileNavDrawer
├── pages/         # routing (Nuxt file-based)
├── layouts/       # owner.vue, tenant.vue, admin.vue, auth.vue, auth-admin.vue, default.vue
├── stores/        # auth.ts (Pinia; delegates to demoAuth / apiAuth)
├── plugins/       # theme.ts, auth-restore.client.ts
├── middleware/    # env.global.ts (renamed from the old demo-only middleware — now also drives the admin-host redirect), auth.global.ts
└── utils/         # rpgt.ts, propertyCompletion.ts, csv.ts, warningText.ts
```

**Routing rule:** for tab-style detail pages, use `pages/owner/<entity>/index.vue` + `[id].vue` (NOT `<entity>.vue` + `<entity>/[id].vue` — the latter requires `<NuxtPage />` in the parent and silently fails to render the child if you forget).

---

## Locked-in conventions

- **Git flow: feature → `UAT` → `main`. Never feature → `main`.** All `gh pr create` calls in this repo use `--base UAT`. The only `--base main` PR is a release promotion with `--head UAT`. Enforced by [.github/workflows/guard-main.yml](.github/workflows/guard-main.yml) (required check on `protect-main`). Full rules in [docs/global/BRANCH-PROTECTION.md](docs/global/BRANCH-PROTECTION.md).
- **Money is integer sen everywhere.** Format only at the render edge via `useMoney().formatRM` or `<MoneyDisplay>`. Never store formatted strings.
- **Mock toggle is single source of truth.** Every `services/useX.ts` selector reads `useEnv().useMock` at call time — never module-level constants. Flip per-environment via `NUXT_PUBLIC_USE_MOCK=false`. Demo (`NUXT_PUBLIC_APP_ENV=demo`) always uses the demo layer regardless of the flag, because `useEnv` derives `useMock = isDemo || config.public.useMock`. See `composables/useEnv.ts`.
- **Demo and API are separate adapters, not branches.** `app/demo/**` never imports `useApi`; `services/api/**` never imports `~/demo`; pages/components only import `services/useX`. Never write `if (useMock)` inside a method — add the method to the contract and implement it in both adapters (TypeScript enforces parity). New feature recipe: contract → demo adapter (full) → API adapter (stub throwing `Not implemented` until the backend lands) → UI behind a `features.*` flag in `useEnv`. `demo-roofly` never gets feature commits of its own; it only merges UAT.
- **Per-environment behaviour goes through `useEnv()`.** One env var (`NUXT_PUBLIC_APP_ENV` = `"demo" | "uat" | "production"`) drives all UI feature flags (`isDemo`, `showDemoShortcuts`, `showFloatingFeedback`, `showEnvBanner`, `redirectRootToDemo`, etc.). Components ask for derived flags by name, not for the raw env. Add new env-driven features as one new derived field in `composables/useEnv.ts`, not a new env var per feature.
- **Documents tab + tenant photos + reports PDF are gated** by `runtimeConfig.public.features.documents`. Currently default-on so demos signal Phase-4 file storage is coming; flip semantics will switch to gating real storage when it lands.
- **Field tiers** (Properties, Tenants): Tier 1 captured in the create modal; Tier 2/3 edited on the detail page. JSON sub-objects (`ownership`, `utilities`, `personal`, `emergencyContact`) on the model map 1:1 to detail-page tabs and to backend JSON columns.
- **Co-owners are a separate `property_co_owners` table** on the backend (DB-enforced sum=100 + exactly-one `is_primary`). On the frontend they're a top-level `Property.coOwners[]` with the same invariants validated by Zod.
- **MalaysianState enum from day one** — never `state: string`.
- **Sentence case** in all strings, BM and EN. **Exception: the admin shell is English-only** — `admin.*` and `auth.admin.*` keys live in `en.json` only, `layouts/admin.vue` / `auth-admin.vue` pin the locale to `en` and hide the language switcher. Two font weights only (400 / 600). See [UI-STANDARDS.md § 12](docs/frontend/UI-STANDARDS.md).
- **i18n: never put a literal `@` in a translation value** — vue-i18n treats it as a linked-message marker and crashes the compiler. Avoid or escape with `{'@'}`.
- **Admin sees summaries only** — `AdminResourcesTest` (backend) pins the key sets on every admin API Resource. Widen deliberately, never by adding a field to a Resource without updating that test first (money and PII stay out of admin owner/tenant list+detail responses).

---

## Mobile patterns

Captured in [UI-STANDARDS.md § 11](docs/frontend/UI-STANDARDS.md). Highlights:

- Tab strips → `<Select>` dropdown on mobile (`<sm`), tab strip from `sm:` up. **Use `v-model="activeTab"` on `<TabsRoot>`** — reka-ui binds `modelValue`, not `value`.
- Card-row layout (Needs Attention / activity feeds): pill + meta on top row, primary message in `text-body font-medium` below. Same on mobile and desktop.
- Section headers with primary action: stack on mobile (`flex-col gap-3`), inline on `sm:flex-row sm:items-start sm:justify-between`. Action uses `self-start` so it doesn't stretch.
- Topbar collapses on mobile: theme + language move into UserMenu dropdown, gated by `md:hidden` inside the dropdown and `hidden md:inline-flex` on the topbar wrapper.
- Tighten margins at narrow widths: `mb-8 → mb-6 sm:mb-8`, `gap-6 → gap-4 sm:gap-6` for major sections / stat grids. Card padding levels stay fixed.

When adding any new mobile behaviour, add it to UI-STANDARDS § 11 first, then implement.

---

## Common gotchas

- **Tailwind opacity modifier silently no-ops** on CSS-variable tokens defined as hex literals (`--text-muted: #5f5f5d`, `--text-primary: #1c1c1c`, etc.). `bg-ink-muted/40` produces *no* background. Use solid tokens for backgrounds: `bg-line-passive`, `bg-ink-muted`, `bg-ink-strong`, `bg-ink`. The exception is `--surface-hover` which is already an rgba — `bg-surface-hover` works.
- **Docker volume isolation:** the frontend container has its own `node_modules` volume. `npm install` on the host doesn't reach the container. Run installs / typechecks inside: `docker exec roofly-frontend npm run typecheck`. Restart the container if you need a fresh install.
- **Typecheck has 5 known pre-existing errors** unrelated to current work: `InvoiceViewModal.vue` Tone narrowing, `payments.vue` Tone + possibly-undefined, `Icon.vue` + `EmptyState.vue` lucide-vue-next IconProps shape skew. Don't try to "fix" these without scoping — they predate the current work.
- **Reka UI `<TabsRoot>`** uses `modelValue` (not `value`). `v-model:value="x"` silently fails. Use `v-model="x"`.
- **Routing with parent-page collision:** `pages/foo.vue` + `pages/foo/[id].vue` makes Nuxt expect `<NuxtPage />` inside `foo.vue`. Use `pages/foo/index.vue` + `pages/foo/[id].vue` instead.
- **`@` in vue-i18n strings** breaks the message compiler (linked-message marker). Escape with `{'@'}` or rephrase.

---

## How to run

```bash
# from repo root
docker compose up -d              # boots frontend (and the empty backend slot)
open http://localhost:3000        # owner login lands at /auth/login

# typecheck
docker exec roofly-frontend npm run typecheck

# install a new package (must run inside the container)
docker exec roofly-frontend npm install <pkg>

# tail dev logs
docker logs -f roofly-frontend
```

**API-mode credentials** (from `DemoSeeder`, all password `password`): owner `aminah@roofly.my`; tenants `aminah.yusof@example.com` (the richest record — active agreement, invoices, tickets), `arif.hakim@example.com`, `limlw@example.com`, `ravik@example.com`, `siti.khadijah@example.com`. There is no `tenant@roofly.my` in the DB.

**Demo auth credentials** (demo mode only — `app/demo/auth.ts`):
- Owner: any email NOT starting with `tenant`/`admin`/`ops` (e.g. `aminah@roofly.my`)
- Tenant: any email starting with `tenant` (e.g. `tenant@example.com`)
- `admin@…` / `ops@…` sign in at `/admin/login` only (`loginAdmin`, not the customer form — spec § 4).
- Auth persists across refresh via `localStorage["roofly_auth"]`.

**Admin credentials** (API mode, from `DemoSeeder`, password `password`, sign in at `/admin/login`): `admin@roofly.my` (super-admin) and `ops@roofly.my` (Operations preset). Admin is gated by `features.admin` and is always off in demo — there's no demo-mode admin login.

---

## When uncertain

1. Check the source-of-truth docs first.
2. Search the codebase for an existing pattern before inventing one.
3. If the user asks for a frontend behaviour, default to mock-first and document any future backend implication in the relevant MOCK-POC.md section's "Schema impact" subsection — keep it brief.
4. For any new mobile or design pattern, capture it in UI-STANDARDS § 11 before / alongside implementation, so the next session has it.
