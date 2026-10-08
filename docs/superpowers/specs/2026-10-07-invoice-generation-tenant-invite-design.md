# Invoice generation + tenant invite delivery — design

**Date:** 2026-10-07 · **Status:** approved in chat (back-fill policy chosen by owner), implementing on `feature/admin-backoffice` · **Scope:** backend + the thin frontend accept page. Closes the two gaps that stop a real owner using UAT: no invoice is ever created, and an invited tenant never receives anything.

## 1. Goal

A beta owner who registers, adds a property and unit, invites a tenant and activates an agreement must end up with (a) a rent invoice the tenant can see and pay, rolling forward every month without anyone touching the app, and (b) a tenant who receives an email, sets a password and lands in `/tenant`.

## 2. Decisions

| Question | Decision | Why |
|---|---|---|
| Past months when an agreement with a past `start_date` is activated | **Next due date onward, no back-fill.** | Chosen by the owner 2026-10-07. A new owner never sees instant overdue for rent already collected outside Roofly; nothing is fabricated. The owner can add historical records later when a "past invoice" tool exists (out of scope). |
| Where generation is triggered | Controller layer, not a model observer | `DemoSeeder` and factories create agreements directly and must keep seeding their own curated invoice history; only real owner actions generate. |
| Generation horizon | Due dates up to **30 days ahead** | Mirrors the demo layer's `today + 30 days` cutoff so the tenant home's "rent due" hero has something to show before the due date. |
| Overdue | `pending` → `overdue` the day after `due_date`, late fee snapshotted from `agreement.late_fee_cents` once | ADR-006 / PROJECT.md § 6 ("day after due, if unpaid: late fee auto-applied"). Flat fee, same as demo. |
| Invoice numbering | Global `INV-NNNN`, chronological by creation | Matches the seeder and `invoice_number` unique index. |
| Idempotency | Unique index `invoices(agreement_id, due_date)` + generator skips existing rows (any status, incl. cancelled) | Daily command and controller hooks can overlap safely. |
| Termination | On `status → terminated`: cancel this agreement's `pending` invoices with `due_date > today` | Stops charging after the tenant leaves; the already-due one stays collectable. `expired` does nothing (end date already capped generation). |
| Rent / due-day edits | Existing invoices untouched; only future, not-yet-generated periods pick up the change | Simple, auditable; owner can cancel + regenerate via status if needed later. |
| Tenant invite transport | Email only | WhatsApp is Phase 6. |
| Tenant invite token | New `tenant_invites` table mirroring `admin_invites` (`user_id`, `token_hash`, `expires_at` 7 days, `accepted_at`) | Keeps customer and admin flows separate (spec 2026-08-23 § 3.4 rule: admins never reset through the customer flow, and vice versa). The 60-minute password-broker expiry is too short for an invite. |

## 3. Invoice generation

### 3.1 `App\Services\InvoiceGenerator`

```php
generateFor(Agreement $a, ?CarbonInterface $today = null): Collection   // new Invoice rows
cancelFuture(Agreement $a, ?CarbonInterface $today = null): int         // pending, due_date > today → cancelled
markOverdue(?CarbonInterface $today = null): int                        // all agreements; pending, due_date < today → overdue + late fee
```

`generateFor` rules, with `today = startOfDay()`:

1. Only `status = active`. Draft / expired / terminated → empty.
2. Candidate due dates: the `rent_due_day` of each month, starting from the month of `max(start_date, today)`; if that month's due day is before the floor, start with the next month.
3. Stop after `min(end_date, today + 30 days)`.
4. Skip any due date that already has an invoice for this agreement (any status).
5. Each new row: `amount_cents = rent_amount_cents`, `late_fee_cents = 0`, `status = pending`, `invoice_number` = next global sequence, computed inside a `DB::transaction` with a retry on duplicate-key.

So an owner activating on 7 Oct with due day 1 gets `2026-11-01` (not October); activating on 1 Oct gets `2026-10-01` (`pending` today, `overdue` tomorrow if unpaid). Agreements starting in the future get nothing until the start month is within 30 days.

### 3.2 Hooks (owner controller)

- `AgreementController@store` — after create, if active → `generateFor`.
- `AgreementController@update` — after update: if status changed to `active` → `generateFor`; if changed to `terminated` → `cancelFuture`. Response shape unchanged.

### 3.3 Command + schedule

`php artisan invoices:roll {--date=}` — for every active agreement: `generateFor`, then one `markOverdue` pass. Scheduled daily at 00:30 (Asia/Kuala_Lumpur, the app timezone) in `routes/console.php`. `--date` exists for tests and manual replays.

### 3.4 Migration

`2026_10_07_000001_add_agreement_due_date_unique_to_invoices` — `unique(['agreement_id', 'due_date'])`. Seeder data already satisfies it (one invoice per due day per agreement).

## 4. Tenant invite

### 4.1 Backend

- Migration `2026_10_07_000002_create_tenant_invites_table` + `App\Models\TenantInvite` (copy of `AdminInvite` incl. `isUsable()`).
- `App\Notifications\TenantInvite` (`ShouldQueue`, mail): bilingual like `AdminInvite`, action link `FRONTEND_URL/auth/accept-invite?token=…&email=…`, states the inviting owner's name and that the link lasts 7 days.
- `App\Support\TenantInvites::send(User $tenant, User $invitedBy)` — voids live tokens, creates a new one, notifies. Used by:
  - `Owner\TenantController@invite` (replaces the TODO).
  - `Admin\TenantController@resendInvite` (replaces the TODO; audit entry unchanged).
- `POST /auth/accept-invite` (`AcceptTenantInviteController`, public, `throttle:5,1`): body `token`, `email`, `password` (≥8, `confirmed`). Looks up by `sha256(token)`; 422 `{errors:{token:[…]}}` if missing / expired / used / user not a tenant / email mismatch. On success: set password, `status = active`, `first_login_at`, `accepted_at`; log in (web guard) and return `{user, token}` exactly like `reset-password`.
- `PasswordResetController@reset`: when the user is a tenant with `status = invited`, also set `status = active` (covers tenants who use "Forgot password" instead of the invite link).
- `MagicLinkController` routes stay as they are (501) — not wired to anything.

### 4.2 Frontend

- Contract `AuthAdapter.acceptInvite({token, email, password})` → demo: like `resetPassword` (persists a tenant user for that email); API: `POST /auth/accept-invite`.
- Store `acceptInvite(...)`.
- `pages/auth/accept-invite.vue` — copy of `reset-password.vue` with its own copy (`auth.acceptInvite.*`, en + ms): "Welcome to Roofly — set your password". Email is prefilled from the query and read-only. Success → `/tenant`.
- `TenantInviteModal` help text (en + ms) → "They'll get an email with a link to set their password. It expires in 7 days."

### 4.3 Owner backup: copy / share the link (added 2026-10-07, approved in chat)

Email can fail silently (spam, typo, provider outage). Because only a token hash is stored, a plain link exists exactly once — when it is issued — so the backup works like this:

- `POST /tenants/invite` returns `{tenant, inviteUrl, inviteExpiresAt}`; `TenantInviteModal` stays open on a "sent" panel showing that same link with **Copy link** and **Send via WhatsApp** (`wa.me/<digits>?text=…` with a prefilled bilingual-safe message; `0`-prefixed Malaysian numbers become `60…`).
- Tenant detail page, while `status = invited`: **Copy invite link** / **Send via WhatsApp** call `POST /tenants/{tenant}/invite-link`, which mints a **fresh** link (voiding earlier ones, the emailed one included), sends **no** mail, and is `409` once the tenant is active. The toast says earlier links no longer work.
- Admin resend is unchanged (email only).
- `TenantInvites` is split into `issue()` (token + url, no mail) and `send()` (issue + email); both return `{invite, url}`.

### 4.4 Tenant onboarding (added 2026-10-07, approved in chat)

Decisions: **mandatory, core fields required** (phone, MyKad number, emergency contact name + phone; everything else optional) and **home card + Profile second in the nav**.

- `users.onboarded_at` is reused for tenants. `AuthUserResource.onboardedAt` is exposed for owners and tenants (still `null` for admins). Migration `2026_10_07_000003` back-fills non-invited tenants with `COALESCE(first_login_at, created_at)`; pending invites stay `null` and meet the screen right after `accept-invite`.
- `PATCH /me/onboarding` (`CompleteTenantOnboardingRequest`) saves the profile columns and stamps `onboarded_at` idempotently, returning `AuthUserResource`.
- `middleware/auth.global.ts` gains the tenant twin of the owner guard: `onboardedAt` falsy → `/tenant/onboarding`; onboarded tenants can't revisit it.
- `/tenant/onboarding` (layout `onboarding`): three steps — About you (name, phone, MyKad, DOB, nationality defaulting to Malaysian), Work (optional), Emergency contact — with a segment progress bar, Back/Continue, per-step validation, and Finish. No skip link.
- Tenant home shows `ProfileNudgeCard` at the top while `tenantProfileGaps()` reports a missing core field; it links to `/tenant/profile?edit=1`. The sidebar order becomes Home, Profile, Agreement, Payments, Issues.
- Demo: stock tenant login is pre-onboarded; `acceptInvite` is not, so the flow is demoable from the owner's invite link.
- **MyKad input (2026-10-07):** no dashes to type. `utils/mykad.ts` / `App\Support\MyKad` accept 12 digits in any spacing, store the dashed canonical form, format live as the tenant types, and derive `dateOfBirth` from `YYMMDD` (current century unless that lands in the future). Applied on the onboarding page, the tenant profile form and the owner's personal form; the backend repeats it in all three write requests so API clients behave the same.

## 5. Out of scope (recorded so nobody expects it)

Back-filling historical invoices or payments · owner-side "resend email" button (admin has one; the owner's backup is the copy / WhatsApp link in § 4.3, and the tenant can also use Forgot password) · WhatsApp · rent reminders · automatic agreement expiry · Billplz · changing already-generated invoices when rent is edited · demo-adapter regeneration on `demoAgreements.create` (demo invoices stay precomputed from `agreementsMock`; documented in API-MAP).

## 6. Tests

Backend (`php artisan test`):
- `InvoiceGeneratorTest` — floor is next due date (7 Oct / due 1 → first 2026-11-01), due day today is included, 30-day horizon, end-date cap, draft skipped, idempotent re-run, future start outside horizon → none, `cancelFuture`, `markOverdue` sets fee once and ignores paid.
- `AgreementContractTest` additions — store active creates invoices, store draft creates none, update to active creates, update to terminated cancels future pending.
- `InvoicesRollCommandTest` — rolls forward and marks overdue for a fixed `--date`.
- `TenantInviteTest` — owner invite sends `TenantInvite` with the right URL; admin resend sends again and voids the old token; accept sets password/status/first_login and returns `{user, token}`; expired / used / wrong email → 422; reset flow flips `invited → active`.

Frontend: typecheck (no new Vitest — the page is UI only).

## 7. Docs to update with the implementation

`docs/backend/API-SPEC.md` (Agreements side-effects, new Invoices § "generation", Auth `accept-invite`, Tenants invite now mails, Scheduler) · `docs/frontend/API-MAP.md` (Tenants row, Payments row, new Accept-invite row) · `docs/frontend/MOCK-POC.md` § 5.4 and § 6.7 schema impact (brief) · `.claude/CLAUDE.md` current-state paragraph.
