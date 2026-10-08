# Frontend↔Backend Integration (Owner-first) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the Nuxt frontend to the real Laravel API for the owner shell — Sanctum SPA cookie auth, CSRF/401/422 handling in `useApi`, a global auth+role route guard, and Laravel-422→vee-validate field-error mapping across owner and auth forms — verified against seeded demo data with `NUXT_PUBLIC_USE_MOCK=false`.

**Architecture:** The mock/API swap already lives per-method inside each service composable (`if (useMock) … else useApi()`). This plan does not touch that seam — it hardens the `useApi()` side (cookies, CSRF header, 401 redirect, 422 enrichment), replaces the mock auth store with real Sanctum calls, adds route guarding, and wires form error display. Tenant-shell write methods are explicitly deferred (spec §6).

**Tech Stack:** Nuxt 4, Vue 3, Pinia, vee-validate + Zod, ofetch (`$fetch`), Laravel 11 + Sanctum backend (already contract-aligned, Phase 1).

**Spec:** `docs/superpowers/specs/2026-07-22-frontend-backend-integration-design.md` — read it first.

## Global Constraints

- **NO git commits / pushes anywhere in this plan without the user's explicit permission, asked each time.** This overrides the writing-plans template's default "Commit" steps. Every task ends at a **review checkpoint**, not a commit. (Per `~/.claude/CLAUDE.md`.)
- All frontend work under `/Users/BHQIMBP16/Developer/roofly/frontend`. Paths below are relative to `frontend/app/` unless stated.
- **No automated frontend test suite exists** (mock-first phase convention). The gate for every task is: `docker exec roofly-frontend npm run typecheck` produces **zero new errors** (the 5 pre-existing errors listed in project CLAUDE.md stay — `InvoiceViewModal.vue`, `payments.vue` ×2, `Icon.vue`, `EmptyState.vue`), plus the task's stated manual/curl smoke check.
- **Money is integer sen** end-to-end; never format at the service edge.
- **No literal `@` in i18n strings** (vue-i18n linked-message marker) — escape as `{'@'}` or rephrase.
- Services read `useEnv().useMock` **inside** the composable — never a module constant. Do not change this.
- Contract casing is camelCase both directions (Phase 1). Frontend types are the source of truth; a mismatch is fixed on the backend Resource.
- Backend runs locally at `http://localhost:8000/api` (docker `roofly-backend`, `php artisan serve`). Reset seed data: `docker exec roofly-backend php artisan migrate:fresh --seed --force`. Demo owner login: `aminah@roofly.my` / `password`.

---

### Task 1: `useApi` — CSRF header, 401 redirect, 422 enrichment + `useApiError` helper

**Files:**
- Modify: `composables/useApi.ts`
- Create: `composables/useApiError.ts`

**Interfaces:**
- Produces:
  - `useApi()` → `{ request, baseURL }` where `request` is an ofetch instance that (a) sends `credentials: "include"`, (b) attaches `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie on the client, (c) on a 401 (except `/auth/me` and `/auth/login`) clears the auth store and navigates to `/auth/login`, (d) lets ofetch throw its `FetchError` on 422 with `error.data = { message, errors }` intact.
  - `useApiError()` → `{ toFieldErrors(error: unknown): Record<string, string> | null }`. Returns `null` when `error` is not a Laravel 422 (`{ errors: { field: string[] } }`) shape; otherwise a map of `field → first message`.

- [ ] **Step 1: Write the `useApiError` helper**

`composables/useApiError.ts`:
```ts
/**
 * Maps a Laravel 422 validation error (`{ message, errors: { field: string[] } }`,
 * surfaced by ofetch as `FetchError.data`) into vee-validate's `setErrors` shape
 * (`{ field: firstMessage }`). Returns null for any non-422 / unshaped error so
 * callers can fall back to a generic toast.
 */
export const useApiError = () => {
  const toFieldErrors = (error: unknown): Record<string, string> | null => {
    const data = (error as { data?: unknown })?.data as
      | { errors?: Record<string, string[]> }
      | undefined;
    if (!data?.errors || typeof data.errors !== "object") return null;

    const out: Record<string, string> = {};
    for (const [field, messages] of Object.entries(data.errors)) {
      if (Array.isArray(messages) && messages.length > 0 && messages[0]) {
        out[field] = messages[0];
      }
    }
    return Object.keys(out).length > 0 ? out : null;
  };

  return { toFieldErrors };
};
```

- [ ] **Step 2: Rewrite `useApi` with cookie/CSRF/401 handling**

`composables/useApi.ts`:
```ts
/**
 * Sanctum-aware $fetch wrapper.
 *
 * - Sends the session cookie on every call (`credentials: "include"`).
 * - Attaches the `X-XSRF-TOKEN` header from the `XSRF-TOKEN` cookie Sanctum
 *   sets after `GET /sanctum/csrf-cookie`, so state-changing requests pass
 *   CSRF verification.
 * - On a 401 (other than the auth-probe endpoints, which legitimately 401
 *   when logged out or on a bad password), clears auth state and bounces to
 *   the login page.
 * - Leaves 422s to throw as ofetch FetchErrors with `.data = { message, errors }`
 *   intact, so forms can map them via `useApiError().toFieldErrors`.
 */
const readXsrfToken = (): string | null => {
  if (!import.meta.client) return null;
  const match = document.cookie
    .split("; ")
    .find((row) => row.startsWith("XSRF-TOKEN="));
  return match ? decodeURIComponent(match.split("=")[1] ?? "") : null;
};

export const useApi = () => {
  const config = useRuntimeConfig();
  const baseURL = config.public.apiBase;

  const request = $fetch.create({
    baseURL,
    credentials: "include",
    headers: { Accept: "application/json" },
    onRequest({ options }) {
      const token = readXsrfToken();
      if (token) {
        options.headers = new Headers(options.headers);
        options.headers.set("X-XSRF-TOKEN", token);
      }
    },
    onResponseError({ request: req, response }) {
      if (response.status !== 401) return;
      const url = typeof req === "string" ? req : req.url;
      // Auth probes are allowed to 401 without a redirect: /auth/me is the
      // boot "am I logged in?" check, /auth/login is a failed sign-in.
      if (url.includes("/auth/me") || url.includes("/auth/login")) return;

      const auth = useAuthStore();
      auth.$patch({ user: null });
      navigateTo("/auth/login");
    },
  });

  return { request, baseURL };
};
```

- [ ] **Step 3: Typecheck**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: zero new errors (only the 5 known pre-existing). If `auth.$patch` errors on the type, it is because the store isn't imported — `useAuthStore` is auto-imported by Pinia/Nuxt, so no import line is needed; confirm the error is not about `$patch`.

- [ ] **Step 4: Curl smoke — CSRF round-trip works**

Run:
```bash
# csrf-cookie then a login POST, reusing the cookie jar + xsrf header
docker exec roofly-backend sh -c '
  curl -s -c /tmp/cj -b /tmp/cj http://localhost:8000/sanctum/csrf-cookie -o /dev/null -w "csrf:%{http_code}\n"
  TOKEN=$(grep XSRF-TOKEN /tmp/cj | cut -f7)
  curl -s -c /tmp/cj -b /tmp/cj -X POST http://localhost:8000/api/auth/login \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    -H "X-XSRF-TOKEN: $(python3 -c "import urllib.parse,sys;print(urllib.parse.unquote(sys.argv[1]))" "$TOKEN")" \
    -d "{\"email\":\"aminah@roofly.my\",\"password\":\"password\"}" \
    -w "\nlogin:%{http_code}\n"
'
```
Expected: `csrf:204` then `login:200` with a JSON body `{"user":{...},"token":"..."}`. (This proves the backend CSRF + login contract the frontend now depends on; the header wiring itself is exercised by the browser in Task 5.)

- [ ] **Step 5: Review checkpoint**

Report: files changed, typecheck result, curl output. Do not commit.

---

### Task 2: Real Sanctum auth store + boot hydration

**Files:**
- Modify: `stores/auth.ts`
- Modify: `plugins/auth-restore.client.ts`

**Interfaces:**
- Consumes: `useApi()` (Task 1).
- Produces: `useAuthStore()` with the same reads as today (`user`, `loading`, getters `isAuthenticated`/`isOwner`/`isTenant`/`isAdmin`) plus a new `authReady: boolean` state flag (false until the boot `fetchMe()` settles) and a `pending`-safe `fetchMe()`. `login(email, password)`, `register(payload)`, `logout()` now hit the real API. No `localStorage`.

- [ ] **Step 1: Replace the auth store body**

`stores/auth.ts` — keep the `AuthUser`/`UserRole` types and the store id/getters; replace state + actions:
```ts
import { defineStore } from "pinia";

export type UserRole = "owner" | "tenant" | "admin";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
}

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
  /** False until the boot `fetchMe()` has settled — the route guard waits on this. */
  authReady: boolean;
}

interface RegisterPayload {
  name: string;
  email: string;
  phone: string;
  password: string;
}

export const useAuthStore = defineStore("auth", {
  state: (): AuthState => ({
    user: null,
    loading: false,
    authReady: false,
  }),

  getters: {
    isAuthenticated: (s) => s.user !== null,
    isOwner: (s) => s.user?.role === "owner",
    isTenant: (s) => s.user?.role === "tenant",
    isAdmin: (s) => s.user?.role === "admin",
  },

  actions: {
    async login(email: string, password: string) {
      this.loading = true;
      try {
        const { request } = useApi();
        // Prime the CSRF cookie before the stateful POST.
        await request("/../sanctum/csrf-cookie");
        const res = await request<{ user: AuthUser }>("/auth/login", {
          method: "POST",
          body: { email, password },
        });
        this.user = res.user;
      } finally {
        this.loading = false;
      }
    },

    async register(payload: RegisterPayload) {
      this.loading = true;
      try {
        const { request } = useApi();
        await request("/../sanctum/csrf-cookie");
        const res = await request<{ user: AuthUser }>("/auth/register", {
          method: "POST",
          body: {
            ...payload,
            password_confirmation: payload.password,
          },
        });
        this.user = res.user;
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      try {
        const { request } = useApi();
        await request("/auth/logout", { method: "POST" });
      } catch {
        // Even if the server call fails, drop local state.
      }
      this.user = null;
    },

    /**
     * Boot hydration: ask the server who we are. A 401 is the expected
     * "not logged in" case, not an error to surface. Always marks the
     * session ready so the route guard can proceed.
     */
    async fetchMe() {
      try {
        const { request } = useApi();
        this.user = await request<AuthUser>("/auth/me");
      } catch {
        this.user = null;
      } finally {
        this.authReady = true;
      }
    },
  },
});
```

Note on `"/../sanctum/csrf-cookie"`: `apiBase` is `http://localhost:8000/api`, but the csrf-cookie route is at `http://localhost:8000/sanctum/csrf-cookie` (no `/api`). The `/../` segment resolves the baseURL up one level. Verify in Step 4; if ofetch does not normalize it, fall back to an absolute URL built from `config.public.apiBase.replace(/\/api$/, "")`.

- [ ] **Step 2: Swap the boot plugin to hydrate from the server**

`plugins/auth-restore.client.ts`:
```ts
/**
 * Hydrate auth state from the Sanctum session on first client load, so a
 * refresh doesn't drop the user. Replaces the old localStorage restore —
 * the httpOnly session cookie is now the single source of truth.
 *
 * `.client.ts` keeps it off the SSR pass (no cookies/`document` server-side).
 */
export default defineNuxtPlugin(async () => {
  const auth = useAuthStore();
  await auth.fetchMe();
});
```

- [ ] **Step 3: Typecheck**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: zero new errors. In particular, confirm no call site still references the removed `restoreSession`/`persist` (grep below).

- [ ] **Step 4: Grep for dead references + verify csrf URL resolves**

Run: `cd frontend/app && grep -rn "restoreSession\|roofly_auth\|persist(" . --include=*.ts --include=*.vue`
Expected: no matches (the store no longer exports them and nothing else called them — the only caller was the boot plugin, now updated).

Run (URL normalization sanity — does `$fetch` collapse `/api/../sanctum`?):
```bash
docker exec roofly-frontend node -e "console.log(new URL('/api/../sanctum/csrf-cookie','http://localhost:8000').href)"
```
Expected: `http://localhost:8000/sanctum/csrf-cookie`. (Confirms the `/../` trick resolves; if the browser/ofetch path differs, the Step-1 fallback is used — decided during the Task-5 browser smoke.)

- [ ] **Step 5: Review checkpoint**

Report files changed, typecheck + grep results. Do not commit.

---

### Task 3: Global auth + role route guard

**Files:**
- Create: `middleware/auth.global.ts`

**Interfaces:**
- Consumes: `useAuthStore()` incl. `authReady` (Task 2).
- Produces: a global middleware that, for `/owner/*` and `/tenant/*` paths only, redirects unauthenticated users to `/auth/login` and role-mismatched users to their own shell root. No-op everywhere else. Waits for `authReady` so a hard refresh of a protected page doesn't flash-redirect during the boot `fetchMe()`.

- [ ] **Step 1: Write the middleware**

`middleware/auth.global.ts`:
```ts
/**
 * Auth + role gate for the app shells.
 *
 * Only `/owner/*` and `/tenant/*` are protected — marketing, `/auth/*`,
 * `/demo/*`, and `/coming-soon` keep their own routing (see demo-only.global.ts).
 *
 * On a hard refresh of a protected page the boot plugin's `fetchMe()` may
 * still be in flight; we await `authReady` so we never bounce a logged-in
 * user to /login mid-hydration. On the server pass (SSR) `authReady` is
 * false and there's no session cookie access, so we skip — the client
 * plugin + this same guard re-run on hydration.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const isOwnerArea = to.path === "/owner" || to.path.startsWith("/owner/");
  const isTenantArea = to.path === "/tenant" || to.path.startsWith("/tenant/");
  if (!isOwnerArea && !isTenantArea) return;

  // SSR has no session cookie here; let the client guard decide post-hydration.
  if (import.meta.server) return;

  const auth = useAuthStore();
  // Wait out the boot hydration if it hasn't settled yet.
  if (!auth.authReady) {
    await auth.fetchMe();
  }

  if (!auth.isAuthenticated) {
    return navigateTo("/auth/login");
  }

  const inWrongShell =
    (isOwnerArea && !auth.isOwner) || (isTenantArea && !auth.isTenant);
  if (inWrongShell) {
    return navigateTo(auth.isTenant ? "/tenant" : "/owner");
  }
});
```

- [ ] **Step 2: Typecheck**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: zero new errors.

- [ ] **Step 3: Reason-through smoke (no browser yet)**

Confirm by reading `middleware/demo-only.global.ts` that both global middlewares can coexist: `demo-only` handles `/`, `/coming-soon`, `/demo/*`; `auth.global` handles `/owner/*`, `/tenant/*`. Their path sets are disjoint, so ordering doesn't matter. Note this in the checkpoint.

- [ ] **Step 4: Review checkpoint**

Report the file, typecheck result, and the coexistence note. Do not commit.

---

### Task 4: 422 → vee-validate field errors across owner + auth forms

**Files:**
- Modify (owner forms): `components/owner/AddPropertyModal.vue`, `PropertyDetailsForm.vue`, `PropertyOwnershipForm.vue`, `PropertyUtilitiesForm.vue`, `UnitFormModal.vue`, `TenantInviteModal.vue`, `TenantIdentityForm.vue`, `TenantPersonalForm.vue`, `TenantEmergencyContactForm.vue`, `AgreementTermsForm.vue`, `RecordPaymentModal.vue`, `TicketCreateModal.vue`, `SettingsProfileForm.vue`, `SettingsPreferencesForm.vue`, `SettingsNotificationsForm.vue`
- Modify (owner pages with inline writes): `pages/owner/maintenance/[id].vue` (comment + status)
- Modify (auth): `pages/auth/register.vue`, `pages/auth/login.vue`
- Prereq: an i18n key `common.genericError` for the toast fallback (add to `frontend/i18n/locales/en.json` + `ms.json` if absent)

**Interfaces:**
- Consumes: `useApiError().toFieldErrors` (Task 1); each form's existing vee-validate `useForm(...)` instance.
- Produces: every owner/auth write shows Laravel field errors inline; non-422 errors fall back to the existing/added toast.

The mechanical pattern for a `useForm`-based component:
1. Add `setErrors` to the destructure: `const { defineField, handleSubmit, errors, setErrors, /* … */ } = useForm(...)`.
2. Add `const { toFieldErrors } = useApiError();` and (if not already present) `const { show } = useToast();`.
3. Wrap the existing `try { … } finally { … }` submit body with a `catch`:
```ts
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "error");
  } finally {
```

- [ ] **Step 1: Add the fallback i18n key (if missing)**

Run: `cd frontend && grep -n "genericError" i18n/locales/en.json`
If absent, add to `en.json`: `"common": { …, "genericError": "Something went wrong. Please try again." }` and to `ms.json`: `"genericError": "Sesuatu tidak kena. Sila cuba lagi."` (place inside the existing `common` object; do not introduce a literal `@`).

- [ ] **Step 2: Wire `AddPropertyModal.vue`**

Current submit (lines ~57–67) has `try { … } finally { … }`. Apply the pattern:
```ts
const { defineField, handleSubmit, errors, resetForm, setErrors } =
  useForm<PropertyInput>(/* unchanged args */);
// … existing consts …
const { toFieldErrors } = useApiError();

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const created = await useProperties().create(values);
    emit("created", created);
    emit("update:open", false);
    show(t("owner.properties.addModal.createdToast"), "success");
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "error");
  } finally {
    submitting.value = false;
  }
});
```

- [ ] **Step 3: Apply the same pattern to the remaining 14 owner form components**

For each of `PropertyDetailsForm.vue`, `PropertyOwnershipForm.vue`, `PropertyUtilitiesForm.vue`, `UnitFormModal.vue`, `TenantInviteModal.vue`, `TenantIdentityForm.vue`, `TenantPersonalForm.vue`, `TenantEmergencyContactForm.vue`, `AgreementTermsForm.vue`, `RecordPaymentModal.vue`, `TicketCreateModal.vue`, `SettingsProfileForm.vue`, `SettingsPreferencesForm.vue`, `SettingsNotificationsForm.vue`:
  1. Add `setErrors` to the `useForm` destructure.
  2. Add `const { toFieldErrors } = useApiError();` (and `const { show } = useToast();` if the file doesn't already have it — Task-context grep showed all these already import `useToast`, so most will).
  3. Insert the `catch` block shown above before the existing `finally` (or wrap the body in try/catch/finally if a form currently has no try — verify per file; the surveyed ones all use try/finally).
  4. For `UnitFormModal.vue`, which has both a create/update `onSubmit` and a `remove` handler: wire the `catch` into the submit handler only; the `remove` (line ~97) keeps its current behavior (a delete 422 is not expected; a non-422 failure there falls to the existing toast/finally).
  5. For nested JSON-blob forms (`PropertyOwnershipForm`, `PropertyUtilitiesForm`, `TenantPersonalForm`, `TenantEmergencyContactForm`): Laravel returns dotted error keys for nested fields (e.g. `ownership.titleType`). vee-validate's `setErrors` accepts the dotted path only if the field was registered with that path. Where the form's `defineField` names differ from the backend's dotted keys, the field-level error simply won't attach and the value falls through to `null` → the generic toast fires instead. That is acceptable for this phase (these forms are Tier-2 edits with few fields); note any such form in the checkpoint rather than reshaping field names now.

- [ ] **Step 4: Wire the two inline writes in `pages/owner/maintenance/[id].vue`**

The status transition (line ~75) and comment add (line ~95) are not `useForm` submits. For these, there's no `setErrors` target, so map 422 → toast only:
```ts
const { toFieldErrors } = useApiError();
// in each handler's catch:
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    show(fieldErrors ? Object.values(fieldErrors)[0]! : t("common.genericError"), "error");
  }
```
(A transition 422 is a real case — the backend enforces the ticket state machine — so surfacing its message is useful.)

- [ ] **Step 5: Wire `register.vue` and `login.vue`**

`register.vue` is not vee-validate (manual refs + a single `error` string). Map 422 to the first field message into the existing `error` ref:
```ts
const { toFieldErrors } = useApiError();
// …
  try {
    await auth.register({ /* … */ });
    await navigateTo("/owner");
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    error.value = fieldErrors
      ? Object.values(fieldErrors)[0]!
      : t("auth.invalidCredentials"); // reuse or add; see below
  }
```
`login.vue`: wrap the `await auth.login(...)` in try/catch, set `error.value = t("auth.invalidCredentials")` on any failure (login is a single credential case — no per-field mapping needed). Add `auth.invalidCredentials` to `en.json`/`ms.json` if absent (`"Invalid email or password."` / `"E-mel atau kata laluan tidak sah."`).

- [ ] **Step 6: Typecheck**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: zero new errors.

- [ ] **Step 7: Review checkpoint**

Report every file touched, any nested-blob form flagged in Step 3.5, typecheck result. Do not commit.

---

### Task 5: Env flip + owner-side verification pass

**Files:**
- Modify: `/Users/BHQIMBP16/Developer/roofly/.env` (gitignored — local only)
- Fix-forward: any backend Resource or frontend type mismatch surfaced during the walk (files TBD by findings — a mismatch is fixed on the backend Resource per the contract rule)

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: a verified owner shell running against the real API, plus a written list of any mismatches found and how each was resolved.

- [ ] **Step 1: Point local frontend at the real API with mocks off**

Edit `/Users/BHQIMBP16/Developer/roofly/.env`:
- `NUXT_PUBLIC_APP_ENV=uat`  (was `demo`)
- `NUXT_PUBLIC_USE_MOCK=false`  (was `true`)

(`apiBase` already defaults to `http://localhost:8000/api` in `nuxt.config.ts`; no change needed. `SANCTUM_STATEFUL_DOMAINS` already includes `localhost:3000` and CORS `FRONTEND_URL` is set — Phase 1.)

- [ ] **Step 2: Restart the frontend container to pick up env + fresh-seed the backend**

Run:
```bash
cd /Users/BHQIMBP16/Developer/roofly
docker compose up -d frontend                 # picks up new NUXT_PUBLIC_* env
docker exec roofly-backend php artisan migrate:fresh --seed --force
```
Expected: frontend restarts; backend reports migrations + seed OK.

- [ ] **Step 3: Confirm the app is in API mode**

Run: `docker exec roofly-frontend printenv | grep NUXT_PUBLIC`
Expected: `NUXT_PUBLIC_APP_ENV=uat`, `NUXT_PUBLIC_USE_MOCK=false`.

- [ ] **Step 4: Hand off to the user for the browser walk**

The actual click-through is the user's to run (they asked to test it). Provide them:
- URL `http://localhost:3000`, owner login `aminah@roofly.my` / `password`.
- The checklist of owner surfaces to exercise: dashboard (stats + income chart + needs-attention feed), properties list → detail (all 5 tabs) → add property → edit details/ownership/utilities → units panel add/edit/delete → co-owner edit (sum=100 invariant), tenants list → detail (3 tabs) → invite → edit, agreements list → new → detail (3 tabs) → delete, payments (table, filters, record-payment modal, invoice view), maintenance kanban → detail (status transitions + comment), reports (year picker, chart, per-property + CSV), settings (all 4 tabs, each save).
- Ask them to report anything that renders wrong, errors in the console/network tab, or differs from mock-mode.

- [ ] **Step 5: Fix mismatches surfaced by the walk**

For each issue the user reports: reproduce via `curl` against the endpoint, compare the JSON against the frontend `types/*.ts`, and fix the **backend Resource** to match the type (or the frontend type + service if the type itself was wrong — rare). Re-run `docker exec roofly-backend php artisan test` after any backend change (must stay green) and `docker exec roofly-frontend npm run typecheck` after any frontend change.

- [ ] **Step 6: Review checkpoint**

Report the env change, the walk results, every mismatch found + its fix, and the final test/typecheck state. Do not commit. Note for the user: reverting `.env` back to `demo`/`true` returns the local app to mock mode.

---

## Self-Review

**Spec coverage:**
- Spec §3 (auth store) → Task 2. §4 (`useApi` CSRF/401/422 + `useApiError`) → Task 1. §5 (route guard) → Task 3. §6 (tenant methods) → **deferred, no task, by design**. §7 (422 wiring, owner+auth) → Task 4. §2/§6-env → Task 5 Step 1. §8 (owner verification) → Task 5. §8b (tenant verification) → deferred. All in-scope sections have a task.

**Placeholder scan:** No "TBD/TODO" left as work items except Task 5's fix-forward file list, which is genuinely findings-dependent (documented as such, with the resolution rule stated). Every code step shows complete code.

**Type consistency:** `useApiError().toFieldErrors` signature is identical in Task 1 (definition) and Tasks 4's call sites. `auth.authReady` defined in Task 2, consumed in Task 3. `fetchMe()` defined in Task 2, called in Task 2's plugin and Task 3's guard. Store getters (`isAuthenticated`/`isOwner`/`isTenant`) unchanged from the current store, so existing call sites (login page, layouts) keep working.

**Note on the csrf-cookie URL:** Task 2 Step 1 uses `"/../sanctum/csrf-cookie"` with a stated fallback; Task 2 Step 4 verifies resolution before the browser walk depends on it.
