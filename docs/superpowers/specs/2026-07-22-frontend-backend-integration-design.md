# Frontend↔backend integration (Phase 2) — design

**Date:** 2026-07-22
**Scope:** Frontend only, **owner side first**. Wire the Nuxt frontend to the real Laravel API — Sanctum SPA auth, CSRF/401/422 handling, route guarding, env wiring — and verify every **owner** screen against the seeded demo data with `NUXT_PUBLIC_USE_MOCK=false`. The auth/`useApi`/route-guard/env infrastructure built here is shared and role-agnostic, so it unblocks the tenant side without rework.
**Deferred (KIV) to a follow-up phase:** the four tenant-scoped service-method gaps (§6) and the tenant-surface verification walk (§8b) — tenant login/shell will work end-to-end once this phase lands, but tenant-shell writes (pay invoice, report issue, ticket comment, profile edit) will still 403 against the real API until that follow-up closes §6. §6 and §8b are kept in this doc as the handoff note for that phase, not as current-phase work.
**Out of scope:** Backend changes (Phase 1 is done — API Resources, FormRequests, expand envelopes, DemoSeeder, 54/54 tests green), real Billplz payment flow, file uploads, RabbitMQ jobs, magic-link UI.
**Depends on:** `docs/superpowers/specs/2026-07-21-backend-api-contract-alignment-design.md` (esp. §12 "Integration notes for the next phase").

---

## 0. Correction to prior state

The kickoff brief for this phase said Phase-1 backend work was staged-but-uncommitted. `git log` on `feature/backend-contract-alignment` shows it is already committed (`b373e68`, `ad854db`) and pushed to `origin`. No action needed — noting it so the ledger stays accurate. This phase's own changes still follow the standing rule: no `git commit`/`git push` without explicit permission, asked each time.

## 1. Problem

The frontend currently runs entirely against typed in-memory mocks. `useApi()` is a thin `$fetch` shim with no CSRF/401/422 handling. `stores/auth.ts` mocks login/register and persists a fake user to `localStorage`. Four tenant-shell components call owner-scoped service methods that will 403 under the real `role:owner` middleware once mocks are off (recorded in the Phase-1 spec §12). Nothing guards `/owner/*` or `/tenant/*` routes against unauthenticated or wrong-role access — only the root page (`/`) redirects, and only on load.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Session storage | Drop `localStorage` entirely. Boot state comes from `GET /api/auth/me` in the client plugin. Sanctum's session cookie is the only persistence. |
| Data-fetch timing | All service calls happen client-side inside `onMounted()` (verified true across every current owner/tenant page — no SSR-time fetches). No dual server/browser `apiBase` needed; `http://localhost:8000/api` works uniformly for local dev. |
| Route guarding | Add a global middleware (`middleware/auth.global.ts`) that redirects unauthenticated visits to `/owner/*` or `/tenant/*` to `/auth/login`, and redirects role-mismatched visits (tenant on `/owner/*`, owner on `/tenant/*`) to their own shell root. Runs alongside, not instead of, `useApi`'s 401 handling. |
| Tenant-scoped service methods | Explicit new methods per gap (`payForTenant`, `createForTenant`, `addCommentForTenant`, `getWithRefsForTenant`, `getForTenant`/`updateForTenant`), following the `listForTenant` naming convention already established in `useInvoices`/`useTickets`. Not role-branching inside the owner-facing methods — the tenant endpoints have genuinely different payloads/URLs. |
| 422 validation errors | Shared helper maps Laravel's `{message, errors: {field: [msgs]}}` to vee-validate's `setErrors()` shape. Wired into every form that writes to the API this phase (properties, tenants, agreements, payments, tickets, settings, auth) — full rollout, not deferred. |
| Env for local verification | Root `.env` (gitignored) flips `NUXT_PUBLIC_APP_ENV=uat`, `NUXT_PUBLIC_USE_MOCK=false`. Demo stays mock-only regardless (`useEnv().useMock = isDemo || flag`, and `isDemo` always wins). No `nuxt.config.ts` changes needed — `apiBase` already defaults to `http://localhost:8000/api` and already reads both vars from env. |

## 3. Auth store (`stores/auth.ts`)

Replace the mock bodies; keep the same public shape (`user`, `loading`, `isAuthenticated`/`isOwner`/`isTenant`/`isAdmin` getters) so call sites (login page, layouts, the new middleware) don't change their read side.

- `login(email, password)`: `GET /sanctum/csrf-cookie` (via `useApi().request`, no credentials issue since `credentials: "include"` is already set) → `POST /api/auth/login` with `{email, password}` → response is `{user: AuthUser, token}`; store `user`. Let errors (401 invalid credentials, 422) propagate to the caller — the login page already renders a generic error string; this phase doesn't need to expand that beyond "invalid credentials" since the login form is a single free-text case, not a multi-field validation surface worth the field-error treatment.
- `register(payload)`: same pattern via `POST /api/auth/register`, on the register page apply the 422→field-error helper (multi-field form, benefits from it).
- `logout()`: `POST /api/auth/logout`, then clear `user`.
- `fetchMe()`: `GET /api/auth/me`. On success, set `user`. On 401, leave `user` null — this is the expected "not logged in" case, not an error to surface.
- Drop `persist()` and the `STORAGE_KEY` localStorage read/write entirely.

`plugins/auth-restore.client.ts` body becomes `await useAuthStore().fetchMe()`.

## 4. `useApi`

Add three behaviors to the `$fetch.create` instance, all scoped to real (non-mock) calls only — mock-mode services never call `useApi()` at all, so no branching needed inside it:

- **CSRF**: `onRequest` hook reads `document.cookie` for `XSRF-TOKEN`, `decodeURIComponent`s it, sets header `X-XSRF-TOKEN`. Guard for `import.meta.client` (SSR has no `document`; harmless no-op since nothing calls `useApi` server-side per §2's data-fetch-timing decision).
- **401 → login**: `onResponseError` hook — if `response.status === 401` and the request path isn't `/auth/me` or `/auth/login` (both expected to legitimately 401 without triggering a redirect loop or masking a bad-password error), call `useAuthStore().logout()`-equivalent local clear + `navigateTo('/auth/login')`.
- **422 → field errors**: `onResponseError` hook — if `response.status === 422`, attach the parsed `{message, errors}` body to the thrown error (ofetch already throws on non-2xx; we enrich `error.data`). New composable `useApiError()` exports `toFieldErrors(error: unknown): Record<string, string> | null` — returns `null` if the error isn't a 422 shape (caller falls back to toast), otherwise `{field: firstMessage}` per key.

## 5. Route guard middleware

`middleware/auth.global.ts`:

- No-op for anything outside `/owner` and `/tenant` prefixes (marketing pages, `/auth/*`, `/demo/*`, `/coming-soon` keep their existing behavior untouched).
- Waits for the boot `fetchMe()` to have settled before evaluating (a `pending`/`authReady` flag on the auth store, set `true` once the plugin's `fetchMe()` promise resolves — prevents a false redirect-to-login flash while the boot check is in flight on a hard refresh of e.g. `/owner/properties`).
- Not authenticated → `navigateTo('/auth/login')`.
- Authenticated but role/prefix mismatch → `navigateTo(auth.isTenant ? '/tenant' : '/owner')`.
- In mock mode this is exercised identically — `auth.user` is populated by the existing mock `login()`, so `isAuthenticated`/role checks behave the same as today; no demo-mode carve-out needed.

## 6. Tenant-scoped service methods — DEFERRED (KIV, not this phase)

Kept here as the handoff note for the follow-up phase. None of this is implemented in the current plan; the table below is a spec, not a task list yet.

| New method | Endpoint | Notes |
|---|---|---|
| `useInvoices().payForTenant(invoiceId, method)` | `POST /me/invoices/{id}/pay`, body `{method}` | Backend computes amount/paidAt/status server-side. Returns `{payment, invoice}` same shape as `recordPayment`. `PayInvoiceModal.vue` drops the amount/paidAt/reference fields it currently builds client-side and calls this with the selected bank's method (`"fpx"`) instead. |
| `useTickets().createForTenant(input)` | `POST /me/tickets`, body `{category, priority, title, description}` | No `unitId`/`reporterId`/`reporterRole` — server derives unit from the tenant's active agreement and 422s if there isn't one. `ReportIssueModal.vue` stops building/sending those three fields; `unitId`/`reporterId` props on the modal become unused for the real-mode path (kept for the mock-mode branch, which still needs them to build a full `Ticket`). |
| `useTickets().addCommentForTenant(ticketId, body)` | `POST /me/tickets/{id}/comments`, body `{body}` | Same body shape as today's `addComment` — only the URL differs. Tenant ticket detail page switches call sites. |
| `useTickets().getWithRefsForTenant(ticketId)` | `GET /me/tickets/{id}?expand=unit,property,reporter,comments` | Tenant ticket detail page switches its initial load call. |
| `useTenants().getForTenant(tenantId)` / `updateForTenant(tenantId, payload)` | `GET`/`PATCH /me/profile` | Real-mode branch ignores `tenantId` (session-scoped server-side), mock-mode branch still needs it to find the right mock record — same pattern as existing `listForTenant`. Tenant profile page switches from `get`/`update` to these. |

All five keep mock-mode bodies byte-identical to the logic they're replacing at the call site (copy the existing mock branch, no behavior change in demo/mock mode).

## 7. 422 → vee-validate wiring (owner + auth forms this phase)

Each form's `handleSubmit(async (values) => { ... })` callback wraps its service call in try/catch:

```ts
try {
  await useProperties().create(values);
} catch (err) {
  const fieldErrors = toFieldErrors(err);
  if (fieldErrors) { setErrors(fieldErrors); return; }
  show(t("common.genericError"), "error"); // existing toast fallback
}
```

Applies to (this phase): property create/edit, tenant invite/edit, agreement create/edit, payment record modal, ticket status/comment forms (owner), settings profile/preferences/notifications, auth login/register. Exact field-name alignment (Laravel's validated key vs. the vee-validate field name) is 1:1 in every case since both sides already use the same camelCase contract (Phase 1 design §5).

Deferred: the five tenant-shell forms (profile edit, pay modal, report-issue modal, comment forms) — wired in the follow-up phase alongside §6, using the same `toFieldErrors` helper (no new helper work needed then, just call sites).

## 8. Verification pass (owner side, this phase)

With `NUXT_PUBLIC_USE_MOCK=false` locally: log in as `aminah@roofly.my` (owner), click through every owner surface (dashboard, properties + units + co-owners, tenants, agreements, payments, maintenance, reports, settings), compare against current mock-mode behavior screen-by-screen. Any contract mismatch is fixed on the side the Phase-1 spec says wins — frontend types are the source of truth; backend Resources must match. Known pre-existing typecheck errors (5, documented in project CLAUDE.md) stay out of scope.

Also verify, as a side effect of the shared auth infra: tenant login succeeds and lands in the tenant shell (role-based redirect + route guard both exercise the tenant path even though tenant-shell *writes* aren't wired yet — reads that already went through `listForTenant`/`getWithRefs` such as tenant home, agreement, payments list, and issues list should work; only the four write/detail gaps in §6 are expected to fail/403 until the follow-up phase).

## 8b. Verification pass (tenant side) — DEFERRED (KIV, not this phase)

Once §6 lands: log in as a seeded tenant, click through every tenant surface (home, agreement, payments + pay, issues + report + comment, profile), same compare-against-mock-mode process.

---

## 9. Testing

No new backend tests (Phase 1's 54 feature tests already gate the contract). Frontend verification is manual click-through per §8 — this app has no frontend test suite yet, consistent with the mock-first phase's existing practice (typecheck is the only automated gate: `docker exec roofly-frontend npm run typecheck`, expect the same 5 known pre-existing errors and zero new ones).
