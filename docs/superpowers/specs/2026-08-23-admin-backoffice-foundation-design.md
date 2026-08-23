# Admin back office — sub-project 1: Foundation — design

**Date:** 2026-08-23
**Scope:** A third shell in the existing Nuxt app (`/admin/*`, also served as `admin.roofly.my`) with its own Admin Portal login, a permission model with super-admin + customisable operations admins, an audit log, the platform dashboard, and owner/tenant control at a summary-only visibility tier with warn / suspend actions. Backend `/api/admin/*` routes, Resources, and contract tests.
**Out of scope (later sub-projects, outlined in §10):** support inbox, broadcast, WhatsApp/SMS channels (SP2); DB-backed feature flags, announcements, maintenance mode (SP3); plans, subscriptions, billing, gateway webhook log (SP4).
**Depends on:** the demo/API adapter split (`2026-08-23-demo-adapter-split-design.md`) — admin services follow the same contract → demo adapter → API adapter pattern.

---

## 1. Why

Roofly is becoming a SaaS with a small operations team. Before beta, the team needs to: see whether the platform is healthy, look up any owner or tenant and confirm their account is in order, warn and suspend owners who don't pay for the service, manage who on the team can do what, and have a record of every admin action. Nothing of this exists today: `admin` is a role enum value with no shell, no routes, no seeded user.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Where it lives | Same Nuxt app + same Laravel API. `layouts/admin.vue`, `pages/admin/**`, `/api/admin/*`. One deploy; `admin.roofly.my` is a hostname rule, not a separate app. |
| Login | **Separate Admin Portal login** at `/admin/login` with its own `auth-admin` layout. `POST /api/admin/auth/login` accepts only `role = admin`; the customer login rejects admins. Same Sanctum cookie session underneath. No self-signup, no demo shortcuts. |
| Who | Super-admin (the founder) + operations admins the super-admin creates. Permissions are **per admin**, chosen on the create/edit form from a fixed list, pre-filled from the "Operations" preset. |
| Visibility tier | Admin sees **summaries, never customer detail**. No impersonation / view-as of any kind. Exact field lists in §6. Enforced in API Resources and pinned by contract tests. |
| Money | No rent / invoice / payment amounts anywhere in the admin. Counts only (e.g. overdue invoice count). Platform revenue (MRR) arrives with SP4 from subscriptions. |
| Suspension | Admin action with mandatory reason. Blocks the **owner's** login/API (`403 account_suspended`); the owner's **tenants are unaffected**. Warn = notice sent on the owner's enabled channels (email in SP1; WhatsApp/SMS when SP2 lands). Both manual in SP1; SP4 automates them on failed subscription payment. |
| Audit | Every admin write is recorded via one `AuditLogger` (Spatie ActivityLog, `log_name = admin`). Read-only screen with filters + CSV. Indefinite retention. |
| Demo | `features.admin` is **off in demo** — `demo-roofly` never shows the admin. Locally with `useMock` the admin runs against a seeded fake platform so screens can be prototyped demo-first like everything else. |
| Accounts | Admins are `users` rows with `role = admin` (no new table). Created only from Settings → Admins; invited by email with a set-password link. Disable is soft. |

## 3. Shell, routing, hostname

```
layouts/admin.vue               sidebar: Dashboard · Owners · Tenants · Support (SP2) · Settings
layouts/auth-admin.vue          Admin Portal login chrome — neutral/dark, no marketing copy
pages/admin/login.vue
pages/admin/accept-invite.vue   set password from invite token
pages/admin/index.vue           dashboard
pages/admin/owners/index.vue    + [id].vue (4 tabs: Overview · Properties · Tenants · History)
pages/admin/tenants/index.vue   + [id].vue
pages/admin/settings.vue        tabs: Admins · (SP2 Channels · SP3 Flags · SP4 Plans)
pages/admin/audit.vue
pages/suspended.vue             full-page "Account suspended — contact support" (owner shell)
```

- **Route guard** (`auth.global.ts`): add `/admin` area. Unauthenticated → `/admin/login` (never the customer login). Authenticated non-admin → their own shell root. Admin on `/owner/*` or `/tenant/*` → `/admin` (there is no reason for an admin to be in a customer shell).
- **Env middleware** (`demo-only.global.ts` → renamed `env.global.ts`): on host `admin.roofly.my`, `/` → `/admin`; when `features.admin` is false, `/admin/*` → 404. On customer hosts `/admin/*` stays reachable by URL, still role-gated.
- **`useEnv()`** gains `features.admin` (env `NUXT_PUBLIC_FEATURE_ADMIN`, default on for uat/prod, forced off when `isDemo`) and `isAdminHost`.

## 4. Auth

- `POST /api/admin/auth/login` `{email, password}` → `{user: AuthUser}` ; 401 unless `role = admin` and not disabled. Logged (`admin.login`).
- `POST /api/admin/auth/accept-invite` `{token, password, password_confirmation}` → sets password, marks invite accepted, logs in.
- Customer `POST /api/auth/login` returns 401 for `role = admin` (so the customer page can't be used as a back door).
- Frontend: `AuthAdapter` gains `loginAdmin(email, password)` and `acceptAdminInvite(token, password)`; demo adapter: `admin@…` prefix → super-admin, `ops@…` → operations preset. `useAuthStore` unchanged otherwise; `isAdmin` already exists. `AuthUser` gains `permissions: string[]` and `isSuperAdmin: boolean` (empty / false for owners and tenants).

## 5. Permissions, admin users, audit log

**Permission list** (seeded; Spatie `permissions` table, guard `web`):

| Key | Grants | In Operations preset |
|---|---|---|
| `dashboard.view` | platform metrics | ✓ |
| `owners.view` | owners list + detail (§6 tier) | ✓ |
| `tenants.view` | tenants list + detail (§6 tier) | ✓ |
| `owners.warn` | send payment-warning notice | ✓ |
| `owners.suspend` | suspend / unsuspend | ✓ |
| `owners.plan` | change plan / override (SP4) | ✗ |
| `support.manage` | support inbox (SP2) | ✓ |
| `broadcast.send` | announcements (SP2) | ✓ |
| `settings.channels` | channel providers (SP2) | ✗ |
| `settings.flags` | feature flags (SP3) | ✗ |
| `admins.manage` | create / edit / disable admins | ✗ |
| `audit.view` | full audit log (own entries always visible) | ✗ |
| `users.delete` | PDPA delete / anonymise | ✗ |

- `users.is_super_admin` — bypasses every check; only a super-admin can grant it; a super-admin cannot disable themselves; there must always be ≥ 1 enabled super-admin (enforced server-side).
- Backend: `can:<key>` middleware per route (Spatie `Gate::before` returns true for super-admins). Frontend: `useAdminPermissions().can(key)` hides/disables UI; the API is the enforcement.
- **Admin users** (Settings → Admins, needs `admins.manage`): table (name, email, permissions count, status, last login); create (name, email, permission checklist pre-filled from preset) → invite email; edit permissions; disable / enable; resend invite. `GET /api/admin/permissions` returns the list + preset so the form never hard-codes keys.
- **AuditLogger** `record(string $action, ?Model $subject, array $before = [], array $after = [], ?string $reason = null)` — actor = current admin, stored in Spatie ActivityLog with `log_name = admin`, `properties = {before, after, reason, ip}`. Actions in SP1: `admin.login`, `admin.invite_sent`, `admin.invite_accepted`, `admin.permissions_changed`, `admin.disabled`, `admin.enabled`, `owner.warned`, `owner.suspended`, `owner.unsuspended`, `tenant.invite_resent`.
- **Audit screen** (`/admin/audit`): table newest-first; filters actor, action, subject type/id, date range; CSV export (`utils/csv.ts`); `audit.view` sees all, others see only their own.

## 6. Visibility tier (the privacy line)

| Entity | Admin **sees** | Admin **never sees** |
|---|---|---|
| Owner | id, name, email, phone, businessName, planTier, unitsUsed, unitsCap, status (`active` / `suspended`), suspendedAt, suspensionReason, createdAt, lastActiveAt, counts {properties, units, unitsOccupied, tenants, agreementsActive, agreementsExpiring30d, invoicesOverdue, ticketsOpen} | bank details, preferences, notification settings, any amount |
| Property (summary) | id, name, address (line, postcode, city, state), type, unitsTotal, unitsOccupied, createdAt | ownership / co-owners, utilities, documents, purchase price, RPGT data |
| Tenant | id, name, email, phone, status, ownerId + ownerName, property + unit (name/label), invitedAt, acceptedAt (first login), createdAt | personal (IC, DOB, occupation, income, nationality), emergencyContact, documents, agreement terms, invoices, tickets content |
| Agreements / invoices / tickets | counts only | records |

`AdminOwnerResource`, `AdminPropertySummaryResource`, `AdminTenantResource` emit exactly these keys; `AdminResourcesTest` asserts the key sets so a future "helpful" addition fails CI.

## 7. Dashboard (`GET /api/admin/dashboard`)

One payload, like the owner dashboard:

- **tiles:** owners {total, active, suspended}, tenants {total, invitedPending}, properties, units {total, occupiedPct}, agreementsActive, agreementsExpiring30d, supportOpen (0 until SP2)
- **series (12 months):** ownerSignups[], invoicesIssued[] vs invoicesPaid[] (counts), inviteAcceptanceRate[]
- **attention[]:** `{kind, ownerId, ownerName, meta, link}` for kinds `over_cap`, `overdue_3plus`, `invite_stale_7d`, `no_property_7d`, `suspended`

Frontend `useAdminDashboard()` mirrors `useDashboard()` (selector + computed + localised month labels). No money.

## 8. Owner & tenant control

**Owners list** — TanStack table: name · email · plan · units used/cap · properties · tenants · overdue (count) · status · signed up · last active. Search (name/email/business), filters (plan, status, over-cap, has-overdue). Server-side pagination/search (`?q=&plan=&status=&overCap=1&overdue=1&page=`).

**Owner detail tabs**
1. *Overview* — profile card, plan + usage bar, status badge, counts strip, actions **Send warning** (`owners.warn`) · **Suspend / Unsuspend** (`owners.suspend`).
2. *Properties* — summary list (§6). No links into owner pages.
3. *Tenants* — tenant rows (§6) with **Resend invite** for `invited` status (`tenants.view` + action logged). Row → `/admin/tenants/[id]`.
4. *History* — this owner's audit entries + platform events (signup, invites sent).

**Warn modal** — template select (SP1 ships one: `payment_overdue` — "Your Roofly subscription payment is overdue; your account will be suspended on {date} unless settled"), optional extra line, preview, send. Dispatched via Laravel Notification `OwnerWarning` on the owner's enabled channels — **mail only in SP1**; the notification class is written so SP2 adds `whatsapp` / `sms` channels without touching callers. Logged `owner.warned` with template + text.

**Suspend modal** — reason (required, ≥ 10 chars) → `users.suspended_at = now()`, `suspension_reason`. Logged. **Unsuspend** — confirm → clears both, logged.

**Suspension enforcement** — `EnsureNotSuspended` middleware on the owner route group (not `/me/*`): 403 `{code: "account_suspended", message}`; `useApi` `onResponseError` maps that code to `navigateTo("/suspended")`. The suspended page shows the reason-free message + support contact. Login still succeeds (so the owner sees the page rather than "invalid credentials").

**Tenants list** — cross-owner table: name · email · phone · status · owner · property/unit · invited · accepted. Search + filters (status, owner). Detail = same fields + history + **Resend invite**.

## 9. Backend & frontend structure

**Migration** `add_admin_fields_to_users`: `is_super_admin` bool default false, `suspended_at` nullable ts, `suspension_reason` nullable text, `last_active_at` nullable ts, `disabled_at` nullable ts (admins). Table `admin_invites` (id uuid, user_id, token hash, expires_at, accepted_at). Seeder: permission list; `DemoSeeder` adds one super-admin (`admin@roofly.my` / `password`) and one ops admin (`ops@roofly.my` / `password`); `tenants.accepted_at` derived from first login (new `users.first_login_at` set by login controllers).

**Middleware:** `role:admin` (existing `EnsureRole`), `can:` (Spatie), `EnsureNotSuspended`, `TouchLastActive` (throttled 10 min, owners + tenants + admins).

**Routes** `/api/admin/*` (all `auth:sanctum` + `role:admin` unless noted):
```
POST  admin/auth/login                 (guest)
POST  admin/auth/accept-invite         (guest)
GET   admin/dashboard                  can:dashboard.view
GET   admin/owners                     can:owners.view
GET   admin/owners/{id}                can:owners.view
GET   admin/owners/{id}/properties     can:owners.view
GET   admin/owners/{id}/tenants        can:owners.view
GET   admin/owners/{id}/history        can:owners.view
POST  admin/owners/{id}/warn           can:owners.warn
POST  admin/owners/{id}/suspend        can:owners.suspend
POST  admin/owners/{id}/unsuspend      can:owners.suspend
GET   admin/tenants                    can:tenants.view
GET   admin/tenants/{id}               can:tenants.view
POST  admin/tenants/{id}/resend-invite can:tenants.view
GET   admin/permissions                can:admins.manage
GET   admin/admins                     can:admins.manage
POST  admin/admins                     can:admins.manage
PATCH admin/admins/{id}                can:admins.manage
POST  admin/admins/{id}/resend-invite  can:admins.manage
GET   admin/audit                      (audit.view → all; else own)
GET   admin/audit/export.csv           can:audit.view
```

**Frontend**
```
services/contracts/admin/{auth,dashboard,owners,tenants,admins,audit}.ts
services/api/admin/*.ts               apiAdminX
demo/services/admin/*.ts              demoAdminX
demo/data/admin.ts                    fake platform: 4 owners (free/starter/pro, one suspended,
                                      one over cap), their tenants (some invited-pending),
                                      2 admins, ~30 audit rows
services/useAdminDashboard.ts … useAdminAudit.ts   selectors (auto-imported)
composables/useAdminPermissions.ts    can(key) from auth.user.permissions / isSuperAdmin
```
All admin services follow the adapter-split rules (demo never imports `useApi`; API never imports `~/demo`).

**UI conventions:** reuse `Card`, `Pill`, `Button`, `Input`, `Select`, `Modal`, `EmptyState`, TanStack table setup from `payments.vue`, card-row pattern for attention/history. Sentence case, BM + EN strings for every label (admin UI is bilingual like the rest). Admin accent colour is a new token pair in UI-STANDARDS (§ new "Admin shell" note) so the back office is visually distinct from the customer app.

## 10. Testing

- **Backend** (`tests/Feature/Admin/*`): admin login accepts admin only / customer login rejects admin; permission denial 403 + super-admin bypass; Resource key sets exactly per §6 (`AdminResourcesTest`); suspended owner → 403 `account_suspended` on `/properties` while their tenant's `/me/agreement` → 200; unsuspend restores; warn sends `OwnerWarning` (Notification::fake) and logs; admins CRUD incl. "last super-admin cannot be disabled"; audit list filters; dashboard payload shape.
- **Frontend**: `nuxt typecheck` (5 known errors, 0 new); Playwright smoke in both modes: admin login → dashboard → owners list → owner detail → suspend → (new context) owner login lands on `/suspended` → unsuspend → owner dashboard loads; ops admin cannot see Settings → Admins; demo mode makes zero `:8000` requests.

## 11. Sub-projects 2–4 (decisions already taken today, for the record)

- **SP2 — Support & channels.** In-app "Help & support" form in owner and tenant shells (system issues only; distinct from maintenance tickets) → support case in admin inbox (`support.manage`): status, assignee, internal notes, reply thread. Each reply is dispatched on every channel the user has enabled: **email, WhatsApp (Cloud API), SMS (Malaysian gateway — provider to be researched then)** via Laravel Notifications + RabbitMQ queue, with a `notification_deliveries` table (sent/failed per channel) visible in admin. Admin Settings → Channels holds provider credentials and global on/off per channel; owner Settings → Notifications gains SMS. **Broadcast** (`broadcast.send`): audience (all owners / plan tier / one owner's tenants), compose, channels, send, delivery report. `OwnerWarning` from SP1 picks up the new channels automatically.
- **SP3 — Settings & flags.** DB-backed feature flags replacing `features.*` env (global default + per-owner override), announcement banner, maintenance mode. This is how demo-first prototypes are rolled out to real owners gradually.
- **SP4 — SaaS control.** Plans & pricing editing, subscriptions (beta-free flag, trial, tier), platform invoices to owners, payment-gateway webhook log + retry, MRR on the admin dashboard, **automatic warn → suspend on failed subscription payment** using SP1's actions.

## 12. Open points (decide during the plan, not blockers)

- Admin accent colour value (UI-STANDARDS) — pick during implementation.
- Invite email sender/template wording (EN + BM).
- Whether `admin.roofly.my` gets its own cookie domain or shares `.roofly.my` — share, unless Sanctum stateful-domain config makes it awkward.
