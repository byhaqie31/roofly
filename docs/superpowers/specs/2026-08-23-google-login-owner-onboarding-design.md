# Google sign-in, owner onboarding, and the getting-started checklist

**Date:** 2026-08-23
**Status:** Approved design, awaiting implementation plan
**Scope:** Owner shell only. Tenants and admins are untouched.

## 1. Goal

Make an owner's first session frictionless: sign in with Google in one click,
answer one question about what they manage, and land on a dashboard that tells
them exactly what to fill in next. Properties that are not rented (own stay,
investment) must stop polluting occupancy, attention, and income views.

Refines [PROJECT.md § 6 Flow 1](../../global/PROJECT.md) (owner onboarding).

### Out of scope

- "How many properties" question (dropped — no consumer for the answer yet)
- Google sign-in for tenants (they arrive via owner invite)
- Google account unlinking
- Email verification for password sign-ups (forgot/reset password IS in scope — § 3.4)
- CSV / bulk import

## 2. Decisions taken during brainstorming

| Decision | Choice |
|---|---|
| Who gets Google sign-in | Owners only |
| Existing password owner signs in with Google | Auto-link by Google-verified email |
| Onboarding question | Single screen, multi-select purpose: rental / own stay / investment |
| Checklist state | Computed from real data, never stored ticks |
| Property purpose | Required per-property enum; pre-selected from the owner's onboarding answer |
| OAuth mechanism | Google Identity Services ID token on the frontend → `POST /auth/google` → Sanctum session. No redirect/callback flow. |

## 3. Google sign-in

### 3.1 Frontend

- `useEnv().features.googleLogin` — true iff `config.public.googleClientId` is
  non-empty **and** not demo. Env var `NUXT_PUBLIC_GOOGLE_CLIENT_ID`. Added as
  one derived field, per the `useEnv` convention.
- `components/auth/GoogleSignInButton.vue` — lazily injects
  `https://accounts.google.com/gsi/client`, calls
  `google.accounts.id.initialize({ client_id, callback })` and
  `renderButton` with our theme (`theme: 'outline'`, `size: 'large'`,
  `width` = container). Re-renders on theme/locale change. Emits
  `credential` (the ID token string). Shows a non-blocking inline error if
  the script fails to load.
- `/auth/login` and `/auth/register` render the button above the email form
  with an "or" divider, only when `features.googleLogin`.
- `AuthService.loginWithGoogle(credential: string): Promise<AuthUser>` added
  to `services/contracts/auth.ts`.
  - API adapter: `POST /auth/google { credential }` after the CSRF cookie
    fetch, identical error handling to `login`.
  - Demo adapter: returns a new demo owner session with `onboardedAt: null`
    so the onboarding screen is demonstrable. The demo login shortcut panel
    gets a "Continue with Google (demo)" entry gated by `showDemoShortcuts`
    — the real button never renders in demo.
- `stores/auth.ts` gains `loginWithGoogle(credential)` delegating to the
  selected adapter and then running the same post-login redirect as `login`.

### 3.2 `AuthUser` additions

```ts
export interface AuthUser {
  // …existing
  hasPassword: boolean;            // false for Google-only accounts
  avatarUrl: string | null;
  onboardedAt: string | null;      // owners only; null ⇒ must onboard
  purposes: OwnerPurpose[];        // owners only; [] until onboarded
  checklistDismissedAt: string | null;
}
export type OwnerPurpose = "rental" | "own_stay" | "investment";
```

Tenants and admins return `hasPassword: true`, `onboardedAt: null`,
`purposes: []`, `checklistDismissedAt: null`; the route guard only acts on
`role === "owner"`.

### 3.3 Backend

- Package: `laravel/socialite`. Verification via
  `Socialite::driver('google')->stateless()->userFromToken($credential)`
  is **not** used — `userFromToken` expects an access token, not an ID token.
  Instead verify the ID token with Google's `tokeninfo` endpoint through a
  small `App\Support\GoogleIdToken` class (`Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => …])`),
  checking `aud === config('services.google.client_id')`, `iss` in the Google
  set, `exp` in the future, and `email_verified === 'true'`. This keeps the
  dependency surface to zero; `laravel/socialite` is therefore **not** added.
  The class is bound in the container so tests fake it.
- Route: `POST /auth/google` — guest, `throttle:login`. Request
  `GoogleLoginRequest { credential: required|string }`.
- `GoogleLoginController::store`:
  1. Verify token → 401 `auth.google.invalid` on any failure.
  2. Find user by email.
     - Found, `role !== owner` → 403 `auth.google.not_owner`.
     - Found owner → set `google_id` if null, set `avatar_url` if null,
       `email_verified_at` if null.
     - Not found → create owner: `name`, `email`, `google_id`, `avatar_url`,
       `email_verified_at = now()`, `password = null`, `onboarded_at = null`.
  3. `Auth::login($user)`, regenerate session, return `AuthUserResource` (same
     shape as `/login`).
  4. Audit: `auth.google_register` / `auth.google_login`.
- `LoginController`: a user with `password === null` attempting password login
  gets 422 on `email` with message `auth.uses_google` ("This account signs in
  with Google").
- Migration `add_google_auth_and_onboarding_to_users`:
  - `google_id` string nullable unique
  - `avatar_url` string nullable
  - `password` → nullable
  - `purposes` json nullable
  - `onboarded_at` timestamp nullable — **back-filled to `created_at` for all
    existing owners** so nobody live is forced through onboarding
  - `checklist_dismissed_at` timestamp nullable
- `config/services.php` → `google.client_id` from `GOOGLE_CLIENT_ID`.
- Settings → Profile: `AccountController::setPassword` (`POST /account/password`)
  for `hasPassword === false` users only (422 otherwise). Frontend shows a
  "Set a password" section in `SettingsProfileForm.vue` when
  `!auth.user.hasPassword`.

### 3.4 Forgot / reset password (owners and tenants)

Required for launch. Uses Laravel's password broker — `password_reset_tokens`
already exists and `User` already carries `Notifiable` + `CanResetPassword`.

- `POST /auth/forgot-password { email }` — guest, `throttle:5,1`. **Always
  200** `{ message }` whether or not the email exists (no account
  enumeration). Admins are skipped silently (they use the invite flow). When a
  user exists, `Password::sendResetLink` dispatches
  `App\Notifications\ResetPassword` (queued, bilingual, same shape as
  `AdminInvite`) whose action URL is
  `FRONTEND_URL/auth/reset-password?token=…&email=…`. Token TTL = the broker
  default (60 min).
- `POST /auth/reset-password { token, email, password, password_confirmation }`
  — guest, `throttle:5,1`. On success sets the password (this also works for
  Google-only accounts — it is the email path to "set a password"), fires
  `PasswordReset`, logs the user in and returns the same `{user, token}`
  envelope as `/auth/login`. Invalid/expired token → 422 on `email` with
  `auth.reset.invalid`.
- Frontend: `/auth/forgot-password` (email form, success state that says
  "if that email exists we sent a link"), `/auth/reset-password` (reads
  `token` + `email` from the query; new password + confirm; success → the
  owner/tenant shell by role). The existing `auth.forgotPassword` link on
  `/auth/login` points at the first page. `AuthService.forgotPassword(email)`
  and `AuthService.resetPassword({token,email,password})` in both adapters;
  the demo adapter resolves immediately and `resetPassword` signs in as the
  demo owner.
- Audit: none (customer self-service, not an admin action).

## 4. Onboarding

### 4.1 Route + guard

- `middleware/auth.global.ts`: after auth is restored, if
  `user.role === "owner" && user.onboardedAt === null` and the target is under
  `/owner` but not `/owner/onboarding`, redirect to `/owner/onboarding`.
  Onboarded owners visiting `/owner/onboarding` are sent to `/owner`.
- Page `pages/owner/onboarding.vue`, `layout: "auth"` (no sidebar; the owner
  isn't "in" the app yet). Locale-aware (en + ms).

### 4.2 Screen

Heading: "What will you manage in Roofly?" Three selectable cards,
multi-select, at least one required:

| Card | Subtitle |
|---|---|
| Rental | Units with tenants, rent collection, agreements |
| Own stay | The home you live in — ownership, loan, bills |
| Investment | Held for value, not rented right now |

Buttons: **Continue** (disabled until ≥1 selected) and a quiet **Skip for
now** link. Both call
`useOwnerSettings().completeOnboarding({ purposes })`; Skip sends
`["rental"]`. Success → `auth.user` refreshed from the response → `/owner`.

### 4.3 Contract + backend

```ts
// services/contracts/ownerSettings.ts
completeOnboarding(input: { purposes: OwnerPurpose[] }): Promise<AuthUser>;
dismissChecklist(): Promise<AuthUser>;
restoreChecklist(): Promise<AuthUser>;
```

- `PATCH /account/onboarding { purposes: required|array|min:1, purposes.*: in:rental,own_stay,investment }`
  → sets `purposes`, `onboarded_at = now()` (idempotent; re-calling updates
  purposes only). Returns `AuthUserResource`.
- `PATCH /account/checklist { dismissed: required|boolean }` → sets/clears
  `checklist_dismissed_at`. Returns `AuthUserResource`.
- Settings → Preferences gets a "What you manage" multi-select (same three
  options) calling `completeOnboarding`, and a "Show getting-started
  checklist" toggle calling dismiss/restore.
- Audit actions: `account.onboarded`, `account.checklist_dismissed`,
  `account.checklist_restored`.

## 5. Property purpose

### 5.1 Model

```ts
export type PropertyPurpose = "rental" | "own_stay" | "investment";
export interface Property { /* … */ purpose: PropertyPurpose; }
export type PropertyInput = Pick<Property, "name" | … | "type" | "purpose">;
```

Backend: `properties.purpose` enum column, default `rental`, existing rows
back-filled `rental`. `StorePropertyRequest` / `UpdatePropertyRequest`
validate `in:rental,own_stay,investment`. `PropertyResource` returns it.
Not added to any admin Resource (`AdminResourcesTest` unchanged).

### 5.2 UI

- `AddPropertyModal.vue`: a segmented control "This property is for" with the
  three options. Default = the owner's first `purposes` entry. **Hidden** when
  the owner has exactly one purpose (value is implied). Always visible in
  Details tab of the property detail page (`PropertyDetailsForm.vue`) so it
  can be changed later.
- `PropertyCard.vue` shows a small neutral pill for `own_stay` / `investment`;
  rental shows none (the common case stays quiet).

### 5.3 Effects of `purpose !== "rental"`

| Surface | Behaviour |
|---|---|
| Dashboard occupancy tile | Units of non-rental properties excluded from numerator and denominator |
| Needs attention | No "vacant unit" / "no agreement" items for non-rental properties |
| Units panel | Still available (a landed own-stay house may have a rentable room later) but no empty-state nudge to add units |
| Reports | Non-rental properties listed in a separate "Not for rent" group showing ownership/RPGT columns only; excluded from income totals and the monthly chart |
| Checklist | Drives which steps appear (see § 6) |

Implementation lives in the existing composables (`useDashboard`,
`useReports`) and demo/API dashboard adapters — both adapters must apply the
same filter. The demo seed gains one own-stay property (Aminah's own home) so
the behaviour is visible in `demo-roofly`.

## 6. Getting-started checklist

### 6.1 Computation

`composables/useOnboardingChecklist.ts` exports a pure function

```ts
buildChecklist(input: {
  purposes: OwnerPurpose[];
  properties: Property[];
  units: Unit[];
  tenants: Tenant[];
  agreements: Agreement[];
}): ChecklistStep[];

interface ChecklistStep {
  key: ChecklistKey;
  done: boolean;
  to: string;                       // deep link
  propertyId?: string;              // for property-scoped steps
}
```

and a composable wrapper that feeds it from the dashboard's already-loaded
data plus `auth.user.purposes`. No extra network calls.

Steps (union across the owner's purposes; order as listed):

| Key | Shown for | Done when | Deep link |
|---|---|---|---|
| `add_property` | all | `properties.length > 0` | `/owner/properties?add=1` |
| `fill_ownership` | all | every property has `propertyCompletion(p).ownership === 100` — or, if several, the link targets the first incomplete one | `/owner/properties/:id?tab=ownership` |
| `fill_utilities` | all | same rule on the utilities section | `/owner/properties/:id?tab=utilities` |
| `add_unit` | rental | at least one rental property has ≥1 unit | `/owner/properties/:id?tab=overview` (first rental property without units) |
| `invite_tenant` | rental | `tenants.length > 0` | `/owner/tenants?invite=1` |
| `create_agreement` | rental | any agreement with `status === "active"` | `/owner/agreements/new` |

Steps after `add_property` render disabled (not hidden) until a property
exists, so the owner sees the whole path.

The query params `?add=1`, `?invite=1`, `?tab=` are consumed by the target
pages to open the modal / select the tab on mount, then cleared from the URL.
`?tab=` already exists in spirit via `activeTab`; the other two are new and
tiny.

### 6.2 Card

`components/owner/GettingStartedCard.vue` at the top of `pages/owner/index.vue`:

- Header: "Getting started" + progress "3 of 6" + a dismiss (×) button.
- One row per step: check icon (done) / numbered circle (todo) / muted
  (disabled), title, one-line hint, chevron. Whole row is the link.
- Hidden when every step is done **or** `auth.user.checklistDismissedAt` is
  set. Dismiss calls `dismissChecklist()` with a toast "You can bring this
  back from Settings → Preferences".
- Mobile: same layout; rows stack naturally. Follows UI-STANDARDS § 11
  card-row pattern (pill/meta on top, message below) — add a note there.

## 7. Adapter parity checklist

| Contract method | Demo | API | Backend route |
|---|---|---|---|
| `auth.loginWithGoogle` | fake owner session | `POST /auth/google` | new |
| `auth.forgotPassword` / `auth.resetPassword` | resolve / demo owner session | `POST /auth/forgot-password`, `POST /auth/reset-password` | new |
| `ownerSettings.completeOnboarding` | mutate demo user | `PATCH /account/onboarding` | new |
| `ownerSettings.dismissChecklist` / `restoreChecklist` | mutate demo user | `PATCH /account/checklist` | new |
| `ownerSettings.setPassword` | no-op success | `POST /account/password` | new |
| `properties.create` (+purpose) | existing | existing | `StorePropertyRequest` widened |

Docs to update in the same PR: API-SPEC.md (auth + account + properties),
API-MAP.md (login/register/onboarding/settings/dashboard rows), MOCK-POC.md
(Properties schema-impact: `purpose`; Settings: onboarding fields),
UI-STANDARDS § 11 (checklist card), `.env.example` (both new vars),
`.claude/CLAUDE.md` current-state paragraph.

## 8. Error handling

| Situation | Behaviour |
|---|---|
| GIS script blocked / fails to load | Button area shows muted text "Google sign-in unavailable"; email form still works |
| Token invalid / unverified email | Toast "Google sign-in failed, try again" (401 body message) |
| Email belongs to a tenant | Inline error under the button: "This email is a tenant account. Ask your landlord for your invite link." (403) |
| Password login on Google-only account | 422 on email field: "This account signs in with Google" |
| Onboarding PATCH fails | Stay on screen, toast, retry-able; guard keeps redirecting until `onboardedAt` is set |
| Owner deep-links to `/owner/*` before onboarding | Guard → `/owner/onboarding`, no `redirect` param (dashboard is the correct landing after) |

## 9. Testing

**Backend (Pest, sqlite):**
- `GoogleLoginTest`: new owner created + logged in; existing owner linked
  (google_id set, no duplicate); tenant email → 403; unverified → 401; bad
  `aud` → 401; audit rows written. `GoogleIdToken` is faked via the container.
- `LoginTest`: null-password user → 422 `auth.uses_google`.
- `PasswordResetTest`: forgot returns 200 for unknown + known email, notification queued only for known non-admin; reset with valid token sets password + logs in; bad token → 422; Google-only account gains a password.
- `AccountOnboardingTest`: patch sets purposes + onboarded_at; validation;
  checklist dismiss/restore; set-password only when null.
- `PropertyPurposeTest`: validation, default, resource output.
- `DashboardTest` / `ReportTest`: non-rental property excluded from occupancy
  and income, present in the "not for rent" group.
- Migration test: existing owners have `onboarded_at` back-filled.

**Frontend:**
- Vitest on `buildChecklist` (per-purpose step sets, done rules, deep-link
  targets, disabled-until-property rule).
- `docker exec roofly-frontend npm run typecheck` (5 known pre-existing errors
  tolerated).
- Browser verification by the owner of the repo per the usual workflow; no
  Playwright unless asked.

## 10. Rollout

- Ships to UAT behind `features.googleLogin` (flag off until the Google
  client ID is configured per environment); onboarding and checklist have no
  flag — they're safe for existing owners because of the back-fill.
- Demo (`demo-roofly`) gets onboarding + checklist + purpose via the normal
  UAT merge; Google button never renders there.
- Google Cloud console: one OAuth client (web), authorised origins = UAT +
  prod frontend hosts; the secret is never needed (ID-token flow only).
