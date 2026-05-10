# Roofly — Claude project notes

Quick orientation for any new Claude Code session in this repo. Read this before doing anything else.

---

## What this is

**Roofly.my** — a rent-management SaaS for Malaysian landlords. Solo build, currently in the **frontend mock-first phase** (Phase 2). No backend exists yet; the entire owner shell is implemented against typed TypeScript mocks behind a single `useMock` runtime toggle, ready to swap to a Laravel + Sanctum backend per-entity when that lands.

---

## Source-of-truth docs (read these before starting any new feature)

| Doc | Purpose |
|---|---|
| [docs/global/PROJECT.md](docs/global/PROJECT.md) | Architecture, schema, phased roadmap, brand language. The canonical "what we're building". |
| [docs/frontend/MOCK-POC.md](docs/frontend/MOCK-POC.md) | The frontend mock-first plan, entity-by-entity. Section per surface (Properties, Tenants, Payments, Maintenance, Dashboard & Reports, Settings) with types, mocks, services, and **brief** schema impact for the future backend. **Frontend-first by intent — keep schema-impact subsections forward-looking, not exhaustive.** |
| [docs/frontend/UI-STANDARDS.md](docs/frontend/UI-STANDARDS.md) | Design tokens, components, layout, dark mode, mobile patterns. Section 11 (Mobile patterns) is a living section — add new responsive guidelines there. |
| [docs/global/BRANCH-PROTECTION.md](docs/global/BRANCH-PROTECTION.md) | Git workflow / merge rules. |

If a question is answered by one of these, defer to that doc and don't re-derive.

---

## Stack

- **Frontend** — Nuxt 4 + Vue 3, Pinia, vee-validate + Zod, Reka UI primitives, Tailwind v3, `@nuxtjs/i18n` (en + ms). Lives in [frontend/](frontend/).
- **Backend** — Laravel 11 + Sanctum + Spatie (Permission, MediaLibrary, ActivityLog), MySQL, Redis, RabbitMQ. **Not yet scaffolded** — slot lives in [backend/](backend/) but is empty.
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

**Tenant shell** — placeholder pages exist but not yet built out.

**Backend** — not started.

---

## Where things live

```
frontend/app/
├── types/         # entity shapes (single source of truth, post-swap stays put)
├── schemas/       # Zod (vee-validate) — shared between create modals & edit forms
├── mocks/         # in-memory seed data, only imported by services
├── services/      # the swap point — `if (useMock) ... else useApi()` per method
├── composables/   # useDashboard, useReports, useTheme, useToast, useMoney, useApi
├── components/
│   ├── ui/        # Card, Pill, Button, Input, Select, Modal, Icon, MoneyDisplay,
│   │              # MiniAreaChart, EmptyState, Toaster
│   ├── owner/     # owner-specific (PropertyCard, TenantInviteModal, TicketCard, etc.)
│   ├── tenant/    # tenant-specific (sidebar nav, etc.)
│   ├── topbar/    # ThemeToggle, LangSwitcher, UserMenu
│   └── layout/    # MobileNavDrawer
├── pages/         # routing (Nuxt file-based)
├── layouts/       # owner.vue, tenant.vue, auth.vue, default.vue
├── stores/        # auth.ts (Pinia, localStorage-backed)
├── plugins/       # theme.ts, auth-restore.client.ts
└── utils/         # rpgt.ts, propertyCompletion.ts, csv.ts
```

**Routing rule:** for tab-style detail pages, use `pages/owner/<entity>/index.vue` + `[id].vue` (NOT `<entity>.vue` + `<entity>/[id].vue` — the latter requires `<NuxtPage />` in the parent and silently fails to render the child if you forget).

---

## Locked-in conventions

- **Money is integer sen everywhere.** Format only at the render edge via `useMoney().formatRM` or `<MoneyDisplay>`. Never store formatted strings.
- **Mock toggle is single source of truth.** All services read `useEnv().useMock` inside the composable — never module-level constants. Flip per-environment via `NUXT_PUBLIC_USE_MOCK=false`. Demo (`NUXT_PUBLIC_APP_ENV=demo`) always uses mocks regardless of the flag, because `useEnv` derives `useMock = isDemo || config.public.useMock`. See `composables/useEnv.ts`.
- **Per-environment behaviour goes through `useEnv()`.** One env var (`NUXT_PUBLIC_APP_ENV` = `"demo" | "uat" | "production"`) drives all UI feature flags (`isDemo`, `showDemoShortcuts`, `showFloatingFeedback`, `showEnvBanner`, `redirectRootToDemo`, etc.). Components ask for derived flags by name, not for the raw env. Add new env-driven features as one new derived field in `composables/useEnv.ts`, not a new env var per feature.
- **Documents tab + tenant photos + reports PDF are gated** by `runtimeConfig.public.features.documents`. Currently default-on so demos signal Phase-4 file storage is coming; flip semantics will switch to gating real storage when it lands.
- **Field tiers** (Properties, Tenants): Tier 1 captured in the create modal; Tier 2/3 edited on the detail page. JSON sub-objects (`ownership`, `utilities`, `personal`, `emergencyContact`) on the model map 1:1 to detail-page tabs and to backend JSON columns.
- **Co-owners are a separate `property_co_owners` table** on the backend (DB-enforced sum=100 + exactly-one `is_primary`). On the frontend they're a top-level `Property.coOwners[]` with the same invariants validated by Zod.
- **MalaysianState enum from day one** — never `state: string`.
- **Sentence case** in all strings, BM and EN. Two font weights only (400 / 600). See [UI-STANDARDS.md § 12](docs/frontend/UI-STANDARDS.md).
- **i18n: never put a literal `@` in a translation value** — vue-i18n treats it as a linked-message marker and crashes the compiler. Avoid or escape with `{'@'}`.

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

**Mock auth credentials** (no validation, mock-only):
- Owner: any email NOT starting with `tenant`/`admin` (e.g. `aminah@roofly.my`)
- Tenant: any email starting with `tenant` (e.g. `tenant@example.com`)
- Auth persists across refresh via `localStorage["roofly_auth"]`.

---

## When uncertain

1. Check the source-of-truth docs first.
2. Search the codebase for an existing pattern before inventing one.
3. If the user asks for a frontend behaviour, default to mock-first and document any future backend implication in the relevant MOCK-POC.md section's "Schema impact" subsection — keep it brief.
4. For any new mobile or design pattern, capture it in UI-STANDARDS § 11 before / alongside implementation, so the next session has it.
