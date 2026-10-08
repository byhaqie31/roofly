# Demo / API adapter split — design

**Date:** 2026-08-23
**Scope:** Frontend only. Separate every demo (in-memory) implementation from every API implementation so that demo code lives in one folder, never imports `useApi`, and can never reach the Laravel backend or its database. Zero behaviour change in either mode.
**Out of scope:** the tenant-shell §6 service gaps (follow-up, written *in* this pattern), bundle-level exclusion of `demo/` from UAT/prod builds (noted as a follow-up in §7), backend changes.
**Depends on:** `docs/superpowers/specs/2026-07-22-frontend-backend-integration-design.md` (Phase 2 — the API paths this refactor preserves).

---

## 1. Problem

Every service (`services/use*.ts`) imports `~/mocks/*` directly and branches `if (useMock)` inside each method — 43 branches across 7 services, `useDashboard`, and `useTenantSession`. Consequences:

- The "demo never touches the DB" guarantee is enforced per method, not structurally. Adding a method to one side and forgetting the other is silent.
- Mock seed data ships in every bundle, including production.
- Phase 2 (`40a566f`) replaced the mock auth store with real Sanctum calls and left **no demo path at all** — `demo-roofly` currently cannot log in, and the boot plugin's `fetchMe()` hits the API on every page load.
- Prototyping a feature "in demo only" (the agreed workflow: build the demo adapter first, ship to UAT behind a flag, implement the API adapter when the backend lands) has no natural place to live.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Selection point | One per composable, not per method: `useX = () => useEnv().useMock ? demoX : apiX`. `useEnv().useMock` stays the single toggle; demo always wins. |
| Contract | One TypeScript interface per service in `services/contracts/<entity>.ts`. Both adapters are typed against it, so TypeScript forces parity. |
| Demo folder | `app/demo/` — `data/` (the current `mocks/`, moved with `git mv`), `services/` (demo adapters), `auth.ts` (demo auth adapter + `DEMO_TENANT_ID`). Nothing under `demo/` imports `useApi`. |
| API folder | `services/api/<entity>.ts`. Nothing under `services/api/` imports `demo/`. |
| Import direction | `demo/services/*` → `demo/data/*` + `types/*` + `services/contracts/*`. `services/api/*` → `composables/useApi` + `types/*` + `services/contracts/*`. `services/use*.ts` → both adapters + `useEnv`. Pages/components import only `services/use*.ts` (unchanged). |
| Auto-imports | `nuxt.config.ts` `imports.dirs: ["services"]` scans top level only, so `services/api/*` and `services/contracts/*` are **not** auto-imported — adapters are imported explicitly. Unchanged config. |
| Adapter shape | Plain objects (`export const demoTickets: TicketsService = { … }`). API adapters call `useApi()` lazily inside each method (today's behaviour — `useApi` needs Nuxt context at call time, not module load). |
| Public names | `useProperties`, `useUnits`, `useTenants`, `useAgreements`, `useInvoices`, `useTickets`, `useOwnerSettings`, `useDashboard`, `useTenantSession`, `useAuthStore` keep their current method names and return shapes. `*WithRefs` types move to `services/contracts/*` and are re-exported from `services/use*.ts` so the 20 existing `import type { XWithRefs } from "~/services/useX"` lines keep working. |
| Auth | `AuthUser`/`UserRole` move to `types/auth.ts` (re-exported from the store). `services/contracts/auth.ts` defines `AuthAdapter { login, register, logout, fetchMe }`. `services/api/auth.ts` holds today's Sanctum bodies verbatim. `demo/auth.ts` restores the pre-Phase-2 mock: email prefix decides role, tenant logs in as Aminah (`t-aminah`), owner as `stub-owner`, persisted in `localStorage["roofly_auth"]` so a demo refresh keeps the session (no cookie exists in demo). The store keeps its state/getters and becomes a thin delegator. |
| Demo auth boot | `fetchMe()` in demo reads localStorage, never the network. `authReady` semantics unchanged, so `auth.global.ts` and the boot plugin need no edits. |
| Dashboard | `buildFromMocks` moves to `demo/services/dashboard.ts`; the `GET /dashboard` call to `services/api/dashboard.ts`. `DashboardData`/`DashboardStats`/`AttentionItem`/`MonthlyBucket` types move to `services/contracts/dashboard.ts` and are re-exported from `useDashboard` (`useReports` imports `MonthlyBucket` from there). |
| Tenant session | Unchanged logic; `DEMO_TENANT_ID` is imported from `demo/auth.ts` instead of being a local constant, so the demo login and the tenant binding cannot drift. |
| Seed-data mutation | Demo adapters keep mutating the in-memory arrays exactly as today (push/splice/index assignment + `structuredClone` on the way out). No new store. |

## 3. Resulting tree

```
frontend/app/
├── demo/                          # demo-only — never imports useApi
│   ├── auth.ts                    # demoAuth: AuthAdapter, DEMO_TENANT_ID
│   ├── data/                      # ← mocks/ (git mv), same exports
│   │   ├── agreements.ts  invoices.ts  owner.ts  properties.ts
│   │   ├── tenants.ts  tickets.ts  units.ts
│   └── services/                  # demoX: XService
│       ├── agreements.ts  dashboard.ts  invoices.ts  ownerSettings.ts
│       ├── properties.ts  tenants.ts  tickets.ts  units.ts
├── services/
│   ├── contracts/                 # XService interfaces + *WithRefs types
│   │   ├── agreements.ts  auth.ts  dashboard.ts  invoices.ts
│   │   ├── ownerSettings.ts  properties.ts  tenants.ts  tickets.ts  units.ts
│   ├── api/                       # apiX: XService — only imports useApi
│   │   ├── agreements.ts  auth.ts  dashboard.ts  invoices.ts
│   │   ├── ownerSettings.ts  properties.ts  tenants.ts  tickets.ts  units.ts
│   ├── useAgreements.ts           # selector + type re-exports (auto-imported)
│   ├── useInvoices.ts  useOwnerSettings.ts  useProperties.ts
│   ├── useTenants.ts  useTickets.ts  useUnits.ts
├── composables/useDashboard.ts    # selector + computed state
├── composables/useTenantSession.ts
├── stores/auth.ts                 # state/getters + adapter delegation
└── types/auth.ts                  # AuthUser, UserRole
```

`mocks/` is deleted.

## 4. Contracts

Method names and signatures are today's, unchanged. `ownerSettings` keeps `getAccount/updateProfile/updatePreferences/updateNotifications/getPlans`. The full interfaces are in the plan; the only *new* surface is:

```ts
// services/contracts/auth.ts
export interface RegisterPayload { name: string; email: string; phone: string; password: string }
export interface AuthAdapter {
  login(email: string, password: string): Promise<AuthUser>;
  register(payload: RegisterPayload): Promise<AuthUser>;
  logout(): Promise<void>;
  /** Boot hydration. Resolves null when not signed in — never throws for that case. */
  fetchMe(): Promise<AuthUser | null>;
}

// services/contracts/dashboard.ts
export interface DashboardService { getDashboard(): Promise<DashboardData> }
```

## 5. Prototyping workflow this enables (for the record)

New feature = contract + demo adapter (full) + API adapter (stub throwing `new Error("Not implemented: <method>")`) + UI behind a `features.<name>` flag in `useEnv`. Ships to UAT inert, goes live on `demo-roofly` via the normal sync, API adapter lands later. `demo-roofly` itself never gets feature commits.

## 6. Testing

No frontend test suite exists; the gate is `docker exec roofly-frontend npm run typecheck`, which currently crashes (npx-cached `vue-tsc` vs local `typescript` 5.9). Adding `vue-tsc` as a devDependency fixes it; expect the 5 known pre-existing errors (`InvoiceViewModal`, `payments.vue` ×2, `Icon`, `EmptyState`) and zero new ones after every task. Final check is a manual smoke in both modes: demo (`NUXT_PUBLIC_APP_ENV=demo`) login + one read + one write per surface, and API mode (`uat`/`false`) owner login + dashboard.

## 7. Follow-ups (not this phase)

- Bundle exclusion: a Nuxt alias (`#demo` → `app/demo` when `appEnv === "demo"`, → an empty stub otherwise) so seed data tree-shakes out of UAT/prod. Cheap once this split exists.
- Lint rule (`no-restricted-imports`) to mechanically forbid `demo/` ← `useApi` and `services/api/` ← `demo/`.
- ~~Tenant-shell §6 methods, written directly in this pattern.~~ Done the same day, in this pattern: `InvoicesService.payForTenant`, `TicketsService.getTicketWithRefsForTenant/createForTenant/addCommentForTenant`, `TenantsService.getProfile/updateProfile` (+ `TenantProfile`/`TenantProfileUpdate` types). Tenant email is read-only on the profile because `PATCH /me/profile` does not accept it.
