# Backend API contract alignment (Option A) — design

**Date:** 2026-07-21
**Scope:** Backend only. Make the existing Laravel API serve exactly the contract the frontend types + services define, proven by feature tests. Zero frontend changes.
**Out of scope:** frontend integration (auth wiring, mock flip), magic-link UI, real Billplz flow, file uploads, RabbitMQ jobs, automatic tenant-status transitions, `/reports/*` endpoints (unused by the frontend — see §8).

---

## 1. Problem

The backend (scaffolded in `b373e68` + `ad854db`) mirrors the frontend service layer route-for-route, but returns raw Eloquent models (snake_case) and validates snake_case input. The frontend contract is camelCase with several true renames. Additionally, four latent defects block integration:

1. **Role middleware can never pass** — routes use Spatie's `role:` middleware, but the alias is not registered in `bootstrap/app.php` and no user is ever assigned a Spatie role (users only have the `role` enum column).
2. **Invited tenants are invisible** — `TenantController::index` requires an agreement chain to the owner; freshly invited tenants have none.
3. **No tenant status** — frontend expects a writable 4-state `status` (`invited|active|notice_given|moved_out`); users table has only `invited_at`.
4. **CORS unpublished** — no `config/cors.php`, so `supports_credentials` is false and cookie-based Sanctum cannot work from `localhost:3000`.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Contract transformation | **Option A** — API Resource layer on the backend; frontend types untouched |
| Tenant status | Writable `status` enum column on `users`, default `invited` |
| Input handling | FormRequest per write endpoint: validates **camelCase** keys, exposes `toModelAttributes(): array` mapping to snake_case columns |
| JSON blob interiors | Stored **camelCase verbatim** (opaque pass-through). Frontend types (`PropertyOwnership`, `PropertyUtilities`, `TenantPersonal`, `TenantEmergencyContact`, preference shapes) are the source of truth. Backend code reading inside blobs uses camelCase keys — documented convention |
| Seed data | `DemoSeeder` ports the frontend mock world (content-identical; fixed deterministic UUIDs as constants — parity by content, not id) |
| Role middleware | Replace Spatie route middleware with a small `EnsureRole` middleware reading the `role` enum column (`role:owner`, `role:tenant`). Spatie stays installed but unused for now |

## 3. Response layer — API Resources

New `app/Http/Resources/`: `PropertyResource`, `PropertyCoOwnerResource`, `UnitResource`, `TenantResource` (User → frontend `Tenant`), `AgreementResource`, `InvoiceResource`, `PaymentResource`, `TicketResource`, `TicketCommentResource`, `AuthUserResource`, plus envelope resources (§4). All controllers return Resources; never raw models.

Rules:

- **camelCase keys**, field-for-field with `frontend/app/types/*.ts`.
- **Renames:** `amount_cents→amount`, `late_fee_cents→lateFee` (invoices); `rent_amount_cents→rentAmount`, `deposit_amount_cents→depositAmount`, `late_fee_cents→lateFee` (agreements); `share_pct→sharePct`, `is_primary→isPrimary` (co-owners); `personal_info→personal`, `emergency_contact→emergencyContact` (tenants); `amount_cents→amount` (payments).
- **Dates:** date columns emit `YYYY-MM-DD` (`dueDate`, `startDate`, `endDate`); timestamps emit ISO 8601 (`createdAt`, `updatedAt`, `invitedAt`, `paidAt`, `resolvedAt`).
- **JSON blobs** pass through verbatim.
- **`JsonResource::withoutWrapping()`** in `AppServiceProvider` — frontend expects bare arrays/objects, never `{ data: … }`.
- `TenantResource` emits exactly the frontend `Tenant` shape: `id, name, email, phone, status, invitedAt, createdAt, personal?, emergencyContact?` — no auth fields.
- `PropertyResource` always includes `coOwners` (frontend type requires ≥1 entry) and `ownerId` = the user id of the primary co-owner.
- `AuthUserResource`: `{id, name, email, phone, role}` (phone nullable).
- `OwnerAccountResource` emits the frontend `OwnerAccount` envelope: `{profile: {id, name, email, phone, photoUrl?, businessName?, bankAccountLast4?}, preferences, notifications, planTier}` — `preferences`/`notifications` are the camelCase JSON blobs verbatim (defaults filled server-side when null: `{locale: "en", theme: "system", moneyLocale: "en-MY"}` and all notification events/channels enabled). Returned by `GET /account` and all three `PATCH /account/*` endpoints.
- `GET /plans` returns `Plan[]`: `{tier, priceRm, unitsCap ("unlimited" or number), description}` — static config, no table.

## 4. `expand` envelopes (WithRefs)

The frontend's `listWithRefs` calls expect **wrapper objects**, not embedded relations:

| Call | Envelope per item |
|---|---|
| `GET /agreements?expand=unit,property,tenant` | `{agreement, unit\|null, property\|null, tenant\|null}` |
| `GET /invoices?expand=agreement,unit,property,tenant,payments` | `{invoice, agreement\|null, unit\|null, property\|null, tenant\|null, payments: []}` |
| `GET /tickets?expand=unit,property,reporter,comments` | `{ticket, unit\|null, property\|null, reporter\|null, comments: []}` (`reporter` null when `reporterRole === "owner"`) |
| `GET /tickets/{id}?expand=…` | single ticket envelope |
| `GET /me/agreement?expand=unit,property,tenant` | single agreement envelope or `null` |
| `GET /me/invoices?expand=…` | array of invoice envelopes |
| `GET /me/tickets?expand=…` | array of ticket envelopes |

Implementation: when `expand` is present, controllers eager-load relations and return `*WithRefsResource` envelopes built from the base Resources. Without `expand`, plain Resource collections. Unknown expand values are ignored (frontend only ever sends the fixed strings above). Comments sort ascending by `createdAt`.

`GET /me/agreement` selection rule (mirrors frontend mock logic): prefer the `active` agreement; else the most recent non-`draft` by `startDate`; else `null` (HTTP 200 with `null` body, not 404).

## 5. Input layer — FormRequests

One FormRequest per write endpoint replacing all inline `$request->validate()`:

`StorePropertyRequest`, `UpdatePropertyRequest`, `SyncCoOwnersRequest`, `StoreUnitRequest`, `UpdateUnitRequest`, `InviteTenantRequest`, `UpdateTenantRequest`, `StoreAgreementRequest`, `UpdateAgreementRequest`, `UpdateInvoiceStatusRequest`, `RecordPaymentRequest`, `StoreTicketRequest`, `UpdateTicketStatusRequest`, `StoreTicketCommentRequest`, `UpdateAccountProfileRequest`, `UpdateAccountPreferencesRequest`, `UpdateAccountNotificationsRequest`, `UpdateTenantProfileRequest` (tenant `/me/profile`). Auth endpoints keep inline validation — their payloads (`email`, `password`, `name`, `phone`) have no casing mismatch, so a FormRequest adds nothing.

- Validate **camelCase keys** (`builtUpSqft`, `rentAmount`, `rentDueDay`, `emergencyContact`, …).
- `toModelAttributes(): array` returns snake_case column data, including the money renames.
- Domain invariants enforced here: co-owners sum(`sharePct`) === 100 and exactly one `isPrimary`; `rentDueDay` 1–28; `postcode` 5 digits; `state` in the `MalaysianState` enum; ticket status transitions per `TICKET_TRANSITIONS` (new→in_progress|resolved; in_progress→resolved|new; resolved→reopened; reopened→in_progress|resolved).
- Write payload shapes come from the frontend `*Input` / `*Update` types verbatim (e.g. `PaymentInput = {invoiceId, amount, method, paidAt, reference?}`; server derives `authorId`/`reporterId` from the authenticated user where the frontend sends it redundantly — accept and ignore client-sent ids in `/me/*` context, trusting the session).

## 6. Routes — additions & changes

- **Add flat unit routes** (owner-guarded, ownership-checked through `unit.property.owner_id`): `GET /units`, `GET /units/{unit}`, `PATCH /units/{unit}`, `DELETE /units/{unit}`. Nested `properties/{property}/units` stays for list-by-property + create.
- **Add `POST /tenants/invite`** — the frontend's single create+invite call (`{name, email, phone}` → tenant with `status=invited`, `invited_at=now`, `invited_by=owner`). The split `POST /tenants` + `POST /tenants/{id}/invite` routes may remain but are not part of the tested contract.
- **`PATCH /tickets/{id}/status`** body: `{status}` (exists; gets FormRequest + transition validation).
- Replace `role:` middleware registration: alias `role` → `App\Http\Middleware\EnsureRole` in `bootstrap/app.php`.

## 7. Schema — one migration

- `users.status` — nullable enum `invited|active|notice_given|moved_out`. **Null for owners/admins**; always set for tenants (`invited` on invite). Writable via `PATCH /tenants/{id}`. Only `TenantResource` serializes it.
- `users.invited_by` — nullable uuid FK → `users`, set by `POST /tenants/invite`. `TenantController::index` scope becomes: `role=tenant AND (invited_by = me OR has agreement on my property)`.

## 8. Reports

`GET /reports/*` endpoints are **unused** — `useDashboard`/`useReports` compute everything client-side from `useProperties().list()`, `useUnits().list()`, `useTenants().list()`, `useAgreements().list()`, `useInvoices().listWithRefs()`, `useTickets().list()`; CSV export is client-side (`utils/csv.ts`). ReportController stays as-is, untested, out of contract. (Revisit when list sizes justify server aggregation.)

## 9. Auth & CORS

- `GET /auth/me` and login/register responses return `AuthUserResource`.
- CORS: publish `config/cors.php` — paths `['api/*', 'sanctum/csrf-cookie']`, `supports_credentials: true`, `allowed_origins: [env('FRONTEND_URL')]`.
- Sanctum SPA cookie mode (already half-wired: `statefulApi()`, `SANCTUM_STATEFUL_DOMAINS`, frontend sends `credentials: "include"`).

## 10. Seed data

`DemoSeeder` (invoked from `DatabaseSeeder`) porting `frontend/app/mocks/*`: one owner (+ account prefs), Aminah + other tenants (statuses incl. `notice_given`), properties with co-owners/ownership/utilities blobs, units, agreements, invoices + payments, tickets + comments. Fixed UUID constants; content matches mocks so the integration phase can compare screen-by-screen.

## 11. Testing — contract feature tests

PHPUnit feature tests per entity, acting as the **contract gate** for the integration phase:

- Response key sets are exactly the frontend type's fields (camelCase), spot-checking rename values (`amount === amount_cents`).
- Date/timestamp formats as §3.
- `expand` envelopes match §4 shapes, including `null` refs and empty arrays.
- camelCase writes validate and persist (and snake_case writes are rejected/ignored — proving no accidental dual contract).
- Invariants: co-owner sum/primary, `rentDueDay`, ticket transitions.
- Authorization: owner A cannot read owner B's resources; tenant routes scoped to the session tenant; `role:` guard blocks cross-role access.
- `/me/agreement` selection rule incl. the `null` case.

Runner: existing PHPUnit setup (sqlite in-memory if configured; else MySQL service). No commits until the user reviews the finished build.

---

## 12. Integration notes for the next phase (recorded from final whole-branch review)

- **Tenant-shell writes currently call owner routes.** In mock mode the tenant pages reuse owner service methods: profile via `useTenants().get/update` (`/tenants/{id}`), pay via `useInvoices().recordPayment` (`/invoices/{id}/payments`), ticket detail/comment/create via owner `/tickets/*`. Against the real API these 403 under `role:owner`. The backend's `/me/profile`, `/me/invoices/{id}/pay`, `/me/tickets` (store/show/comments) routes are built and tested for exactly these flows — the frontend integration phase must add tenant-variant service methods (or role-aware paths) for: PayInvoiceModal, ReportIssueModal, tenant ticket detail (`getWithRefs`/`addComment`), and tenant profile view/edit.
- **Co-owner edits** persist through `PATCH /properties/{id}` (`coOwners` in body) — implemented server-side with the same invariants as the `PUT …/co-owners` sync route. The PUT route remains as the canonical explicit sync endpoint.
- **Dev proxy:** the nginx service expects php-fpm (`fastcgi_pass backend:9000`) but the dev override runs `php artisan serve` (HTTP) — for local integration, point the frontend at the backend container directly or align the override with php-fpm.
