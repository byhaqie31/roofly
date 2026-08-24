# Roofly backend API spec

First-cut reference for the Laravel API — organised per shell → per module → per endpoint. Accuracy over prose: every field/rule below was read from `backend/routes/api.php`, the controllers, FormRequests, and Resources, not invented. Cross-link: [docs/frontend/API-MAP.md](../frontend/API-MAP.md) — how the frontend consumes this contract, per page.

Route count documented: **96** (90 above baseline + `POST /auth/google`, `POST /auth/forgot-password`, `POST /auth/reset-password`, `PATCH /account/onboarding`, `PATCH /account/checklist`, `POST /account/password` from `.superpowers/sdd/2026-08-23-google-login-owner-onboarding/`).

---

## Conventions

- **JSON casing** — all request/response bodies are camelCase. FormRequests map camelCase → snake_case columns via a `toModelAttributes()` helper (e.g. `rentAmount` → `rent_amount_cents`). **Exception:** `Owner\ReportController@dashboard` / `@yearly` return raw snake_case keys (`income_this_month_cents`, `monthly_income`, …) straight from the query — this predates the camelCase convention and was not caught by contract tests. Flag for the owner to confirm whether the frontend's `useReports.ts` API adapter compensates or whether this is a live bug.
- **Money** is integer sen (cents) everywhere in request/response bodies — `amount`, `rentAmount`, `depositAmount`, `lateFee`, etc. Never a formatted string.
- **Response envelope** — `JsonResource::withoutWrapping()` is called once in `AppServiceProvider` (`app/Providers/AppServiceProvider.php:25`), so single-resource responses are the bare object (no `{data: …}` wrapper) and collection responses are a bare array — **except** endpoints that paginate, which return `{data: [...], meta: {page, perPage, total, lastPage}}` explicitly in the controller (admin audit, admin owners, admin tenants).
- **Auth** — Sanctum. The owner/tenant surface uses `POST /api/auth/login` which returns a Bearer token (`createToken('api')->plainTextToken`) alongside the user; the admin portal (`POST /api/admin/auth/login`) uses session/cookie auth (`Auth::attempt` + `Auth::guard('web')`), no token in the response. Both surfaces sit behind `auth:sanctum`. `GET /sanctum/csrf-cookie` is Laravel Sanctum's standard cookie-auth bootstrap route (not custom, not in `routes/api.php` — provided by the Sanctum package) and is needed before any cookie-session (admin/SPA) write.
- **Route middleware stack**, read left→right as applied: `api` (route prefix) → `Authenticate:sanctum` (all protected routes) → `TouchLastActive` (aliased `touch-active`, 10-minute-throttled `last_active_at` heartbeat write, `app/Http/Middleware/TouchLastActive.php`) → role guard (`role:owner`, `role:tenant`, `role:admin` — `App\Http\Middleware\EnsureRole`, compares `$user->role->value` to the route's string arg, `abort(403)` on mismatch, **plain Laravel `{message}` body, no `code`**) → `not-suspended` (owner shell only — `App\Http\Middleware\EnsureNotSuspended`, 403 with `{code:"account_suspended", message}` if `owner.suspended_at` is set) → `can:<permission>` (admin shell only — Laravel's built-in Gate middleware against Spatie permission names defined in `App\Support\AdminPermissions`).
- **Trusted proxies** — `bootstrap/app.php` calls `$middleware->trustProxies(at: '*')`, since nginx/Cloudflare sit in front of every environment. Without this, the client IP used for per-IP throttling (`throttle:track`) and the analytics `ip_hash` would be the proxy's address instead of the real client's.
- **Error shapes**:
  - `401` — `{"message": "..."}` (unauthenticated, or login/admin-login with bad credentials — both return `401 {"message": "Invalid credentials."}` rather than 422, and an admin who successfully authenticates via the *customer* `/api/auth/login` form is logged out and also gets this 401 — admins are only allowed in through `/api/admin/auth/login`).
  - `403` — bare `{"message": "..."}` from `abort_if`/`abort_unless` ownership checks (e.g. "you don't own this property") and from `EnsureRole`; **`{"code": "account_suspended", "message": "..."}`** specifically from `EnsureNotSuspended`.
  - `404` — Laravel default (route-model-binding miss, or explicit `abort_if($x->role !== ..., 404)` to hide cross-role records — e.g. fetching a tenant id that is actually an owner 404s rather than 403s).
  - `409` — used for state-conflict business rules: admin `admins/{admin}/resend-invite` when already accepted, admin `owners/{owner}/suspend` when already suspended / `unsuspend` when not suspended, admin `tenants/{tenant}/resend-invite` when status isn't `invited`.
  - `422` — Laravel's default FormRequest validation failure shape: `{"message": "The given data was invalid.", "errors": {"field": ["message", ...]}}`. Also used for business-rule rejections that aren't pure field validation, e.g. ticket status transition rejected by `Ticket::canTransitionTo()`, tenant "no active agreement" when filing a ticket, co-owner sum≠100/not-exactly-one-primary (via a custom `after()` validator hook, still surfaces as the same `errors.coOwners` shape), and "cannot remove primary co-owner".
  - `501` — used for genuinely unimplemented endpoints: `POST /api/auth/magic-link`, `GET /api/auth/magic-link/{token}`, `GET /api/reports/yearly/{year}/export`.
- **Pagination** query params, where supported: `page` (1-based), `perPage` (clamped `min(100, max(1, n))`, default varies by endpoint — 20 or 25). Response `meta: {page, perPage, total, lastPage}`.
- **UUIDs** — all model ids (`users`, `properties`, `units`, etc.) are UUID strings, not integers.
- Every admin **write** (warn/suspend/unsuspend/admin CRUD/permission changes/tenant resend-invite) is logged via `App\Services\AuditLogger::record()` into Spatie ActivityLog under `log_name = 'admin'`, action constants listed in the Admin § Audit section below. Reads are not logged.

---

## Shell 1 — Public / Auth

No auth required except where noted. Base path `/api`.

| Method | Path | Middleware | Request | Response | Notes |
|---|---|---|---|---|---|
| POST | `/auth/register` | `api` | body: `name` (required, string≤255), `email` (required, email≤255, unique `users.email`), `phone` (nullable, string≤30), `password` (required, string≥8, `confirmed` → needs `password_confirmation`) | `201` `{user: AuthUserResource, token}` | Creates `role: owner`. No email verification step exists yet. |
| POST | `/auth/login` | `api` | body: `email` (required, email), `password` (required, string) | `200` `{user: AuthUserResource, token}` | `401 {message}` on bad credentials. If the account resolves to `role: admin`, the session is logged out and this endpoint also returns `401` — admins must use `/admin/auth/login`. **`422 {errors:{email:["This account signs in with Google."]}}`** if the resolved account is an owner with no password set (`hasPassword: false`) — a Google-only account; the check runs before `Auth::attempt`, so it never counts as a failed-credentials attempt. Sets `first_login_at` on first successful login. |
| POST | `/auth/google` | `api`, `throttle:10,1` | body: `credential` (required, string — a Google Identity Services ID token, **not** an OAuth access token) | `201` `{user: AuthUserResource, token}` (new owner created) or `200` (existing owner linked/logged in); `401 {message}` if the token can't be verified (no user created); `403 {message, code: "not_owner"}` if the verified email belongs to a tenant or admin | Owner-only sign-in. Verifies via `App\Support\GoogleIdToken::verify()` — calls Google's `tokeninfo` endpoint directly; **no Composer package** (`laravel/socialite` was rejected — its `userFromToken` wants an OAuth access token, not an ID token). Auto-links an existing owner by Google-verified email (backfills `google_id`/`avatar_url`/`email_verified_at` if unset) or creates one (`password: null`, `email_verified_at: now()`). Starts a Sanctum session + token, same envelope as `/auth/login`. Audit `auth.google_register` (create) / `auth.google_login` (link). |
| POST | `/auth/forgot-password` | `api`, `throttle:5,1` | body: `email` (required, email) | `200 {message}` — **always**, whether or not the email exists | Generic message never reveals account existence. The admin-skip check (`Password::sendResetLink`'s callback deletes the token and returns `RESET_LINK_SENT` without mailing) runs **inside** the callback deliberately — the whole method body, unknown-email branch included, shares Laravel's `Timebox`-padded ~200ms, so branching outside the callback would reintroduce a timing oracle. Reset link points at `{FRONTEND_URL}/auth/reset-password?token=…&email=…`. |
| POST | `/auth/reset-password` | `api`, `throttle:5,1` | body: `token` (required, string), `email` (required, email), `password` (required, string≥8, `confirmed`) | `200 {user: AuthUserResource, token}` (same envelope as `/auth/login`) | `422 {errors:{email:["This reset link is invalid or has expired."]}}` on an invalid/expired token or an admin email. On success: sets the password, revokes all existing Sanctum tokens (`$user->tokens()->delete()`), logs the user in, mints a new token. Also the path by which a Google-only account (no password) gains one. |
| POST | `/auth/magic-link` | `api` | body: `email` (required, email) | `501 {message: "Magic link feature coming in Phase 2."}` | **Stub.** `TODO Phase 2`: generate signed URL, dispatch `TenantInviteNotification`. |
| GET | `/auth/magic-link/{token}` | `api` | path: `token` | `501 {message: "Magic link feature coming in Phase 2."}` | **Stub.** `TODO Phase 2`: verify signed token, issue Sanctum token. |
| POST | `/admin/auth/login` | `api` | body: `email` (required, email), `password` (required, string) | `200` `{user: AuthUserResource}` (no token — cookie session) | `401` on bad credentials, or if the resolved user isn't `role: admin` or is disabled (session torn down either way). Sets `first_login_at` if unset. Logs `admin.login` (`AuditLogger::ADMIN_LOGIN`). |
| POST | `/admin/auth/accept-invite` | `api` | body: `token` (required, string), `password` (required, string≥8, `confirmed`) | `200` `{user: AuthUserResource}` | Looks up `AdminInvite` by `sha256(token)`; `422 {errors:{token:[...]}}` if missing/expired/used/target user not an admin/target disabled. Sets the user's password, `first_login_at`, marks the invite `accepted_at`, logs the caller in (`web` guard), logs `admin.invite_accepted`. |

### Analytics beacon

| Method | Path | Middleware | Request | Response | Notes |
|---|---|---|---|---|---|
| POST | `/track` | `api`, `throttle:track` (120/min/IP) | body (`TrackRequest`): `visitorId` (required, uuid), `event` (required, one of `App\Models\AnalyticsEvent::EVENTS`: `page_view\|demo_enter\|demo_feedback_click\|waitlist_signup\|register`), `path` (nullable, ≤255), `referrer` (nullable, ≤255), `utm` (nullable, array of `source\|medium\|campaign`, each ≤100), `props` (nullable, array, JSON-encoded size ≤2048 bytes; `props.email` nullable email≤255, `props.userId` nullable uuid, `props.role` nullable ≤20), `at` (nullable, date) | `204` on success, `422` on validation failure | Guest, no auth. **Exempted from CSRF** (`bootstrap/app.php`'s `validateCsrfTokens(except: ['api/track'])`) — as a `sendBeacon`/`fetch` call from a marketing page, it can be treated as a "stateful" frontend request by Sanctum without a matching CSRF token, which would otherwise 419. `App\Services\AnalyticsRecorder::record()` writes an `AnalyticsEvent` row (IP salted-hashed via `hash('sha256', ip.config('app.key'))`, never stored raw) and upserts a `Lead` by email when `props.email` is present. **`props.userId` is never trusted for conversion** — only the server's own `AnalyticsRecorder::linkRegistration()` (called from the trusted `/auth/register` flow with the authenticated user's own id) may set `Lead.converted_user_id`. |

### Common protected (any authenticated role)

| Method | Path | Middleware | Request | Response | Notes |
|---|---|---|---|---|---|
| POST | `/auth/logout` | `Authenticate:sanctum`, `touch-active` | — | `204` | Deletes only the current access token (`$request->user()->currentAccessToken()->delete()`). |
| GET | `/auth/me` | `Authenticate:sanctum`, `touch-active` | — | `200` `AuthUserResource` | Works for owner, tenant, and admin. Frontend/admin-portal use this to detect a suspended owner without a failed login. |

---

## Shell 2 — Owner (`role:owner`, `not-suspended`)

Base path `/api`, all routes additionally gated by `EnsureNotSuspended` (403 `account_suspended` if the owner is suspended).

### Dashboard

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/dashboard` | — | `200` inline JSON: `{isEmpty, stats:{monthlyIncome, occupancyPct, occupiedCount, unitCount, outstanding, outstandingCount, expiringCount}, incomeSeries:[{key:"YYYY-MM", amount}] (12 entries, oldest first), needsAttention:[{kind, title, meta, link}]}` | One aggregated payload — no separate Resource class. `needsAttention.kind` ∈ `overdue \| expiring \| notice_given \| ticket_new \| ticket_reopened`, in that priority order. **Every stat and attention-feed item is scoped to `purpose: rental` properties only** (units/agreements/invoices/tickets are all derived from that rental-only property id set) — **except `isEmpty`, which counts ALL of the owner's properties** regardless of purpose, so an owner whose only property is an own-stay home never sees the "add your first property" empty state. Mirrors `frontend/app/composables/useDashboard.ts` mock computation; must be kept in lock-step by hand. |

### Account / settings

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/account` | — | `200` `OwnerAccountResource` | |
| PATCH | `/account/profile` | body: `name` (sometimes, string≤255), `phone` (sometimes, string≤30), `businessName` (nullable, string≤255) | `200` `OwnerAccountResource` | `businessName` → `business_name`. |
| PATCH | `/account/preferences` | body: `locale` (sometimes, `en\|ms`), `theme` (sometimes, `light\|dark\|system`), `moneyLocale` (sometimes, `en-MY`) | `200` `OwnerAccountResource` | Shallow-merges onto the existing `owner_preferences` JSON column (defaults: `{locale:"en", theme:"system", moneyLocale:"en-MY"}`). |
| PATCH | `/account/notifications` | body: `events` (sometimes, array), `channels` (sometimes, array) | `200` `OwnerAccountResource` | Shallow-merges onto `notification_preferences` JSON (defaults: 5 event toggles all `true` except none disabled by default, `channels:{email:true, whatsapp:false, in_app:true}` — see `OwnerAccountResource::defaultNotifications()`). No field-level validation inside `events`/`channels` — any array is accepted. |
| GET | `/plans` | — | `200` array of `{tier, priceRm, unitsCap, description}` (4 rows: free/starter/pro/business) | Static/hardcoded in the controller, not DB-backed. `description` is an i18n key, not literal text. `unitsCap` is `2\|5\|25\|"unlimited"`. Duplicates the cap logic in `App\Support\PlanCaps` (used server-side for enforcement) — the two are not read from one source. |
| PATCH | `/account/onboarding` | body (`CompleteOnboardingRequest`): `purposes` (required, array, min 1, each ∈ `rental\|own_stay\|investment`) | `200` `AuthUserResource` | **Idempotent on `onboarded_at`** — `purposes` is overwritten every call, but `onboarded_at` is only set the first time (`$user->onboarded_at ?? now()`), never moved on a re-call. Audit `account.onboarded`. |
| PATCH | `/account/checklist` | body: `dismissed` (required, boolean) | `200` `AuthUserResource` | Sets/clears `checklist_dismissed_at`. Audit `account.checklist_dismissed` (true) / `account.checklist_restored` (false). |
| POST | `/account/password` | body: `password` (required, string≥8, `confirmed`) | `200` `AuthUserResource` | `422 {errors:{password:["A password is already set."]}}` if the owner already has one — this is an **add-a-password** path for Google-only accounts, never a change-password path. Audit `account.password_set`. |

### Properties

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/properties` | — | `200` array of `PropertyResource` (with `coOwners` loaded) | |
| POST | `/properties` | body (`StorePropertyRequest`): `name` (required, string≤255), `type` (required, `condo\|landed\|shoplot\|room`), `purpose` (`sometimes`, `in:rental,own_stay,investment`, defaults to `rental` at the DB column level if omitted), `address` (required, string≤500), `city` (required, string≤100), `state` (required, one of the 16 Malaysian states — see `StorePropertyRequest::MY_STATES`), `postcode` (required, `digits:5`) | `201` `PropertyResource` | Auto-creates one `PropertyCoOwner` row: the creating owner, `sharePct: 100`, `isPrimary: true`. |
| GET | `/properties/{property}` | — | `200` `PropertyResource` | `403` if `property.owner_id !== auth user id`. |
| PUT | `/properties/{property}` | body (`UpdatePropertyRequest`, all `sometimes`/`nullable`): Tier-1 fields as above plus `purpose` (`sometimes`, `in:rental,own_stay,investment`), `internalLabel`, `notes`, `yearBuilt` (1900–2100), `builtUpSqft`, `landSqft`, `bedrooms` (0–20), `bathrooms` (0–20), `parkingLots`, `furnishing` (`unfurnished\|partial\|fully`), `ownership` (array, stored verbatim), `utilities` (array, stored verbatim), and optionally `coOwners[]` (`{id?, name, sharePct, isPrimary}[]`, min 1) | `200` `PropertyResource` | If `coOwners` is present, invariants are enforced (sum of `sharePct` = 100 ± 0.01, exactly one `isPrimary`) via a shared static helper (`SyncCoOwnersRequest::coOwnerInvariantErrors`) also used by the dedicated co-owner sync endpoint below; violations surface as `422 {errors:{coOwners:[...]}}`. If `coOwners` present, existing rows are deleted and replaced. |
| DELETE | `/properties/{property}` | — | `204` | Hard delete. |

### Co-owners (nested under properties)

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/properties/{property}/co-owners` | — | `200` array of `PropertyCoOwnerResource` | |
| POST | `/properties/{property}/co-owners` | body: `name` (required, string≤255), `sharePct` (required, numeric 0.01–100), `isPrimary` (boolean), `userId` (nullable, uuid, exists `users.id`) | `201` `PropertyCoOwnerResource` | Validated inline in the controller (not a FormRequest class). Does **not** enforce the sum=100/one-primary invariant — that only happens on `PUT` (sync) and on `PUT /properties/{id}` with `coOwners`. |
| PUT | `/properties/{property}/co-owners` | body (`SyncCoOwnersRequest`): `coOwners` (required array, min 1) of `{id? (nullable string), name (required, ≤255), sharePct (required, 0.01–100), isPrimary (required boolean)}` | `200` array of `PropertyCoOwnerResource` | Full replace: deletes all existing rows, recreates from the payload. Enforces sum=100 and exactly-one-primary via `after()` validator hook → `422 {errors:{coOwners:[...]}}`. |
| DELETE | `/properties/{property}/co-owners/{coOwner}` | — | `204` | `422` if the target `coOwner.is_primary` — must reassign primary first. |

### Units — flat list, nested create (mirrors `useUnits.ts`)

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/units` | — | `200` array of `UnitResource` | All units across every property the owner owns. |
| GET | `/properties/{property}/units` | — | `200` array of `UnitResource` | |
| POST | `/properties/{property}/units` | body (`StoreUnitRequest`): `label` (required, string≤255), `bedrooms` (nullable, 0–20), `bathrooms` (nullable, 0–20), `sqft` (nullable, ≥1), `status` (nullable, `vacant\|occupied\|maintenance`) | `201` `UnitResource` | |
| GET | `/units/{unit}` | — | `200` `UnitResource` | `403` if the unit's property isn't owned by the caller. |
| PATCH | `/units/{unit}` | body (`UpdateUnitRequest`, same fields as store, all `sometimes`) | `200` `UnitResource` | |
| DELETE | `/units/{unit}` | — | `204` | |

### Tenants

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/tenants` | — | `200` array of `TenantResource` | Visible set = tenants this owner invited, **or** who hold/held an agreement on one of the owner's units (`App\Support\OwnerTenantsQuery`-equivalent inline query). |
| POST | `/tenants` | body (`InviteTenantRequest`): `name` (required, ≤255), `email` (required, email≤255, unique `users.email`), `phone` (required, ≤30) | `201` `TenantResource` | Alias of `invite` below — same controller method. |
| POST | `/tenants/invite` | same as above | `201` `TenantResource` | Creates `role: tenant, status: invited, invited_at: now(), invited_by: <owner id>`. **No mail sent** — `TODO Phase 3`: dispatch magic-link invite notification. No password is set on the record here. |
| GET | `/tenants/{tenant}` | — | `200` `TenantResource` | `404` if the id isn't a tenant; `403` if not visible to this owner (same rule as list). |
| PUT | `/tenants/{tenant}` | body (`UpdateTenantRequest`, all `sometimes`): `name`, `email` (unique excluding self), `phone`, `status` (`invited\|active\|notice_given\|moved_out`), `personal` (nullable array → `personal_info`), `emergencyContact` (nullable array → `emergency_contact`) | `200` `TenantResource` | |
| DELETE | `/tenants/{tenant}` | — | `204` | Hard delete. |

### Agreements

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/agreements` | query: `expand` (any truthy value) | `200` array of `AgreementResource`, or `AgreementWithRefsResource` (`{agreement, unit, property, tenant}`) if `expand` present | Scoped to agreements on units the owner owns. |
| POST | `/agreements` | body (`StoreAgreementRequest`): `unitId` (required, uuid, exists), `tenantId` (required, uuid, exists `users.id`), `startDate` (required, date), `endDate` (required, date, after `startDate`), `rentAmount` (required, int ≥1), `depositAmount` (required, int ≥0), `lateFee` (nullable, int ≥0), `rentDueDay` (required, int 1–28), `status` (nullable, `draft\|active\|expired\|terminated`) | `201` `AgreementResource` | `403` if the target unit's property isn't owned by the caller. |
| GET | `/agreements/{agreement}` | — | `200` `AgreementResource` | `403` if not the owner's unit. |
| PUT | `/agreements/{agreement}` | body (`UpdateAgreementRequest`, same fields all `sometimes`) | `200` `AgreementResource` | If `unitId` changes, re-checks unit ownership (`403` on mismatch). |
| DELETE | `/agreements/{agreement}` | — | `204` | |

### Invoices

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/invoices` | query: `status`, `year`, `month`, `expand` | `200` array of `InvoiceResource` (or `InvoiceWithRefsResource` if `expand`), ordered `due_date desc` | |
| GET | `/invoices/{invoice}` | query: `expand` | `200` `InvoiceResource` / `InvoiceWithRefsResource` | `403` if not the owner's invoice. |
| PATCH | `/invoices/{invoice}` | body (`UpdateInvoiceStatusRequest`): `status` (required, `pending\|paid\|overdue\|cancelled`) | `200` `InvoiceResource` | Same handler as the `/status` route below (`updateStatus` bound twice — a duplicate route, both present in `routes.txt`). |
| PATCH | `/invoices/{invoice}/status` | same as above | `200` `InvoiceResource` | Duplicate of the route above; kept for frontend naming parity (`useInvoices.ts`). |
| POST | `/invoices/{invoice}/send` | — | `200` `{sentAt: ISO8601}` | **Stub** — no actual email/WhatsApp dispatch. `TODO Phase 4`: `InvoiceSentNotification`. |
| POST | `/invoices/{invoice}/payments` | body (`RecordPaymentRequest`): `amount` (required, int ≥1 → `amount_cents`), `method` (required, `fpx\|card\|cash\|transfer`), `paidAt` (required, date → `paid_at`), `reference` (nullable, string≤255) | `201` `{payment: PaymentResource, invoice: InvoiceResource}` | Creates a `Payment` with `status: successful` (owner-recorded payments are trusted, no gateway round-trip) and flips the invoice to `paid`. |

### Maintenance tickets

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/tickets` | query: `expand` | `200` array of `TicketResource` (or `TicketWithRefsResource`) | Scoped to tickets on the owner's units. |
| POST | `/tickets` | body (`StoreTicketRequest`): `unitId` (required, uuid, exists), `category` (required, `plumbing\|electrical\|appliance\|structural\|pest\|other`), `priority` (required, `low\|medium\|high\|urgent`), `title` (required, ≤100), `description` (required, string) | `201` `TicketResource` | `reporter_id`/`reporter_role` are server-set to the calling owner (`ReporterRole::OWNER`), never trusted from the body. |
| GET | `/tickets/{ticket}` | query: `expand` | `200` `TicketResource` / `TicketWithRefsResource` | |
| PUT | `/tickets/{ticket}` | body, validated **inline** (not a FormRequest class): `category` (sometimes), `priority` (sometimes), `title` (sometimes, ≤100), `description` (sometimes) | `200` `TicketResource` | Does not go through `status` — status is a separate endpoint (see below). |
| DELETE | `/tickets/{ticket}` | — | `204` | |
| PATCH | `/tickets/{ticket}/status` | body (`UpdateTicketStatusRequest`): `status` (required, `new\|in_progress\|resolved\|reopened`) | `200` `TicketResource`, or `422 {message}` | Enforced state machine — `TicketStatus::canTransitionTo()`: `new→{in_progress,resolved}`, `in_progress→{resolved,new}`, `resolved→{reopened}`, `reopened→{in_progress,resolved}`. Invalid transitions return `422` with a plain `message`, **not** the standard `errors{}` shape. Sets `resolved_at` on transition to `resolved`. |
| POST | `/tickets/{ticket}/comments` | body (`StoreTicketCommentRequest`): `body` (required, string) | `201` `TicketCommentResource` | `author_role: owner` server-set. |

### Reports

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/reports/dashboard` | — | `200` **snake_case** inline JSON: `{income_this_month_cents, outstanding_cents, outstanding_invoice_count, expiring_agreement_count, monthly_income:[{month, income_cents}]×12, expiring_agreements: Agreement[] (raw Eloquent, not a Resource), outstanding_invoices: Invoice[] (raw Eloquent)}` | ⚠ **Not camelCase** — see Conventions note. `expiring_agreements`/`outstanding_invoices` are raw model arrays (all DB columns, snake_case, no Resource transform) — leaks internal column names. |
| GET | `/reports/yearly/{year}` | path: `year` (int) | `200` **snake_case** inline JSON: `{year, monthly_income:[{month, income_cents}]×12, total_income_cents, properties: Property[] (raw Eloquent w/ nested units.agreements.invoices)}` | Same casing/leak caveat. |
| GET | `/reports/yearly/{year}/export` | path: `year` | `501` | **Stub.** `TODO Phase 4`: generate + stream CSV. |

---

## Shell 3 — Tenant (`/api/me/*`, `role:tenant`)

No `not-suspended` gate on this shell (a suspended owner's tenants keep working, per `EnsureNotSuspended`'s docblock).

| Method | Path | Request | Response | Notes |
|---|---|---|---|---|
| GET | `/me/agreement` | query: `expand` | `200` `AgreementResource` / `AgreementWithRefsResource`, or literal JSON `null` (status 200) if none | Resolves the tenant's active agreement, falling back to the most recent non-draft one. The controller deliberately calls `->setData(null)` to force a literal `null` body rather than Symfony's default `{}` coercion. |
| GET | `/me/invoices` | query: `expand` | `200` array of `InvoiceResource` / `InvoiceWithRefsResource`, ordered `due_date desc` | Scoped to invoices on the tenant's own agreements. |
| POST | `/me/invoices/{invoice}/pay` | body: `method` (required, `fpx\|card\|cash\|transfer`) | `201` `{payment: PaymentResource, invoice: InvoiceResource}` | **Mock/simulated round-trip** — no real gateway call. `TODO Phase 3`: create a Billplz bill and redirect. Creates a `successful` `Payment` for `invoice.totalDueCents()` and flips the invoice to `paid` immediately. `403` if the invoice isn't the caller's; `422` if already paid. |
| GET | `/me/tickets` | query: `expand` | `200` array of `TicketResource` / `TicketWithRefsResource` | Scoped to `reporter_id === auth user`. |
| GET | `/me/tickets/{ticket}` | query: `expand` | `200` `TicketResource` / `TicketWithRefsResource` | `403` if not the caller's ticket. |
| POST | `/me/tickets` | body, validated inline: `category` (required), `priority` (required), `title` (required, ≤100), `description` (required) | `201` `TicketResource` | **No `unitId` in the body** — the unit is derived server-side from the tenant's active agreement; `unitId`/`reporterId`/`reporterRole` in the request are ignored even if sent. `422` if the tenant has no active agreement. |
| POST | `/me/tickets/{ticket}/comments` | body (`StoreTicketCommentRequest`): `body` (required) | `201` `TicketCommentResource` | `403` if not the caller's ticket. `author_role: tenant`. |
| GET | `/me/profile` | — | `200` inline JSON: `{id, name, email, phone, personal, emergencyContact}` | Not a Resource class — hand-built in the controller. `email` is not editable via the PATCH below (login identity). |
| PATCH | `/me/profile` | body (`UpdateTenantProfileRequest`, all `sometimes`/`nullable`): `name` (≤255), `phone` (≤30), `personal` (array → `personal_info`), `emergencyContact` (array → `emergency_contact`) | `200` same shape as GET | No `email`/`status` field accepted — those stay owner/admin-controlled. |

---

## Shell 4 — Admin (`/api/admin/*`, `role:admin` [+ `can:<permission>`])

Every write listed here is recorded via `AuditLogger` (`log_name: admin`). Permission keys are `App\Support\AdminPermissions` constants; a super-admin (`is_super_admin: true`) implicitly has all of them (see `AuthUserResource`).

### Permissions & dashboard

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/permissions` | `admins.manage` | — | `200` `{permissions: [{key, preset}] (14 rows), preset: string[] (keys where preset=true)}` | The fixed catalogue — `dashboard.view, owners.view, tenants.view, analytics.view, owners.warn, owners.suspend, owners.plan, support.manage, broadcast.send, settings.channels, settings.flags, admins.manage, audit.view, users.delete`. "Operations preset" = 8 of the 14 (excludes `owners.plan`, `settings.channels`, `settings.flags`, `admins.manage`, `audit.view`, `users.delete`). |
| GET | `/admin/dashboard` | `dashboard.view` | — | `200` inline JSON: `{tiles:{owners:{total,active,suspended}, tenants:{total,invitedPending}, properties, units:{total,occupiedPct}, agreementsActive, agreementsExpiring30d, supportOpen:0 /*SP2*/}, series:{months[12], ownerSignups[12], invoicesIssued[12], invoicesPaid[12], inviteAcceptanceRate[12]}, attention:[{kind, ownerId, ownerName, meta, link}]}` | Platform-wide, counts only — never a money amount. `attention.kind` ∈ `over_cap \| overdue_3plus \| invite_stale_7d \| no_property_7d \| suspended`. `supportOpen` is hardcoded `0`, deferred to a later phase (support tickets don't exist yet). Must be kept in lock-step with `frontend/app/demo/services/admin/dashboard.ts` (mock mirror) by hand — no shared source. |

### Owners

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/owners` | `owners.view` | query: `q` (name/email/businessName like-search), `plan`, `status` (`active\|suspended`), `overdue` (bool — has ≥1 overdue invoice), `overCap` (bool — units over the plan's cap), `page`, `perPage` (default 20) | `200` `{data: AdminOwnerResource[], meta:{page, perPage, total, lastPage}}` | `overCap` filtering happens in-memory after the DB query (loads all matches then slices) — not indexed/DB-level. |
| GET | `/admin/owners/{owner}` | `owners.view` | — | `200` `AdminOwnerResource` | `404` if id isn't role `owner`. |
| GET | `/admin/owners/{owner}/properties` | `owners.view` | — | `200` array of `AdminPropertySummaryResource` | No ownership/utilities/documents/prices — summary only. |
| GET | `/admin/owners/{owner}/tenants` | `owners.view` | — | `200` array of `AdminTenantResource` | |
| GET | `/admin/owners/{owner}/history` | `owners.view` | — | `200` array of audit-entry-shaped rows (`AdminOwnerResource`... actually `AuditEntryResource[]`) **plus one synthetic `owner.signup` row appended** with `id: "signup-{ownerId}"`, `actorId: null`, `after: {planTier}` | The synthetic signup row is not a real `Activity` record — fabricated so the timeline has a start event even for owners who predate audit logging. |
| POST | `/admin/owners/{owner}/warn` | `owners.warn` | body (`WarnOwnerRequest`): `template` (required, one of `App\Notifications\OwnerWarning::TEMPLATES`), `suspendOn` (required, `Y-m-d`, must be after today), `extraLine` (nullable, ≤500) | `204` | Sends an `OwnerWarning` notification — **mail only in SP1** (`via()` returns `['mail']`; queued, so the `queue-worker` container must be running; SP2 adds WhatsApp/SMS channels without touching callers) — and logs `owner.warned` with the composed warning text in `after`. |
| POST | `/admin/owners/{owner}/suspend` | `owners.suspend` | body (`SuspendOwnerRequest`): `reason` (required, string, 10–1000 chars) | `200` `AdminOwnerResource` | `409` if already suspended. Sets `suspended_at`, `suspension_reason`. Logs `owner.suspended` with the reason. This flips `not-suspended` for the owner shell going forward. |
| POST | `/admin/owners/{owner}/unsuspend` | `owners.suspend` | — | `200` `AdminOwnerResource` | `409` if not suspended. Logs `owner.unsuspended`. |

### Analytics

Read-only platform analytics (marketing-site funnel + leads). Counts only — never money, never PII beyond a lead's email.

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/analytics/overview` | `analytics.view` | query (`AnalyticsRangeRequest`): `from`/`to` (nullable, `Y-m-d`, `to` defaults to now, `from` defaults to `to - 29 days`; range capped at 366 days, else `422`) | `200` `{range:{from, to, days}, tiles:{views, visitors, newVisitors, demoEntries, leads, registrations, conversionPct}, series:{days[], views[], visitors[], leads[], registrations[]}, funnel:{visitors, demo, leads, registered}, topPages:[{path, views}] (top 10), referrers:[{referrer, visitors}] (top 10, `null` referrer bucketed as `"direct"`)}` | Bucketing/aggregation is done in PHP over the range's rows (same style as the owner dashboard) — fine at this scale, not SQL-side. `conversionPct` = `round(registrations / visitors * 100)`, `0` if no visitors. |
| GET | `/admin/analytics/leads` | `analytics.view` | query: `q` (email like-search), `source` (`waitlist\|demo\|register`), `converted` (bool), `page`, `perPage` (default 20, clamped `min(100, max(1, n))`) | `200` `{data: AdminLeadResource[], meta:{page, perPage, total, lastPage}}` | Ordered `last_seen_at desc, id asc`. Each row decorated with `pageViews`/`demoEntered` from the lead's `visitor_id` events (one query each per page, not per row). |
| GET | `/admin/analytics/leads/export.csv` | `analytics.view` | same filters as `leads` (minus pagination) | `200` streamed `text/csv; charset=UTF-8`, filename `roofly-leads-{Ymd-His}.csv` | Columns: `email, source, firstSeenAt, lastSeenAt, pageViews, demoEntered, convertedOwnerName`. Streams in chunks of 500. Logs `analytics.exported` (`AuditLogger::ANALYTICS_EXPORTED`) with the applied filters in `after`. Route is registered **before** `leads/{lead}` so `export.csv` isn't swallowed by the wildcard. |
| GET | `/admin/analytics/leads/{lead}` | `analytics.view` | — | `200` `AdminLeadResource` `+ {events: LeadEventResource[]}` (latest 20 by `created_at`, only if the lead has a `visitor_id`) | Same per-lead decoration as the list. |

### Tenants

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/tenants` | `tenants.view` | query: `q` (name/email/phone like-search), `status`, `ownerId` (matches invited-by OR agreement-on-owner's-property), `page`, `perPage` (default 20) | `200` `{data: AdminTenantResource[], meta}` | |
| GET | `/admin/tenants/{tenant}` | `tenants.view` | — | `200` `AdminTenantResource` | `404` if not a tenant. |
| POST | `/admin/tenants/{tenant}/resend-invite` | `tenants.view` | — | `204` | `409` unless `tenant.status === 'invited'`. Bumps `invited_at`. **No mail sent** — `TODO Phase 2`: dispatch magic-link invite notification. Logs `tenant.invite_resent`. |

### Admin users

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/admins` | `admins.manage` | — | `200` array of `AdminUserResource` | Not paginated (unlike owners/tenants lists). |
| POST | `/admin/admins` | `admins.manage` | body (`StoreAdminRequest`): `name` (required, ≤255), `email` (required, email≤255, unique), `permissions` (required, present array, each ∈ `AdminPermissions::keys()`), `isSuperAdmin` (sometimes, bool) | `201` `AdminUserResource` | **Authorization gate inside the FormRequest**: only an existing super-admin may set `isSuperAdmin: true` on the new record — otherwise the request itself is denied (403 via `authorize()` returning false), before validation. Creates the user with `password: null` (no password set yet) and immediately mints + emails an invite. |
| PATCH | `/admin/admins/{admin}` | `admins.manage` | body (`UpdateAdminRequest`, all `sometimes`): `permissions` (array, each valid key), `isSuperAdmin` (bool), `disabled` (bool) | `200` `AdminUserResource` | Same super-admin-only gate on touching `isSuperAdmin`. `422` if trying to disable yourself. `422` if the change would leave zero enabled super-admins (demoting or disabling the last one). Disabling revokes all Sanctum tokens (`$admin->tokens()->delete()`). Logs `admin.permissions_changed` (with before/after snapshots) and/or `admin.disabled`/`admin.enabled` depending on which fields changed. |
| POST | `/admin/admins/{admin}/resend-invite` | `admins.manage` | — | `204` | `409` if the admin already has `first_login_at` set (already accepted). Voids any live invite token and mints a new one. Logs `admin.invite_sent`. |

### Audit

| Method | Path | `can:` | Request | Response | Notes |
|---|---|---|---|---|---|
| GET | `/admin/audit` | *(no `can:` on the route — see notes)* | query: `page`, `perPage` (default 25, max 100), `actorId`, `action`, `subjectType` (`user` mapped to `App\Models\User::class`, else passed through raw), `subjectId`, `from`/`to` (`Y-m-d`, inclusive day bounds) | `200` `{data: AuditEntryResource[], meta}` | Every admin can call this (no route-level `can:`), but the **query is scoped in-controller**: without `audit.view`, results are forced to `causer_id = caller`, even if an `actorId` filter asks for someone else's entries (returns empty rather than someone else's rows). With `audit.view`, sees everything. |
| GET | `/admin/audit/export.csv` | `audit.view` | same filters as above | `200` streamed `text/csv; charset=UTF-8`, filename `roofly-audit-{Ymd-His}.csv` | Columns: `id, createdAt, action, actorName, subjectType, subjectId, subjectName, reason, before, after` (`before`/`after` JSON-encoded per cell). Streams in chunks of 500. |

---

## Shell 5 — Webhooks

| Method | Path | Middleware | Request | Response | Notes |
|---|---|---|---|---|---|
| POST | `/webhooks/billplz` | `api`, `touch-active` (explicitly `withoutMiddleware('auth:sanctum')`) | raw body, stored verbatim as `payload` on a new `PaymentWebhook` row | `200` plain text `"OK"` | **No signature verification yet** — `TODO Phase 3`: verify `X-Signature` header, dispatch a `ProcessBillplzWebhook` job. Currently just persists the payload and immediately marks it `processed_at: now()`; no actual invoice/payment state is touched by this endpoint today. |

---

## Resource shapes appendix

Key lists below are read directly from each `toArray()`. `?` marks a value that can be `null`.

- **`AgreementResource`** — `id, unitId, tenantId, startDate (Y-m-d), endDate (Y-m-d), rentAmount, depositAmount, lateFee?, rentDueDay, status?, createdAt?`
- **`AgreementWithRefsResource`** — `{agreement: AgreementResource, unit: UnitResource?, property: PropertyResource?, tenant: TenantResource?}`
- **`AuthUserResource`** — 12 keys, in this order: `id, name, email, phone?, role?, permissions: string[] (admin only — [] for owner/tenant), isSuperAdmin: bool, hasPassword: bool (false only for a Google-only owner with no password set), avatarUrl?, onboardedAt? (ISO8601 — owner only, else null), purposes: string[] (owner only, else []), checklistDismissedAt? (ISO8601 — owner only, else null)`
- **`InvoiceResource`** — `id, agreementId, invoiceNumber, amount, lateFee?, dueDate (Y-m-d), status?, createdAt?`
- **`InvoiceWithRefsResource`** — `{invoice, agreement?, unit?, property?, tenant?, payments: PaymentResource[]}`
- **`OwnerAccountResource`** — `{profile:{id, name, email, phone?, photoUrl: null /*Phase 4*/, businessName?, bankAccountLast4?}, preferences:{locale, theme, moneyLocale}, notifications:{events:{...5 booleans}, channels:{email, whatsapp, in_app}}, planTier?}`
- **`PaymentResource`** — `id, invoiceId, amount, method?, status?, paidAt?, reference?, createdAt?`
- **`PropertyCoOwnerResource`** — `id, name, sharePct: float, isPrimary: bool`
- **`PropertyResource`** — `id, ownerId, name, internalLabel?, type?, purpose ("rental"|"own_stay"|"investment", defaults "rental"), notes?, address, city, state, postcode, yearBuilt?, builtUpSqft?, landSqft?, bedrooms?, bathrooms?, parkingLots?, furnishing?, ownership? (raw array), utilities? (raw array), coOwners: PropertyCoOwnerResource[], createdAt?`
- **`TenantResource`** — `id, name, email, phone?, status, invitedAt?, createdAt?, personal? (raw array), emergencyContact? (raw array)`
- **`TicketCommentResource`** — `id, ticketId, authorId, authorRole?, body, createdAt?`
- **`TicketResource`** — `id, unitId, reporterId, reporterRole?, category?, priority?, title, description, status?, createdAt?, updatedAt?, resolvedAt?`
- **`TicketWithRefsResource`** — `{ticket, unit?, property?, reporter: TenantResource? (null for owner-reported tickets), comments: TicketCommentResource[] (sorted by created_at)}`
- **`UnitResource`** — `id, propertyId, label, bedrooms?, bathrooms?, sqft?, status?, createdAt?`
- **`Admin\AdminLeadResource`** — `id, email, source ("waitlist"|"demo"|"register"), firstSeenAt, lastSeenAt, pageViews: int, demoEntered: bool, convertedUserId?, convertedOwnerName?` (`pageViews`/`demoEntered` are controller-set attributes, not model columns — see the Analytics endpoints above)
- **`Admin\AdminOwnerResource`** — `id, name, email, phone?, businessName?, planTier ("free" default), unitsUsed, unitsCap (int? — null = unlimited), status ("active"|"suspended"), suspendedAt?, suspensionReason?, createdAt?, lastActiveAt?, counts:{properties, units, unitsOccupied, tenants, agreementsActive, agreementsExpiring30d, invoicesOverdue, ticketsOpen}`
- **`Admin\AdminPropertySummaryResource`** — `id, name, address:{line, postcode, city, state}, type?, unitsTotal, unitsOccupied, createdAt?`
- **`Admin\AdminTenantResource`** — `id, name, email, phone?, status, ownerId?, ownerName?, propertyName?, unitLabel?, invitedAt?, acceptedAt?, createdAt?` (`ownerId`/`ownerName` prefer the direct inviter, fall back to the most-relevant agreement's property owner)
- **`Admin\AdminUserResource`** — `id, name, email, permissions: string[], isSuperAdmin: bool, status ("disabled"|"invited"|"active"), lastActiveAt?, createdAt?`
- **`Admin\AuditEntryResource`** — `id (string), action, actorId?, actorName?, subjectType? (lowercased class basename, e.g. "user"), subjectId?, subjectName? (only populated when subject is a User), before: object, after: object, reason?, ip?, createdAt?`
- **`Admin\LeadEventResource`** — `id, event, path?, props: object, createdAt?` (`props.email`, if present, is redacted to the lead's own email via `LeadEventResource::forLead($lead->email)` — never surfaces a different email another visitor on the same device may have typed)

---

## Uncertain / worth owner verification

- `Owner\ReportController@dashboard` and `@yearly` return **snake_case** keys and raw Eloquent model arrays (`expiring_agreements`, `outstanding_invoices`, `properties`), breaking the stated camelCase convention. Need to confirm whether the frontend API adapter (`frontend/app/services/api/reports.ts`) transforms these client-side, or whether this is a live contract mismatch — the doc for this needs a look at `docs/frontend/API-MAP.md`'s reports section.
- `POST /api/properties/{property}/co-owners` (single-row add) validates inline in the controller and does **not** enforce the sum=100/exactly-one-primary invariant that the sync (`PUT`) endpoint and `PUT /properties/{id}` both enforce — unclear if that's intentional (add-then-fix-up-with-sync workflow) or a gap.
- `PUT /tickets/{ticket}` (owner ticket update) validates inline rather than via a FormRequest class, unlike almost every other write endpoint — noted for consistency, not necessarily a bug.
- The `role:owner`/`role:tenant`/`role:admin` failure path (`EnsureRole`) returns a bare `{message}` via `abort_if(..., 403)`, which is Laravel's default abort body — worth confirming this is the intended shape versus something more structured, since `EnsureNotSuspended` on the neighboring middleware deliberately uses a `{code, message}` shape.
