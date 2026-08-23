# Frontend → API map

How the Nuxt frontend consumes the Laravel API, organised per shell → per page → service methods → endpoint, with the demo-adapter equivalent for each. Cross-link: [docs/backend/API-SPEC.md](../backend/API-SPEC.md) — the backend contract this frontend targets.

---

## Conventions

- **Adapter split is structural, not a runtime branch.** `frontend/app/demo/**` never imports `useApi`; `frontend/app/services/api/**` never imports anything under `~/demo`. Pages and components only ever call the `services/useX.ts` selector — never `demoX`/`apiX` directly, and never `if (useMock)` inline.
- **Selector pattern** — every `services/useX.ts` is a one-line function: `export const useX = (): XService => useEnv().useMock ? demoX : apiX;`. The check happens **at call time**, every call, not once at module load — so a single page load can't get half-demo/half-API state.
- **`useEnv().useMock`** = `isDemo || config.public.useMock` (`frontend/app/composables/useEnv.ts`). `NUXT_PUBLIC_APP_ENV=demo` always forces mock regardless of `NUXT_PUBLIC_USE_MOCK`; UAT/production follow the `NUXT_PUBLIC_USE_MOCK` env var, which is meant to flip off per-service as each backend module lands (today it's an all-or-nothing flag — there's no per-service override).
- **`features.admin`** is computed as `!isDemo && config.public.features.admin` — the admin shell can never render in the demo build regardless of the runtime flag, so `demo-roofly` never exposes the back office.
- **`useApi()`** (`composables/useApi.ts`) is the one `$fetch` wrapper every `services/api/**` file uses. It sends `credentials: "include"`, attaches `X-XSRF-TOKEN` read from the `XSRF-TOKEN` cookie Sanctum sets after `/sanctum/csrf-cookie`, and centralizes response handling:
  - `403 {code: "account_suspended"}` → `navigateTo("/suspended")`, auth state left intact.
  - `401` on any URL **other than** `/auth/me`, `/auth/login`, `/admin/auth/*` (which are allowed to 401 as a normal outcome — boot probe / failed login) → clears `auth.user`, redirects to `/admin/login` if the caller was an admin (or the URL contains `/admin/`), else `/auth/login`.
  - `422` is left to throw as-is (`FetchError.data = {message, errors}`); `useApiError().toFieldErrors()` maps it to vee-validate's `setErrors` shape for the calling form.
- **Demo adapters** (`demo/services/**`) mutate the in-memory seed arrays under `demo/data/**` directly (`push`, `splice`, in-place field assignment) and return `structuredClone()`d copies so callers can't mutate the store by reference. There is no persistence beyond the page session except auth (`demoAuth` persists the signed-in user to `localStorage["roofly_auth"]` — see `demo/auth.ts`); a hard refresh resets all other demo data to its seed state.
- **Two dashboard/report demo builders are hand-mirrored, not shared**, and must be kept in lock-step manually with their backend counterparts: `demo/services/dashboard.ts` mirrors `Owner\DashboardController`, and `demo/services/admin/dashboard.ts` mirrors `Admin\DashboardController`. Both say so in their own docblocks.
- ⚠ **The owner Reports page never calls the backend `/reports/*` endpoints at all.** `composables/useReports.ts` builds every figure (income, outstanding, per-property RPGT breakdown, CSV) client-side from `useProperties/useUnits/useTenants/useAgreements/useInvoices().getInvoicesWithRefs()` — plain list fetches, aggregated in JS. `Owner\ReportController` (`GET /reports/dashboard`, `/reports/yearly/{year}`, `/reports/yearly/{year}/export`) appears to be dead code from the frontend's perspective today; confirm with the owner whether it's meant for a future consumer (mobile? export job?) or should be considered unused.
- ⚠ **`PropertyCoOwnerController`'s dedicated REST endpoints are also unused by the frontend.** Co-owner edits go through `useProperties().update(id, {coOwners: [...]})` (`PUT /properties/{id}`, handled by `UpdatePropertyRequest.toCoOwnerRows()`), not `GET/POST/PUT/DELETE /properties/{property}/co-owners`. There's no `services/contracts/coOwners.ts` at all.

### Contracts → selector → demo adapter → API adapter

| Contract | Selector | Demo adapter | API adapter |
|---|---|---|---|
| `AuthAdapter` | `useAuthStore()` (Pinia — `adapter()` internal, not a `useX`) | `demo/auth.ts` | `services/api/auth.ts` |
| `PropertiesService` | `useProperties()` | `demo/services/properties.ts` | `services/api/properties.ts` |
| `UnitsService` | `useUnits()` | `demo/services/units.ts` | `services/api/units.ts` |
| `TenantsService` | `useTenants()` | `demo/services/tenants.ts` | `services/api/tenants.ts` |
| `AgreementsService` | `useAgreements()` | `demo/services/agreements.ts` | `services/api/agreements.ts` |
| `InvoicesService` | `useInvoices()` | `demo/services/invoices.ts` | `services/api/invoices.ts` |
| `TicketsService` | `useTickets()` | `demo/services/tickets.ts` | `services/api/tickets.ts` |
| `OwnerSettingsService` | `useOwnerSettings()` | `demo/services/ownerSettings.ts` | `services/api/ownerSettings.ts` |
| `DashboardService` | (inlined in `composables/useDashboard.ts`, not its own `useX`) | `demo/services/dashboard.ts` | `services/api/dashboard.ts` |
| `AdminOwnersService` | `useAdminOwners()` | `demo/services/admin/owners.ts` | `services/api/admin/owners.ts` |
| `AdminTenantsService` | `useAdminTenants()` | `demo/services/admin/tenants.ts` | `services/api/admin/tenants.ts` |
| `AdminAdminsService` | `useAdminAdmins()` | `demo/services/admin/admins.ts` | `services/api/admin/admins.ts` |
| `AdminAuditService` | `useAdminAudit()` | `demo/services/admin/audit.ts` | `services/api/admin/audit.ts` |
| `AdminDashboardService` | `useAdminDashboard()` | `demo/services/admin/dashboard.ts` | `services/api/admin/dashboard.ts` |
| `AdminAnalyticsService` | `useAdminAnalytics()` | `demo/services/admin/analytics.ts` | `services/api/admin/analytics.ts` |

`services/api/admin/query.ts` (`cleanQuery`) strips `undefined`/`""`/`false` query params and coerces `true` → `1` before every admin list/audit/export call — shared by all three paginated admin API adapters.

---

## Shell — Public / Auth

| Page | Route | Layout | Calls | Demo behaviour |
|---|---|---|---|---|
| Root redirect | `/` | `false` (no chrome) | `useAuthStore()` state only, no fetch | Redirects to `/auth/login`, `/admin`, `/owner`, or `/tenant` based on `auth.isAdmin/isOwner/isTenant` — same on both adapters, decided from whatever `fetchMe()` already hydrated at boot. |
| Login | `/auth/login` | `auth` | `auth.login(email, password)` → `AuthAdapter.login` | `demoAuth.login`: rejects if the email is an admin-shaped email (`admin*`/`ops*` prefix — "the customer form is never a back door"); otherwise resolves a fixed owner or tenant record based on whether the email starts with `tenant`, after a 300ms fake delay, and persists to `localStorage`. |
| API: `POST /../sanctum/csrf-cookie` then `POST /auth/login` `{email, password}` → `{user, token}` (token unused by the SPA cookie flow but returned). |
| Register | `/auth/register` | `auth` | `auth.register(payload)` → `AuthAdapter.register` | Demo: fabricates an owner record from the form fields, no uniqueness check. API: `POST /../sanctum/csrf-cookie` then `POST /auth/register` `{name, email, phone, password, password_confirmation}` → `{user, token}`. `422` mapped to field errors via `useApiError()`. |
| Demo shortcuts | `/demo` | `auth` | `auth.login(...)` with prefilled demo credentials (owner/tenant) | Demo-only page; not meaningfully present on the API build (`TENANT_ENABLED` shortcut lives in `DemoLoginShortcuts.vue`, referenced from the CLAUDE.md project notes). |
| Suspended notice | `/suspended` | `false` | `auth.logout()` on the one button | No fetch on load — arrived at via the `useApi()` 403 `account_suspended` redirect. `AuthAdapter.logout` → demo: clears `localStorage`; API: `POST /auth/logout`. |
| Admin login | `/admin/login` | `auth-admin` | `auth.loginAdmin(email, password)` → `AuthAdapter.loginAdmin` | Demo: resolves a super-admin or ops-admin fixed record if the email starts with `admin`/`ops`, else throws. API: `POST /../sanctum/csrf-cookie` then `POST /admin/auth/login` → `{user}` (no token — cookie session). |
| Admin accept invite | `/admin/accept-invite` | `auth-admin` | `auth.acceptAdminInvite(token, password)` → `AuthAdapter.acceptAdminInvite` | Demo: ignores the token, returns a fixed ops-admin. API: `POST /../sanctum/csrf-cookie` then `POST /admin/auth/accept-invite` `{token, password, password_confirmation}` → `{user}`. |

Every layout's route guard depends on `auth.fetchMe()` (`AuthAdapter.fetchMe`) having run at boot (`authReady`); demo restores from `localStorage`, API calls `GET /auth/me` and swallows a 401 into `null`.

---

## Shell — Owner (`layout: "owner"`, all under `/owner/*`)

| Page | Route | Contract calls | API endpoint(s) | Demo behaviour |
|---|---|---|---|---|
| Dashboard | `/owner` (`pages/owner/index.vue`) | `useDashboard().getDashboard()` (composable wraps `DashboardService`) | `GET /dashboard` | `demo/services/dashboard.ts` computes the same `{isEmpty, stats, incomeSeries, needsAttention}` shape from `propertiesMock/unitsMock/tenantsMock/agreementsMock/invoicesMock/paymentsMock/ticketsMock` in JS — hand-mirrored, see Conventions. |
| Properties list | `/owner/properties` | `useProperties().getProperties()`; create via `AddPropertyModal` → `useProperties().create(input)` | `GET /properties`; `POST /properties` | `demo/services/properties.ts`: `getProperties` clones `propertiesMock`; `create` pushes a new row and auto-injects a 100%/primary co-owner client-side (mirrors the backend's auto co-owner-on-create behaviour). |
| Property detail | `/owner/properties/[id]` | `useProperties().getProperty(id)`, `.update(id, patch)` (tabs: Details/Ownership/Utilities via `PropertyDetailsForm`/`PropertyOwnershipForm`/`PropertyUtilitiesForm`, all call `.update`), `.remove(id)`; nested `UnitsPanel` → `useUnits().getUnitsByProperty(id)` and `UnitFormModal` → `useUnits().create/update/remove`; `PropertyOverviewPanel` → `useUnits().getUnitsByProperty(id)` + `useAgreements().getAgreementsWithRefs()` | `GET/PUT/DELETE /properties/{id}`; `GET /properties/{id}/units`, `POST /properties/{id}/units`, `PATCH`/`DELETE /units/{id}`; `GET /agreements?expand=...` | Demo adapters do the equivalent in-memory find/splice/push against `propertiesMock`/`unitsMock`/`agreementsMock`. Documents tab is a Phase-4 placeholder behind `features.documents`, no service call either way. |
| Tenants list | `/owner/tenants` | `useTenants().getTenants()`; invite via `TenantInviteModal` → `useTenants().invite(input)` | `GET /tenants`; `POST /tenants/invite` | `demo/services/tenants.ts`: `getTenants` clones `tenantsMock`; `invite` pushes `{status:"invited", invitedAt: now}` — no mail either way (matches the backend's un-implemented invite mail). |
| Tenant detail | `/owner/tenants/[id]` | `useTenants().getTenant(id)`, `.update(id, patch)` (tabs: Identity/Personal/Emergency via `TenantIdentityForm`/`TenantPersonalForm`/`TenantEmergencyContactForm`), `.remove(id)` | `GET/PUT/DELETE /tenants/{id}` | Demo find/merge/splice against `tenantsMock`. |
| Agreements list | `/owner/agreements` | `useAgreements().getAgreementsWithRefs()` | `GET /agreements?expand=unit,property,tenant` | `demo/services/agreements.ts` hydrates each `agreementsMock` row with its unit/property/tenant from the other mock arrays. |
| New agreement | `/owner/agreements/new` | `AgreementTermsForm` loads `useProperties().getProperties()`, `useUnits().getUnits()`, `useTenants().getTenants()` for the pickers, then `useAgreements().create(payload)` | `GET /properties`, `GET /units`, `GET /tenants`; `POST /agreements` | Demo equivalents; `create` pushes into `agreementsMock`. |
| Agreement detail | `/owner/agreements/[id]` | `useAgreements().getAgreementsWithRefs()` (finds by id client-side — no single-agreement expand fetch), `AgreementTermsForm` → `.update(id, patch)`, page → `.remove(id)` | `GET /agreements?expand=...` (list, filtered client-side); `PUT`/`DELETE /agreements/{id}` | Documents tab (signed lease, addendums, etc.) is a static Phase-4 slot list behind `features.documents` — no backend call. |
| Payments | `/owner/payments` | `useInvoices().getInvoicesWithRefs()`; `RecordPaymentModal` → `.recordPayment(input)`; `InvoiceViewModal` → `.sendInvoice(id)` | `GET /invoices?expand=agreement,unit,property,tenant,payments`; `POST /invoices/{id}/payments`; `POST /invoices/{id}/send` | `demo/services/invoices.ts` hydrates `invoicesMock` with refs + `paymentsMock`; `recordPayment`/`sendInvoice` mutate/no-op in memory. `sendInvoice` is a stub on both sides (no real email). |
| Maintenance list | `/owner/maintenance` | `useTickets().getTicketsWithRefs()`; `TicketCreateModal` loads `useUnits().getUnits()`, `useProperties().getProperties()`, `useTenants().getTenants()` for pickers then `.create(input)` | `GET /tickets?expand=unit,property,reporter,comments`; `GET /units`, `GET /properties`, `GET /tenants`; `POST /tickets` | Demo hydrates `ticketsMock` with unit/property/reporter/sorted comments; `create` pushes with `reporterRole: "owner"`. |
| Maintenance detail | `/owner/maintenance/[id]` | `useTickets().getTicketWithRefs(id)`, `.transitionStatus(id, next)`, `.addComment(input)` | `GET /tickets/{id}?expand=...`; `PATCH /tickets/{id}/status`; `POST /tickets/{id}/comments` | Demo enforces the same `new→{in_progress,resolved}` etc. transition table client-side (mirrors `TicketStatus::canTransitionTo`) — not verified line-by-line here, flagged for owner spot-check. Photo attach is a Phase-4 stub. |
| Reports | `/owner/reports` | `useReports(year)` composable → `useProperties().getProperties()`, `useUnits().getUnits()`, `useTenants().getTenants()`, `useAgreements().getAgreements()`, `useInvoices().getInvoicesWithRefs()`, all `Promise.all`'d, then aggregated entirely client-side (income totals, monthly buckets, per-property RPGT via `utils/rpgt.ts`) | `GET /properties`, `GET /units`, `GET /tenants`, `GET /agreements`, `GET /invoices?expand=...` | Same list calls resolve against demo mocks; behaviour is otherwise 100% identical since the aggregation logic itself lives in the shared composable, not per-adapter. CSV download (`downloadCsv` in `utils/csv.ts`) is generated client-side from the same in-memory data on both builds — **does not** call `GET /reports/yearly/{year}/export` (which 501s server-side anyway). PDF export is a Phase-4 stub. |
| Settings | `/owner/settings` | `useOwnerSettings().getAccount()`, `.updateProfile/.updatePreferences/.updateNotifications(patch)`, `.getPlans()` (4-tab: Profile/Preferences/Notifications/Plan) | `GET /account`; `PATCH /account/profile|preferences|notifications`; `GET /plans` | `demo/services/ownerSettings.ts` merges patches onto a single `ownerAccountMock`/`plansMock` object in place. |

---

## Shell — Tenant (`layout: "tenant"`, `/tenant/*`)

`composables/useTenantSession.ts` resolves the acting tenant id: `DEMO_TENANT_ID` (`"t-aminah"`, hardcoded in `demo/auth.ts`) in demo, `auth.user.id` on the API. Every tenant-shell contract method takes a `tenantId` argument for the demo adapter's benefit; the API adapter **ignores it** and relies on the Sanctum session to scope `/me/*` server-side (see e.g. `apiTenants.getProfile`/`updateProfile` signatures below).

| Page | Route | Contract calls | API endpoint(s) | Demo behaviour |
|---|---|---|---|---|
| Home | `/tenant` (`pages/tenant/index.vue`) | `useInvoices().getInvoicesForTenant(tenantId)`; `useAgreements().getActiveAgreementForTenant(tenantId)`; `useTickets().getTicketsForTenant(tenantId)` (parallel) | `GET /me/invoices?expand=...`; `GET /me/agreement?expand=...`; `GET /me/tickets?expand=...` | Demo filters the same mock arrays by `tenantId === DEMO_TENANT_ID`. |
| Agreement | `/tenant/agreement` | `useAgreements().getActiveAgreementForTenant(tenantId)` | `GET /me/agreement?expand=unit,property,tenant` | Read-only summary; Documents card reuses the owner `AgreementDocumentsPanel`, gated by `features.documents`. |
| Payments | `/tenant/payments` | `useInvoices().getInvoicesForTenant(tenantId)`; `PayInvoiceModal` → `useInvoices().payForTenant(invoiceId, method)` | `GET /me/invoices?expand=...`; `POST /me/invoices/{id}/pay` `{method}` | API: server computes amount/paidAt and marks the invoice `paid` immediately — simulated FPX round-trip, no real gateway (matches the backend's `TODO Phase 3` note). Demo mirrors the same instant-success flow against `invoicesMock`/`paymentsMock`. The modal doubles as the receipt view (reads back the same `payment`/`invoice` pair). |
| Issues list | `/tenant/tickets` | `useTickets().getTicketsForTenant(tenantId)`; header stat pulls `useAgreements().getActiveAgreementForTenant(tenantId)` too | `GET /me/tickets?expand=...`; `GET /me/agreement?expand=...` | |
| Issue detail | `/tenant/tickets/[id]` | `useTickets().getTicketWithRefsForTenant(id)`, `.addCommentForTenant(input)` | `GET /me/tickets/{id}?expand=...`; `POST /me/tickets/{id}/comments` | Status is owner-controlled and read-only on this page — no `transitionStatus` call here. |
| Report an issue | (modal on Issues list, `ReportIssueModal`) | `useTickets().createForTenant(input)` | `POST /me/tickets` `{category, priority, title, description}` | **`unitId`/`reporterId`/`reporterRole` are never sent** — the API derives the unit from the tenant's active agreement server-side and ignores those fields even if present; demo needs the caller to have them locally to build a full `Ticket` object (contract docblock notes this explicitly) since there's no server to derive them. |
| Profile | `/tenant/profile` | `useTenants().getProfile(tenantId)`, `.updateProfile(tenantId, patch)` | `GET /me/profile`; `PATCH /me/profile` | View + single-form edit of Identity/Personal/Emergency contact. `email` is not part of `TenantProfileUpdate` — login identity, read-only everywhere. |

---

## Shell — Admin (`layout: "admin"`, `/admin/*`, gated by `features.admin` — always off in demo build)

| Page | Route | Contract calls | API endpoint(s) | Demo behaviour |
|---|---|---|---|---|
| Admin login | `/admin/login` | see Public/Auth above | | |
| Accept invite | `/admin/accept-invite` | see Public/Auth above | | |
| Dashboard | `/admin` (`pages/admin/index.vue`) | `useAdminDashboardData()` composable → `useAdminDashboard().getDashboard()`; gates tile links with `useAdminPermissions().can(key)` | `GET /admin/dashboard` | `demo/services/admin/dashboard.ts` computes the same `{tiles, series, attention}` from `adminOwnersMock/adminTenantsMock/adminPropertiesMock`, **except** `invoicesIssued`/`invoicesPaid` in the series, which are synthetic hand-picked numbers (`18 + i*2`, `14 + i*2 - (i%3)`) — the demo has no platform-wide invoice dataset to derive them from, called out in the file's own comment. |
| Analytics | `/admin/analytics` | `useAdminAnalytics().overview(range)`, `.leads(query)` (`q, source, converted, page, perPage`, range preset (`d7\|d30\|d90\|custom`) + filters synced to the URL query string), `LeadDrawer` → `.lead(id)`, export button → `.exportCsv({...leadsQuery, page: undefined})`, gated by `can("analytics.view")` | `GET /admin/analytics/overview?from&to`; `GET /admin/analytics/leads?...`; `GET /admin/analytics/leads/{id}`; `GET /admin/analytics/leads/export.csv?...` | `demo/services/admin/analytics.ts` computes the same `{tiles, series, funnel, topPages, referrers}` shape from `demo/data/analytics.ts` (`leadsMock`/`analyticsEventsMock`, 90 days deterministic), filters/paginates `leadsMock` in memory with the shared `paginate()` helper, and builds the export CSV client-side via `utils/csv.ts` rather than a server stream. Components: `FunnelStrip` (visitors→demo→leads→registered), `SourcePill`, `EventList` (in `LeadDrawer`), `LeadDrawer` itself. |
| Owners list | `/admin/owners` | `useAdminOwners().list(query)` (`q, plan, status, overdue, overCap, page, perPage`, synced to the URL query string) | `GET /admin/owners?...` (via `cleanQuery`) | `demo/services/admin/owners.ts` filters/sorts `adminOwnersMock` in memory, paginates with the shared `paginate()` helper. |
| Owner detail | `/admin/owners/[id]` | `useAdminOwners().get(id)`, `.history(id)`, lazy-loaded `.properties(id)`/`.tenants(id)` per tab; `WarnOwnerModal` → `.warn(id, input)`; `SuspendOwnerModal` → `.suspend(id, reason)` / `.unsuspend(id)`; resend button → `useAdminTenants().resendInvite(tenantId)` then re-fetches `.tenants(id)` | `GET /admin/owners/{id}`; `GET /admin/owners/{id}/history`; `GET /admin/owners/{id}/properties`; `GET /admin/owners/{id}/tenants`; `POST /admin/owners/{id}/warn`; `POST /admin/owners/{id}/suspend`; `POST /admin/owners/{id}/unsuspend`; `POST /admin/tenants/{id}/resend-invite` | Every write pushes a synthetic entry via `pushAudit()` into `auditMock` (demo's own audit trail — not connected to any real logging system), mirroring the backend's `AuditLogger`. |
| Tenants list | `/admin/tenants` | `useAdminTenants().list(query)` (`q, status, ownerId, page, perPage`, URL-synced) | `GET /admin/tenants?...` | Demo filters `adminTenantsMock` in memory. |
| Tenant detail | `/admin/tenants/[id]` | `useAdminTenants().get(id)`; `useAdminAudit().list({subjectType:"user", subjectId:id, perPage:50})` for the history panel; resend button → `.resendInvite(id)` | `GET /admin/tenants/{id}`; `GET /admin/audit?subjectType=user&subjectId={id}&perPage=50`; `POST /admin/tenants/{id}/resend-invite` | |
| Audit log | `/admin/audit` | `useAdminAudit().list(query)`; export button → `.exportCsv({...query, page: undefined})`, gated by `can("audit.view")` | `GET /admin/audit?...`; `GET /admin/audit/export.csv?...` | `demo/services/admin/audit.ts` applies the same "no `audit.view` → only your own rows" scoping rule client-side, and builds the CSV via a shared `utils/csv.ts` helper rather than a server stream. |
| Settings (admins) | `/admin/settings` | `useAdminAdmins().permissions()` + `.list()` (parallel on load); `AdminFormModal` → `.create(input)`; toggle → `.update(id, {disabled})`; resend → `.resendInvite(id)`; gated throughout by `useAdminPermissions().can("admins.manage")`/`isSuperAdmin` | `GET /admin/permissions`; `GET /admin/admins`; `POST /admin/admins`; `PATCH /admin/admins/{id}`; `POST /admin/admins/{id}/resend-invite` | Demo enforces the same "can't disable yourself" / "can't drop the last enabled super-admin" business rules client-side (mirrors the backend `AdminUserController@update` checks) by throwing `Error`s the modal surfaces as toasts. |

`useAdminPermissions().can(key)` is a **UI-only** gate (hide/disable controls) — the real enforcement is server-side `can:` middleware; this mirrors the doc note in `docs/backend/API-SPEC.md`'s Conventions section that permission checks must not be trusted client-side alone.

---

## Tracking

The `POST /track` beacon (see `docs/backend/API-SPEC.md` § Shell 1 — Analytics beacon) is fired from `composables/useTrack.ts`, selecting `demoTrack`/`apiTrack` (`~/demo/track.ts` / `~/services/api/track.ts`) via `useEnv().useMock`, same selector pattern as every other adapter pair.

- **Gate** — `track()` is a no-op unless `env.trackingEnabled` (`composables/useEnv.ts`: `!(isDemo || config.public.useMock) && config.public.tracking !== false`, driven by `NUXT_PUBLIC_TRACKING`) **and** the current path matches `isTrackedPath()` (`/`, or a `TRACKED_PREFIXES` prefix: `/coming-soon`, `/demo`, `/auth`) **and** `import.meta.client`. `demoTrack.send()` is always a hard no-op regardless of the gate, so `demo-roofly` never produces `/api/track` traffic even if the gate were somehow bypassed. The entire body of `track()` is wrapped in try/catch — analytics must never break a page.
- **Identity** — `visitorId` is a `crypto.randomUUID()` persisted to `localStorage["roofly_vid"]`; first-touch UTM params (`utm_source/medium/campaign`) are captured once from the landing URL into `localStorage["roofly_utm"]` and reused on every subsequent event.
- **Events fired** (`TRACK_EVENTS` in `types/analytics.ts`) and call sites:
  - `page_view` — `plugins/track.client.ts`, `router.afterEach`, on every client-side navigation to a tracked path.
  - `demo_enter` — `components/auth/DemoLoginShortcuts.vue` (`enter()`, with `{role}`), `pages/demo/index.vue` (`onMounted` with `{role:"landing"}`, and again in `enter()` before `auth.login`).
  - `demo_feedback_click` — wired on `components/demo/FloatingFeedback.vue`'s `@click`, but **reserved/not captured today**: the widget only renders in demo (`showFloatingFeedback` = demo-only), where `trackingEnabled` is always false, so this event never actually reaches `/api/track`.
  - `waitlist_signup` — `components/marketing/EmailCapture.vue`, on a successful Web3Forms submit, with `{email}`.
  - `register` — `pages/auth/register.vue`, after `auth.register()` resolves and before `navigateTo`, with `{email, userId}`. This is the only client-supplied `props.userId`; the backend's `AnalyticsRecorder` never trusts it for conversion (see the API-SPEC beacon note) — conversion is only ever written server-side via `linkRegistration()`.
- **Local opt-out** — set `NUXT_PUBLIC_TRACKING=false` to disable beacons in any non-demo build without touching code (verifiable via DevTools Network: zero `/api/track` requests).

---

## Cross-shell composables worth flagging

- **`composables/useDashboard.ts`** — thin wrapper picking `demoDashboard`/`apiDashboard` directly (not via a `useX` selector file) and localizing month labels client-side from the raw `{key: "YYYY-MM", amount}` buckets the server/demo returns; the server never sends a formatted label.
- **`composables/useAdminDashboardData.ts`** — same pattern for the admin dashboard, but goes through `useAdminDashboard()` (which *is* a normal `useX` selector) rather than importing `demoAdminDashboard`/`apiAdminDashboard` directly — inconsistent with `useDashboard.ts`'s approach; functionally equivalent, just structured differently.
- **`composables/useReports.ts`** — see the ⚠ note above; entirely client-side aggregation over plain list-fetch contract calls, no dedicated reports service/contract exists on the frontend at all.
- **`composables/useApiError.ts`** — used by every write-form page/modal (`toFieldErrors`) to turn a 422 into vee-validate errors; returns `null` for anything else so the caller falls back to a generic toast.
- **`stores/auth.ts`** — not a `services/useX.ts` file; picks `demoAuth`/`apiAuth` via its own inline `adapter()` function using the same `useEnv().useMock` check, called at the top of every action rather than once.
