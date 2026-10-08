# Demo / API Adapter Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move every in-memory/demo implementation into `app/demo/` and every HTTP implementation into `app/services/api/`, both typed against one interface per service, selected once per composable — so demo code is structurally unable to reach the backend, and demo login works again.

**Architecture:** `services/contracts/<entity>.ts` declares `XService`. `demo/services/<entity>.ts` exports `demoX: XService` built on `demo/data/*` (the old `mocks/`). `services/api/<entity>.ts` exports `apiX: XService` built on `useApi()`. `services/useX.ts` becomes `() => useEnv().useMock ? demoX : apiX` plus type re-exports. Same shape for auth (`AuthAdapter`) and the dashboard.

**Tech Stack:** Nuxt 4 / Vue 3 / TypeScript strict, Pinia. Verification = `nuxt typecheck` (vue-tsc).

**Spec:** `docs/superpowers/specs/2026-08-23-demo-adapter-split-design.md`

## Global Constraints

- **No `git commit` / `git push`** at any step — the user reviews the finished refactor first (global CLAUDE.md rule). Commit steps are intentionally absent.
- Zero behaviour change in either mode: demo adapter bodies are the existing `if (useMock)` blocks moved verbatim; API adapter bodies are the existing `useApi()` calls moved verbatim (same paths, same methods, same bodies).
- Public composable method names and return types unchanged. `*WithRefs` types stay importable from `~/services/useX`.
- Nothing under `app/demo/` imports `useApi`. Nothing under `app/services/api/` imports from `~/demo`.
- Typecheck gate after every task: `docker exec roofly-frontend npm run typecheck` → exactly the 5 known pre-existing errors (`InvoiceViewModal.vue` Tone, `payments.vue` Tone + possibly-undefined, `Icon.vue`, `EmptyState.vue`), zero new.
- Money stays integer sen; `MalaysianState` enum; sentence case — untouched by this refactor but still in force.

---

### Task 0: Make the typecheck gate work in the container

**Files:**
- Modify: `frontend/package.json` (devDependencies)
- Modify: `frontend/package-lock.json` (by npm)

**Interfaces:**
- Produces: a working `npm run typecheck` inside `roofly-frontend`, used by every later task.

- [x] **Step 1: Install `vue-tsc` as a devDependency inside the container**

Run:
```bash
docker exec roofly-frontend npm install -D vue-tsc
```
Expected: `package.json` gains `"vue-tsc": "^3.x"` under devDependencies; lockfile updated.

- [x] **Step 2: Run the typecheck and record the baseline**

Run: `docker exec roofly-frontend npm run typecheck 2>&1 | grep -E 'error TS'`
Expected: exactly these 5 lines (line numbers may differ slightly):
```
app/components/owner/InvoiceViewModal.vue(90,16): error TS2322: Type 'string' is not assignable to type 'Tone'.
app/components/ui/EmptyState.vue(20,12): error TS2345 ...
app/components/ui/Icon.vue(14,10): error TS2345 ...
app/pages/owner/payments.vue(238,11): error TS2769 ... 'Tone'
app/pages/owner/payments.vue(...): error TS... possibly 'undefined'
```
If the host-only noise (`driver.js` missing, TanStack implicit-any) appears, the run is on the host — re-run inside the container.

---

### Task 1: Contracts + auth types

**Files:**
- Create: `frontend/app/types/auth.ts`
- Create: `frontend/app/services/contracts/properties.ts`
- Create: `frontend/app/services/contracts/units.ts`
- Create: `frontend/app/services/contracts/tenants.ts`
- Create: `frontend/app/services/contracts/agreements.ts`
- Create: `frontend/app/services/contracts/invoices.ts`
- Create: `frontend/app/services/contracts/tickets.ts`
- Create: `frontend/app/services/contracts/ownerSettings.ts`
- Create: `frontend/app/services/contracts/dashboard.ts`
- Create: `frontend/app/services/contracts/auth.ts`

**Interfaces:**
- Produces: every `XService` interface, `AgreementWithRefs`, `InvoiceWithRefs`, `TicketWithRefs`, `DashboardData`/`DashboardStats`/`AttentionItem`/`AttentionKind`/`MonthlyBucket`/`IncomeBucket`, `AuthUser`/`UserRole`, `AuthAdapter`/`RegisterPayload`. All later tasks implement or import these.

- [x] **Step 1: `types/auth.ts`**

```ts
export type UserRole = "owner" | "tenant" | "admin";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
}
```

- [x] **Step 2: `services/contracts/properties.ts`**

```ts
import type { Property, PropertyInput, PropertyUpdate } from "~/types/property";

export interface PropertiesService {
  getProperties(): Promise<Property[]>;
  getProperty(id: string): Promise<Property | null>;
  create(input: PropertyInput): Promise<Property>;
  update(id: string, patch: PropertyUpdate): Promise<Property>;
  remove(id: string): Promise<void>;
}
```

- [x] **Step 3: `services/contracts/units.ts`**

```ts
import type { Unit, UnitInput, UnitUpdate } from "~/types/unit";

export interface UnitsService {
  getUnits(): Promise<Unit[]>;
  getUnitsByProperty(propertyId: string): Promise<Unit[]>;
  getUnit(id: string): Promise<Unit | null>;
  create(input: UnitInput): Promise<Unit>;
  update(id: string, patch: UnitUpdate): Promise<Unit>;
  remove(id: string): Promise<void>;
}
```

- [x] **Step 4: `services/contracts/tenants.ts`**

```ts
import type { Tenant, TenantInput, TenantUpdate } from "~/types/tenant";

export interface TenantsService {
  getTenants(): Promise<Tenant[]>;
  getTenant(id: string): Promise<Tenant | null>;
  invite(input: TenantInput): Promise<Tenant>;
  update(id: string, patch: TenantUpdate): Promise<Tenant>;
  remove(id: string): Promise<void>;
}
```

- [x] **Step 5: `services/contracts/agreements.ts`**

```ts
import type { Agreement, AgreementInput, AgreementUpdate } from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";

export interface AgreementWithRefs {
  agreement: Agreement;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
}

export interface AgreementsService {
  getAgreements(): Promise<Agreement[]>;
  getAgreementsWithRefs(): Promise<AgreementWithRefs[]>;
  getAgreement(id: string): Promise<Agreement | null>;
  /** Tenant-shell scope: the tenant's current agreement (active, else latest non-draft). */
  getActiveAgreementForTenant(tenantId: string): Promise<AgreementWithRefs | null>;
  create(input: AgreementInput): Promise<Agreement>;
  update(id: string, patch: AgreementUpdate): Promise<Agreement>;
  remove(id: string): Promise<void>;
}
```

- [x] **Step 6: `services/contracts/invoices.ts`**

```ts
import type { Invoice, InvoiceStatus } from "~/types/invoice";
import type { Payment, PaymentInput } from "~/types/payment";
import type { Agreement } from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";

export interface InvoiceWithRefs {
  invoice: Invoice;
  agreement: Agreement | null;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
  payments: Payment[];
}

export interface InvoicesService {
  getInvoices(): Promise<Invoice[]>;
  getInvoicesWithRefs(): Promise<InvoiceWithRefs[]>;
  getInvoice(id: string): Promise<Invoice | null>;
  /** Tenant-shell scope: invoices across all of the tenant's agreements. */
  getInvoicesForTenant(tenantId: string): Promise<InvoiceWithRefs[]>;
  updateStatus(id: string, status: InvoiceStatus): Promise<Invoice>;
  recordPayment(input: PaymentInput): Promise<{ payment: Payment; invoice: Invoice }>;
  sendInvoice(id: string): Promise<{ sentAt: string }>;
}
```

- [x] **Step 7: `services/contracts/tickets.ts`**

```ts
import type {
  Ticket,
  TicketComment,
  TicketCommentInput,
  TicketInput,
  TicketStatus,
} from "~/types/ticket";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";

export interface TicketWithRefs {
  ticket: Ticket;
  unit: Unit | null;
  property: Property | null;
  reporter: Tenant | null; // null when reporterRole === "owner"
  comments: TicketComment[];
}

export interface TicketsService {
  getTickets(): Promise<Ticket[]>;
  getTicketsWithRefs(): Promise<TicketWithRefs[]>;
  getTicket(id: string): Promise<Ticket | null>;
  getTicketWithRefs(id: string): Promise<TicketWithRefs | null>;
  /** Tenant-shell scope: issues the tenant reported themselves. */
  getTicketsForTenant(tenantId: string): Promise<TicketWithRefs[]>;
  create(input: TicketInput): Promise<Ticket>;
  transitionStatus(id: string, next: TicketStatus): Promise<Ticket>;
  addComment(input: TicketCommentInput): Promise<TicketComment>;
}
```

- [x] **Step 8: `services/contracts/ownerSettings.ts`**

```ts
import type {
  NotificationPreferencesUpdate,
  OwnerAccount,
  OwnerPreferencesUpdate,
  OwnerProfileUpdate,
  Plan,
} from "~/types/owner";

export interface OwnerSettingsService {
  getAccount(): Promise<OwnerAccount>;
  updateProfile(patch: OwnerProfileUpdate): Promise<OwnerAccount>;
  updatePreferences(patch: OwnerPreferencesUpdate): Promise<OwnerAccount>;
  updateNotifications(patch: NotificationPreferencesUpdate): Promise<OwnerAccount>;
  getPlans(): Promise<Plan[]>;
}
```

- [x] **Step 9: `services/contracts/dashboard.ts`** (types lifted verbatim from `composables/useDashboard.ts:15-52`)

```ts
export type AttentionKind =
  | "overdue"
  | "expiring"
  | "notice_given"
  | "ticket_new"
  | "ticket_reopened";

export interface AttentionItem {
  kind: AttentionKind;
  title: string;
  meta: string;
  link: string;
}

export interface DashboardStats {
  monthlyIncome: number; // sen
  occupancyPct: number;
  occupiedCount: number;
  unitCount: number;
  outstanding: number; // sen
  outstandingCount: number;
  expiringCount: number;
}

/** Raw server bucket — the localized label is derived on the client. */
export interface IncomeBucket {
  key: string; // YYYY-MM
  amount: number; // sen
}

export interface MonthlyBucket extends IncomeBucket {
  label: string; // localized short month name
}

/** The exact `GET /api/dashboard` payload (also built from demo data in demo). */
export interface DashboardData {
  isEmpty: boolean;
  stats: DashboardStats;
  incomeSeries: IncomeBucket[];
  needsAttention: AttentionItem[];
}

export interface DashboardService {
  getDashboard(): Promise<DashboardData>;
}
```

- [x] **Step 10: `services/contracts/auth.ts`**

```ts
import type { AuthUser } from "~/types/auth";

export interface RegisterPayload {
  name: string;
  email: string;
  phone: string;
  password: string;
}

export interface AuthAdapter {
  login(email: string, password: string): Promise<AuthUser>;
  register(payload: RegisterPayload): Promise<AuthUser>;
  logout(): Promise<void>;
  /** Boot hydration. Resolves `null` when not signed in — that case never throws. */
  fetchMe(): Promise<AuthUser | null>;
}
```

- [x] **Step 11: Typecheck** — baseline 5 errors, nothing new (the files are not yet imported anywhere).

---

### Task 2: Move seed data to `demo/data/`

**Files:**
- Move: `frontend/app/mocks/*.ts` → `frontend/app/demo/data/*.ts` (7 files, `git mv`)
- Modify: `frontend/app/demo/data/invoices.ts:4` (internal import path)

**Interfaces:**
- Produces: `~/demo/data/{agreements,invoices,owner,properties,tenants,tickets,units}` with the same named exports as before (`propertiesMock`, `unitsMock`, `tenantsMock`, `agreementsMock`, `invoicesMock`, `paymentsMock`, `ticketsMock`, `ticketCommentsMock`, `ownerAccountMock`, `plansMock`).

- [x] **Step 1: Move with history**

```bash
cd /Users/BHQIMBP16/Developer/roofly/frontend/app
mkdir -p demo/data
git mv mocks/agreements.ts mocks/invoices.ts mocks/owner.ts mocks/properties.ts mocks/tenants.ts mocks/tickets.ts mocks/units.ts demo/data/
rmdir mocks
```

- [x] **Step 2: Fix the one intra-seed import**

`demo/data/invoices.ts` line 4: `import { agreementsMock } from "~/mocks/agreements";` → `from "~/demo/data/agreements";`

- [x] **Step 3: Temporarily repoint the existing consumers so the tree still typechecks**

```bash
sed -i '' 's#~/mocks/#~/demo/data/#g' services/useAgreements.ts services/useInvoices.ts services/useOwnerSettings.ts services/useProperties.ts services/useTenants.ts services/useTickets.ts services/useUnits.ts composables/useDashboard.ts
grep -rn '~/mocks' . ; # expected: no output
```

- [x] **Step 4: Typecheck** — baseline 5, nothing new.

---

### Task 3: Properties + Units (worked example of the pattern)

**Files:**
- Create: `frontend/app/demo/services/properties.ts`, `frontend/app/services/api/properties.ts`
- Create: `frontend/app/demo/services/units.ts`, `frontend/app/services/api/units.ts`
- Rewrite: `frontend/app/services/useProperties.ts`, `frontend/app/services/useUnits.ts`

**Interfaces:**
- Consumes: `PropertiesService`, `UnitsService` (Task 1); `propertiesMock`, `unitsMock` (Task 2).
- Produces: `demoProperties`, `apiProperties`, `demoUnits`, `apiUnits`; `useProperties()`/`useUnits()` unchanged to callers.

- [x] **Step 1: `demo/services/properties.ts`** — bodies are `services/useProperties.ts` `if (useMock)` blocks, verbatim

```ts
import type { Property } from "~/types/property";
import type { PropertiesService } from "~/services/contracts/properties";
import { propertiesMock } from "~/demo/data/properties";

export const demoProperties: PropertiesService = {
  async getProperties() {
    return structuredClone(propertiesMock);
  },

  async getProperty(id) {
    const found = propertiesMock.find((p) => p.id === id);
    return found ? structuredClone(found) : null;
  },

  async create(input) {
    // Auto-insert the creating user as the primary co-owner with 100% share.
    const primaryCoOwnerId = crypto.randomUUID();
    const created: Property = {
      id: crypto.randomUUID(),
      ownerId: primaryCoOwnerId,
      ...input,
      coOwners: [
        { id: primaryCoOwnerId, name: "Primary owner", sharePct: 100, isPrimary: true },
      ],
      createdAt: new Date().toISOString(),
    };
    propertiesMock.push(created);
    return structuredClone(created);
  },

  async update(id, patch) {
    const idx = propertiesMock.findIndex((p) => p.id === id);
    if (idx === -1) throw new Error(`Property ${id} not found`);
    const existing = propertiesMock[idx]!;
    const merged: Property = {
      ...existing,
      ...patch,
      ownership: patch.ownership
        ? { ...(existing.ownership ?? {}), ...patch.ownership }
        : existing.ownership,
      utilities: patch.utilities
        ? { ...(existing.utilities ?? {}), ...patch.utilities }
        : existing.utilities,
      // coOwners replaces wholesale (it's a list, not a partial object).
      // Keep ownerId in sync with whichever entry is marked primary.
      coOwners: patch.coOwners ?? existing.coOwners,
      ownerId: patch.coOwners
        ? (patch.coOwners.find((c) => c.isPrimary)?.id ?? existing.ownerId)
        : existing.ownerId,
    };
    propertiesMock[idx] = merged;
    return structuredClone(merged);
  },

  async remove(id) {
    const idx = propertiesMock.findIndex((p) => p.id === id);
    if (idx !== -1) propertiesMock.splice(idx, 1);
  },
};
```

- [x] **Step 2: `services/api/properties.ts`** — bodies are the non-mock branches, verbatim

```ts
import type { Property } from "~/types/property";
import type { PropertiesService } from "~/services/contracts/properties";

export const apiProperties: PropertiesService = {
  getProperties: () => useApi().request<Property[]>("/properties"),
  getProperty: (id) => useApi().request<Property>(`/properties/${id}`),
  create: (input) =>
    useApi().request<Property>("/properties", { method: "POST", body: input }),
  update: (id, patch) =>
    useApi().request<Property>(`/properties/${id}`, { method: "PATCH", body: patch }),
  remove: async (id) => {
    await useApi().request(`/properties/${id}`, { method: "DELETE" });
  },
};
```

- [x] **Step 3: `services/useProperties.ts`** — full replacement

```ts
import type { PropertiesService } from "~/services/contracts/properties";
import { demoProperties } from "~/demo/services/properties";
import { apiProperties } from "~/services/api/properties";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useProperties = (): PropertiesService =>
  useEnv().useMock ? demoProperties : apiProperties;
```

- [x] **Step 4: Units — same three files.** `demo/services/units.ts` exports `demoUnits: UnitsService` with the six `if (useMock)` bodies from `services/useUnits.ts` (getUnits: clone all; getUnitsByProperty: clone filter by `propertyId`; getUnit: find-or-null; create: `{ id: crypto.randomUUID(), ...input, createdAt: now }` pushed; update: findIndex/throw `Unit ${id} not found`/spread-merge; remove: findIndex/splice). `services/api/units.ts` exports `apiUnits: UnitsService` with paths `/units`, `/properties/${propertyId}/units`, `/units/${id}`, `POST /properties/${input.propertyId}/units`, `PATCH /units/${id}`, `DELETE /units/${id}`. `services/useUnits.ts` becomes the two-line selector returning `UnitsService`.

- [x] **Step 5: Typecheck** — baseline 5, nothing new. `grep -rn "useMock" services/useProperties.ts services/useUnits.ts` shows exactly one hit each.

---

### Task 4: Tenants + Owner settings

**Files:**
- Create: `demo/services/tenants.ts`, `services/api/tenants.ts`, `demo/services/ownerSettings.ts`, `services/api/ownerSettings.ts`
- Rewrite: `services/useTenants.ts`, `services/useOwnerSettings.ts`

**Interfaces:**
- Consumes: `TenantsService`, `OwnerSettingsService`; `tenantsMock`, `ownerAccountMock`, `plansMock`.
- Produces: `demoTenants`/`apiTenants`, `demoOwnerSettings`/`apiOwnerSettings`.

- [x] **Step 1: Tenants.** Demo bodies from `services/useTenants.ts` (invite sets `status: "invited"`, `invitedAt: now`, `createdAt: now`; update deep-merges `personal` and `emergencyContact`; remove splices). API paths: `GET /tenants`, `GET /tenants/${id}`, `POST /tenants/invite`, `PATCH /tenants/${id}`, `DELETE /tenants/${id}`.

- [x] **Step 2: Owner settings.** Demo bodies mutate `ownerAccountMock.profile/preferences/notifications` exactly as today (notifications merges `events` and `channels` separately) and return `structuredClone(ownerAccountMock)`; `getPlans` clones `plansMock`. API paths: `GET /account`, `PATCH /account/profile`, `PATCH /account/preferences`, `PATCH /account/notifications`, `GET /plans`.

- [x] **Step 3: Selectors** — `useTenants(): TenantsService`, `useOwnerSettings(): OwnerSettingsService`, same two-line shape as Task 3 Step 3.

- [x] **Step 4: Typecheck** — baseline 5, nothing new.

---

### Task 5: Agreements + Invoices + Tickets (the `WithRefs` services)

**Files:**
- Create: `demo/services/agreements.ts`, `services/api/agreements.ts`, `demo/services/invoices.ts`, `services/api/invoices.ts`, `demo/services/tickets.ts`, `services/api/tickets.ts`
- Rewrite: `services/useAgreements.ts`, `services/useInvoices.ts`, `services/useTickets.ts`

**Interfaces:**
- Consumes: `AgreementsService`/`AgreementWithRefs`, `InvoicesService`/`InvoiceWithRefs`, `TicketsService`/`TicketWithRefs`; all seed arrays.
- Produces: `demoAgreements`/`apiAgreements`, `demoInvoices`/`apiInvoices`, `demoTickets`/`apiTickets`. The three `use*.ts` files **re-export** their `*WithRefs` type so the 20 existing `import type { … } from "~/services/useX"` sites keep compiling.

- [x] **Step 1: Agreements.** The module-level `hydrate` helper moves into `demo/services/agreements.ts` unchanged (it only reads seed arrays). Demo bodies from `services/useAgreements.ts` incl. `getActiveAgreementForTenant` (active, else latest non-draft by `startDate` desc). API paths: `/agreements`, `/agreements?expand=unit,property,tenant`, `/agreements/${id}`, `/me/agreement?expand=unit,property,tenant`, `POST /agreements`, `PATCH /agreements/${id}`, `DELETE /agreements/${id}`.

Selector:
```ts
import type { AgreementsService } from "~/services/contracts/agreements";
import { demoAgreements } from "~/demo/services/agreements";
import { apiAgreements } from "~/services/api/agreements";

export type { AgreementWithRefs } from "~/services/contracts/agreements";

export const useAgreements = (): AgreementsService =>
  useEnv().useMock ? demoAgreements : apiAgreements;
```

- [x] **Step 2: Invoices.** `hydrate` moves to `demo/services/invoices.ts`. Demo bodies from `services/useInvoices.ts` (recordPayment pushes a `successful` payment and flips the invoice to `paid`; `sendInvoice` returns `{ sentAt: now }`). API paths: `/invoices`, `/invoices?expand=agreement,unit,property,tenant,payments`, `/invoices/${id}`, `/me/invoices?expand=agreement,unit,property,tenant,payments`, `PATCH /invoices/${id}` body `{ status }`, `POST /invoices/${input.invoiceId}/payments`, `POST /invoices/${id}/send`. Selector re-exports `InvoiceWithRefs`.

- [x] **Step 3: Tickets.** `hydrate` moves to `demo/services/tickets.ts`. Demo bodies from `services/useTickets.ts` (create sets `status: "new"`, `createdAt`/`updatedAt`; transitionStatus stamps `resolvedAt` when `next === "resolved"`; addComment pushes to `ticketCommentsMock` and bumps the ticket's `updatedAt`). API paths: `/tickets`, `/tickets?expand=unit,property,reporter,comments`, `/tickets/${id}`, `/tickets/${id}?expand=unit,property,reporter,comments`, `/me/tickets?expand=unit,property,reporter,comments`, `POST /tickets`, `PATCH /tickets/${id}/status` body `{ status: next }`, `POST /tickets/${input.ticketId}/comments` body `{ body: input.body }`. Selector re-exports `TicketWithRefs`.

- [x] **Step 4: Typecheck** — baseline 5, nothing new. `grep -rln "~/demo/data" services/` must list nothing (only `demo/` may import seed data now).

---

### Task 6: Dashboard

**Files:**
- Create: `demo/services/dashboard.ts`, `services/api/dashboard.ts`
- Rewrite: `composables/useDashboard.ts`

**Interfaces:**
- Consumes: `DashboardService`, `DashboardData` etc. (Task 1).
- Produces: `demoDashboard`, `apiDashboard`; `useDashboard()` returns the same `{ getDashboard, loading, isEmpty, stats, needsAttention, monthlyIncomeSeries }`; re-exports `MonthlyBucket` (imported by `composables/useReports.ts:8`) and the other dashboard types.

- [x] **Step 1: `demo/services/dashboard.ts`** — `buildFromMocks` (current `useDashboard.ts:64-205`) moves here verbatim, renamed `buildFromDemoData`, with `DAY_MS`/`ymKey` helpers and the six seed imports from `~/demo/data/*`. Export:

```ts
export const demoDashboard: DashboardService = {
  async getDashboard() {
    return buildFromDemoData();
  },
};
```

- [x] **Step 2: `services/api/dashboard.ts`**

```ts
import type { DashboardData, DashboardService } from "~/services/contracts/dashboard";

export const apiDashboard: DashboardService = {
  getDashboard: () => useApi().request<DashboardData>("/dashboard"),
};
```

- [x] **Step 3: `composables/useDashboard.ts`** — keeps only the reactive wrapper:

```ts
import { computed, ref } from "vue";
import type {
  AttentionItem,
  DashboardData,
  DashboardStats,
  MonthlyBucket,
} from "~/services/contracts/dashboard";
import { demoDashboard } from "~/demo/services/dashboard";
import { apiDashboard } from "~/services/api/dashboard";

export type {
  AttentionItem,
  AttentionKind,
  DashboardData,
  DashboardStats,
  IncomeBucket,
  MonthlyBucket,
} from "~/services/contracts/dashboard";

const EMPTY_STATS: DashboardStats = {
  monthlyIncome: 0,
  occupancyPct: 0,
  occupiedCount: 0,
  unitCount: 0,
  outstanding: 0,
  outstandingCount: 0,
  expiringCount: 0,
};

/**
 * Owner dashboard state. One `getDashboard()` fetch — the API (or the demo
 * builder) computes stats, the income series, and the attention feed; the
 * only client-side work left is localizing month labels.
 */
export const useDashboard = () => {
  const service = useEnv().useMock ? demoDashboard : apiDashboard;

  const loading = ref(true);
  const data = ref<DashboardData | null>(null);

  const getDashboard = async () => {
    loading.value = true;
    try {
      data.value = await service.getDashboard();
    } finally {
      loading.value = false;
    }
  };

  const isEmpty = computed(() => data.value?.isEmpty ?? true);
  const stats = computed<DashboardStats>(() => data.value?.stats ?? EMPTY_STATS);
  const needsAttention = computed<AttentionItem[]>(
    () => data.value?.needsAttention ?? [],
  );

  // Localize month labels client-side so the server payload stays locale-free.
  const monthlyIncomeSeries = computed<MonthlyBucket[]>(() =>
    (data.value?.incomeSeries ?? []).map((b) => ({
      key: b.key,
      amount: b.amount,
      label: new Date(`${b.key}-01`).toLocaleDateString("en-MY", { month: "short" }),
    })),
  );

  return { getDashboard, loading, isEmpty, stats, needsAttention, monthlyIncomeSeries };
};
```

- [x] **Step 4: Typecheck** — baseline 5, nothing new. `grep -rn "~/demo/data" composables/` → nothing.

---

### Task 7: Auth adapters + store + tenant session

**Files:**
- Create: `demo/auth.ts`, `services/api/auth.ts`
- Rewrite: `stores/auth.ts`
- Modify: `composables/useTenantSession.ts`

**Interfaces:**
- Consumes: `AuthAdapter`, `RegisterPayload` (Task 1), `AuthUser`/`UserRole` (`types/auth.ts`).
- Produces: `demoAuth`, `DEMO_TENANT_ID`, `apiAuth`. `useAuthStore` keeps `user`, `loading`, `authReady`, getters `isAuthenticated/isOwner/isTenant/isAdmin`, actions `login/register/logout/fetchMe`, and re-exports `AuthUser`/`UserRole` (the store is the current import site for those names).

- [x] **Step 1: `services/api/auth.ts`** — current store bodies, verbatim

```ts
import type { AuthUser } from "~/types/auth";
import type { AuthAdapter } from "~/services/contracts/auth";

/** Sanctum SPA cookie auth. The httpOnly session cookie is the only persistence. */
export const apiAuth: AuthAdapter = {
  async login(email, password) {
    const { request } = useApi();
    // Prime the CSRF cookie before the stateful POST.
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/login", {
      method: "POST",
      body: { email, password },
    });
    return res.user;
  },

  async register(payload) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/register", {
      method: "POST",
      body: { ...payload, password_confirmation: payload.password },
    });
    return res.user;
  },

  async logout() {
    try {
      await useApi().request("/auth/logout", { method: "POST" });
    } catch {
      // Even if the server call fails, the store drops local state.
    }
  },

  async fetchMe() {
    try {
      return await useApi().request<AuthUser>("/auth/me");
    } catch {
      // 401 is the expected "not logged in" case.
      return null;
    }
  },
};
```

- [x] **Step 2: `demo/auth.ts`** — restores the pre-Phase-2 mock (see `git show 40a566f -- frontend/app/stores/auth.ts` for the removed original)

```ts
import type { AuthUser, UserRole } from "~/types/auth";
import type { AuthAdapter } from "~/services/contracts/auth";

/**
 * Demo auth. No backend: the email prefix decides the role, and the user is
 * persisted to localStorage so a refresh keeps the demo session (there is no
 * session cookie in demo). Never touches the network.
 *
 * A demo tenant signs in as the richest seeded tenant (Aminah — active
 * agreement, paid + outstanding invoices, open + resolved issues) so every
 * tenant surface has data. `useTenantSession` binds to the same id.
 */
export const DEMO_TENANT_ID = "t-aminah";
const DEMO_OWNER_ID = "stub-owner"; // matches demo/data/owner.ts profile.id

const STORAGE_KEY = "roofly_auth";

const persist = (user: AuthUser | null) => {
  if (!import.meta.client) return;
  try {
    if (user) localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    else localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Quota / private mode — non-fatal in demo.
  }
};

const restore = (): AuthUser | null => {
  if (!import.meta.client) return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
};

const roleFor = (email: string): UserRole =>
  email.startsWith("tenant") ? "tenant" : email.startsWith("admin") ? "admin" : "owner";

const userFor = (email: string, role: UserRole): AuthUser =>
  role === "tenant"
    ? { id: DEMO_TENANT_ID, name: "Aminah Binti Yusof", email, phone: "+60 12-345 6789", role }
    : {
        id: role === "admin" ? "stub-admin" : DEMO_OWNER_ID,
        name: role === "admin" ? "Admin" : "Cik Aminah",
        email,
        phone: null,
        role,
      };

const delay = () => new Promise((r) => setTimeout(r, 300));

export const demoAuth: AuthAdapter = {
  async login(email) {
    await delay();
    const user = userFor(email, roleFor(email));
    persist(user);
    return user;
  },

  async register(payload) {
    await delay();
    const user: AuthUser = {
      id: DEMO_OWNER_ID,
      name: payload.name,
      email: payload.email,
      phone: payload.phone,
      role: "owner",
    };
    persist(user);
    return user;
  },

  async logout() {
    persist(null);
  },

  async fetchMe() {
    return restore();
  },
};
```

- [x] **Step 3: `stores/auth.ts`** — full replacement

```ts
import { defineStore } from "pinia";
import type { AuthUser } from "~/types/auth";
import type { AuthAdapter, RegisterPayload } from "~/services/contracts/auth";
import { demoAuth } from "~/demo/auth";
import { apiAuth } from "~/services/api/auth";

export type { AuthUser, UserRole } from "~/types/auth";

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
  /** False until the boot `fetchMe()` has settled — the route guard waits on this. */
  authReady: boolean;
}

/** Demo → localStorage-backed stub; otherwise Sanctum SPA cookie auth. */
const adapter = (): AuthAdapter => (useEnv().useMock ? demoAuth : apiAuth);

export const useAuthStore = defineStore("auth", {
  state: (): AuthState => ({ user: null, loading: false, authReady: false }),

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
        this.user = await adapter().login(email, password);
      } finally {
        this.loading = false;
      }
    },

    async register(payload: RegisterPayload) {
      this.loading = true;
      try {
        this.user = await adapter().register(payload);
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      await adapter().logout();
      this.user = null;
    },

    /**
     * Boot hydration. Always marks the session ready so the route guard can
     * proceed; a signed-out result is `user = null`, not an error.
     */
    async fetchMe() {
      try {
        this.user = await adapter().fetchMe();
      } catch {
        this.user = null;
      } finally {
        this.authReady = true;
      }
    },
  },
});
```

- [x] **Step 4: `composables/useTenantSession.ts`** — replace the local constant

Remove `const DEMO_TENANT_ID = "t-aminah";` and add `import { DEMO_TENANT_ID } from "~/demo/auth";`. Body otherwise unchanged.

- [x] **Step 5: Typecheck** — baseline 5, nothing new. Then the import-direction audit:

```bash
cd /Users/BHQIMBP16/Developer/roofly/frontend/app
grep -rn "useApi" demo/                 # expected: nothing
grep -rn "~/demo" services/api/         # expected: nothing
grep -rn "~/demo/data" services/use*.ts composables/ stores/ pages/ components/   # expected: nothing
grep -rn "if (useMock)" .               # expected: nothing
grep -rn "useMock" services/use*.ts composables/useDashboard.ts composables/useTenantSession.ts stores/auth.ts   # expected: exactly one hit per file
```

---

### Task 8: Docs + smoke test

**Files:**
- Modify: `.claude/CLAUDE.md` ("What this is", "Current state → Backend", "Where things live", "Mock toggle" convention, "Mock auth credentials")
- Modify: `docs/frontend/MOCK-POC.md` (one short note pointing at the spec — where the demo layer lives now)

- [x] **Step 1: CLAUDE.md** — update the tree block to the one in the spec §3; replace "No backend exists yet … `useMock` runtime toggle" with a sentence saying the Laravel API is on this branch and demo/API are split into `demo/` vs `services/api/` selected by `useEnv().useMock`; change "Backend — not started" to "Backend — Laravel 11, contract-aligned, 59 feature tests; see `docs/superpowers/specs/2026-07-21-*`"; under conventions add "**Demo never imports `useApi`; `services/api` never imports `demo/`.** New features: contract → demo adapter → API adapter (stub until backend lands) → UI behind a `features.*` flag." Mock auth credentials block: label it "Demo auth credentials (demo mode only)".

- [x] **Step 2: Demo-mode smoke** (no backend needed)

```bash
cd /Users/BHQIMBP16/Developer/roofly
# temporarily: NUXT_PUBLIC_APP_ENV=demo in .env, then
docker compose up -d frontend
```
Browser: `/demo` → Continue as owner → dashboard renders → add a property → refresh (session survives) → logout → Continue as tenant → tenant home shows Aminah's data → report an issue. Network tab: **zero** requests to `:8000`.

- [x] **Step 3: API-mode smoke**

Restore `.env` to `uat` / `false`, `docker compose up -d` (full stack), `docker exec roofly-backend php artisan migrate:fresh --seed --force`. Login `aminah@roofly.my` / `password` → dashboard from `GET /dashboard` → properties list. Same screens as before the refactor.

- [x] **Step 4: Report** — typecheck output, the audit greps, smoke results. **Do not commit**; hand to the user for final check.

---

## Self-Review

**Spec coverage:** §2 selection point → Tasks 3–7 selectors. §2 contract → Task 1. §2 demo folder / `git mv` → Task 2. §2 import direction → Task 7 Step 5 audit. §2 public names + `*WithRefs` re-exports → Task 5 selectors. §2 auth (`types/auth.ts`, `AuthAdapter`, demo localStorage, `authReady` unchanged) → Tasks 1, 7. §2 dashboard → Task 6. §2 tenant session → Task 7 Step 4. §3 tree → Tasks 2–7. §6 testing (vue-tsc, baseline, smoke) → Tasks 0, 8. §7 follow-ups intentionally have no task.

**Placeholder scan:** Tasks 3–5 Step "same three files" describe other entities by exact method→body/path mapping referencing the current source, with the full worked example in Task 3 — acceptable because the executor is moving existing code verbatim, not inventing it. No TBD/TODO.

**Type consistency:** `demoX`/`apiX` names match between create steps and selectors. `RegisterPayload` defined in `contracts/auth.ts`, used by `apiAuth.register`, `demoAuth.register`, and the store action. `DEMO_TENANT_ID` exported from `demo/auth.ts`, imported by `useTenantSession`. `MonthlyBucket` re-exported from `useDashboard` for `useReports`. `AuthUser`/`UserRole` re-exported from the store for any existing `import type { AuthUser } from "~/stores/auth"`.
