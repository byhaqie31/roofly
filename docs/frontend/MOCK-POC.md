# Roofly Frontend Mock POC

> **Status:** Draft — Phase 2 (frontend-first, backend follows).
> **Goal:** Build the full owner UX against typed TypeScript mocks so the backend team has a clear API contract to implement, and the frontend has zero hard dependency on Laravel until the swap is ready.
> **Override priority:** [UI-STANDARDS.md](UI-STANDARDS.md) > [PROJECT.md](../global/PROJECT.md) > this doc. This file describes a *temporary scaffold*.

---

## 1. Why mock-first

- Backend (Laravel + Sanctum) is still being scaffolded — see [PROJECT.md § Database schema](../global/PROJECT.md#L328).
- We don't want UI work blocked on API readiness, and we don't want to scatter `if (mock) ... else ...` checks across every component.
- Mocks double as a **contract**: when the backend lands, response shapes must match the TypeScript types in `app/types/`.

**Non-goals:** persistent state across reloads, network simulation, MSW. Just plain in-memory arrays and async-shaped functions.

---

## 2. Folder layout

```
frontend/app/
├── types/                ← single source of truth for entity shapes
│   ├── property.ts
│   ├── unit.ts
│   ├── tenant.ts
│   └── ...
├── mocks/                ← seed data, only imported by services
│   ├── properties.ts
│   ├── units.ts
│   └── ...
├── services/             ← the swap point: today returns mocks, tomorrow calls useApi()
│   ├── useProperties.ts
│   ├── useUnits.ts
│   └── ...
└── composables/
    └── useApi.ts         ← unchanged; services will start using it post-swap
```

**Rule:** pages and components import from `services/` only. They must never `import { propertiesMock } from "~/mocks/..."`. That single boundary is what makes the swap painless.

---

## 3. The service pattern

Each service composable returns the same shape regardless of backing store. The mock flag is sourced from `runtimeConfig.public.useMock` so all services check one place — flip per-environment via env var, no code edits, no per-service drift.

```ts
// nuxt.config.ts
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      // Default true during Phase 2; set NUXT_PUBLIC_USE_MOCK=false once backend lands.
      useMock: true,
    },
  },
});
```

```ts
// app/services/useProperties.ts
import type { Property, PropertyInput } from "~/types/property";
import { propertiesMock } from "~/mocks/properties";

export const useProperties = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Property[]> => {
    if (useMock) return structuredClone(propertiesMock);
    const { request } = useApi();
    return request<Property[]>("/properties");
  };

  const create = async (input: PropertyInput): Promise<Property> => {
    if (useMock) {
      const created: Property = {
        id: crypto.randomUUID(),
        ...input,
        createdAt: new Date().toISOString(),
      };
      propertiesMock.push(created);
      return created;
    }
    const { request } = useApi();
    return request<Property>("/properties", { method: "POST", body: input });
  };

  return { list, create };
};
```

Notes:
- One source of truth for the mock toggle (`runtimeConfig.public.useMock`); per-entity migration just deletes the `if (useMock)` branch in that service.
- Services always return promises, even for mocks, so call sites are identical post-swap.
- `structuredClone` on read prevents accidental mutation of the seed array.

---

## 4. Properties

### 4.1 Type

Schema source: [PROJECT.md § properties table](../global/PROJECT.md#L328) extended with two JSON sub-objects (`ownership` + `utilities`) that map to the detail-page tabs, plus a top-level `coOwners` array that maps to a **separate `property_co_owners` table** on the backend (relational integrity matters for joint ownership — see §4.7). Money is integer **sen** (matches [useMoney.ts](../../frontend/app/composables/useMoney.ts)).

```ts
// app/types/property.ts (excerpt)
import type { MalaysianState } from "./geography";

export interface PropertyMortgage {
  bank?: string;
  loanAmount?: number;          // sen
  outstandingBalance?: number;  // sen
  monthlyInstalment?: number;   // sen
  tenureYears?: number;
  maturityDate?: string;
  interestRatePct?: number;
}

export interface PropertyCoOwner {
  id: string;                   // uuid; primary owner has isPrimary=true
  name: string;
  sharePct: number;             // 0-100; sum across all co-owners must === 100
  isPrimary: boolean;           // exactly one true; matches Property.ownerId
}

export interface PropertyOwnership {
  // Title — for the Title section
  titleType?: "freehold" | "leasehold";
  titleNumber?: string;
  lotNumber?: string;
  tenureExpiry?: string;
  strataTitle?: boolean;
  masterTitle?: boolean;
  // Acquisition — drives the gains snapshot
  purchaseDate?: string;
  purchasePrice?: number;       // sen
  stampDuty?: number;           // sen
  legalFees?: number;           // sen
  // Valuation
  currentMarketValue?: number;  // sen
  lastValuedAt?: string;
  valuationSource?: "bank" | "agent" | "self";
  // Mortgage (optional)
  mortgage?: PropertyMortgage;
}

export interface PropertyUtilities {
  monthlyMaintenanceFee?: number;
  sinkingFund?: number;
  quitRentAnnual?: number;
  assessmentRateAnnual?: number;
  buildingInsuranceAnnual?: number;
  tnbAccountNo?: string;
  waterAccountNo?: string;
  indahWaterAccountNo?: string;
  internetAccountNo?: string;
  managementCorpName?: string;
  managementCorpPhone?: string;
}

export interface Property {
  // Identity
  id: string;
  ownerId: string;              // matches the `isPrimary: true` co-owner
  name: string;
  internalLabel?: string;
  type: "condo" | "landed" | "shoplot" | "room";
  notes?: string;
  // Location
  address: string;
  city: string;
  state: MalaysianState;        // see §4.3
  postcode: string;
  // Specifications
  yearBuilt?: number;
  builtUpSqft?: number;
  landSqft?: number;
  bedrooms?: number;
  bathrooms?: number;
  parkingLots?: number;
  furnishing?: "unfurnished" | "partial" | "fully";
  // JSON sub-objects — one per detail-page tab
  ownership?: PropertyOwnership;
  utilities?: PropertyUtilities;
  // Joint ownership — separate table on backend (see §4.7)
  coOwners: PropertyCoOwner[];  // always at least one entry (the primary owner)
  // Server-assigned
  createdAt: string;
}

// Add Property modal still captures Tier 1 only — everything else is edited on the detail page.
export type PropertyInput = Pick<
  Property,
  "name" | "address" | "city" | "state" | "postcode" | "type"
>;
```

### 4.2 Add Property modal — field spec (Tier 1 only)

| Field | Type | Required | Validation | Notes |
|---|---|---|---|---|
| `name` | text | ✓ | 2–80 chars | Free text. Placeholder example: "Suria KLCC #12-3A" |
| `type` | select | ✓ | enum | Options: Condo / Landed / Shoplot / Room. i18n labels. |
| `address` | textarea | ✓ | 5–200 chars | One field — street + building, freeform. |
| `city` | text | ✓ | 2–60 chars | Autocomplete later; freeform for POC. |
| `state` | select | ✓ | enum (see 4.3) | 16 Malaysian states + FTs. |
| `postcode` | text | ✓ | exactly 5 digits | MY postal format `^\d{5}$`. |

UX:
- Modal component: `app/components/owner/AddPropertyModal.vue` (new).
- Triggered from the `+ Add property` button in [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
- On submit: call `useProperties().create(input)`, close modal on success, push the returned `Property` into the local list ref.
- Built on `~/components/ui/Modal.vue` + `Select.vue` (Reka UI wrappers — see §8) and existing `Input.vue` / `Button.vue`.
- Validation via **vee-validate + Zod** (already installed). Schema lives in `app/schemas/property.ts` and is shared with the detail-page edit form.
- All labels go through i18n (`en.json`, `ms.json`) under `owner.properties.addModal.*`.

### 4.3 Malaysian states (enum source)

Lives in `app/types/geography.ts` and is imported by `Property` (typed as `MalaysianState` from day one — no `string` interim).

```ts
export const MY_STATES = [
  "Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan",
  "Pahang", "Perak", "Perlis", "Pulau Pinang", "Sabah",
  "Sarawak", "Selangor", "Terengganu",
  "W.P. Kuala Lumpur", "W.P. Labuan", "W.P. Putrajaya",
] as const;
export type MalaysianState = (typeof MY_STATES)[number];
```

### 4.4 Mock seed

`app/mocks/properties.ts` carries **5 properties** with mixed fullness so the detail-page completion indicators read as expected:

1. **Fully populated condo** — Suria KLCC #12-3A.
2. **Mid landed (single unit)** — TTDI bungalow.
3. **Multi-unit landed** — Subang terrace house with master bedroom rental + 2 separate room rentals (3 units under one property — stress-tests the one-property-many-units model end-to-end).
4. **Sparse shoplot** — Wangsa Walk Shoplot G-12.
5. **Near-empty room** — USJ 9 single room.

The empty case is intentional. The multi-unit landed case is intentional too — it surfaces edge cases (per-unit rent allocation, unit-level tenant displays on the property Overview tab) before they can hide until Phase 3.

### 4.5 Detail page — tab structure

Route: `/owner/properties/[id]`. Header pattern follows [§ 4.4 Detail-page header in UI-STANDARDS](UI-STANDARDS.md). Below the title block, one Card hosts the tabs; below that Card, the `UnitsPanel` continues to render the property's units.

The Documents tab renders a **"coming in Phase 4" placeholder by default** so owners see during demos that file uploads are on the way. The faint dot on the tab trigger + the tooltip do the signaling — clicking lands on a Phase-4 empty state, not a broken feature. Toggle via `runtimeConfig.public.features.documents` (default `true`; set `NUXT_PUBLIC_FEATURE_DOCUMENTS=false` to hide entirely). The same flag will switch to gating real file storage in Phase 4 — flag mechanism stays, semantics shift.

| Tab | Mental mode | Backed by | Notes |
|---|---|---|---|
| **Overview** | "How is it doing?" | `useUnits().listByProperty` + `useAgreements().listWithRefs` | Read-only — counts of units (total / occupied), active agreements, monthly income from active rent, list of active tenants. |
| **Details** | "What is it?" | top-level `Property` columns | One form, three labeled sections: Identity, Location, Specifications. |
| **Ownership** | "What's the legal/financial picture?" | `Property.ownership` JSON + `Property.coOwners[]` | Title, Acquisition (auto-totaled), Valuation, Mortgage, Co-owners (vee-validate `useFieldArray` repeater — see invariants below), and a **capital-gains snapshot** computed via `app/utils/rpgt.ts`. |
| **Utilities** | "What does it cost to run?" | `Property.utilities` JSON | Recurring fees (auto annual + monthly equivalent), Service accounts. |
| **Documents** *(Phase 4+)* | "Where are the papers?" | — | Placeholder card visible by default — sets demo expectation that uploads are coming. Trigger carries a faint dot to read as "not wired yet". |

`useProperties().update` deep-merges `ownership` and `utilities` so each tab's form can PATCH only its own slice. `coOwners` replaces wholesale (it's a list, not a partial object).

#### Completion indicators

Each *visible* tab trigger shows a small completion indicator (✓ when 100%, amber dot when partial, faint dot when empty). Completion is **not** "every field that exists in the type" — that would give every condo a permanent 95% because `landSqft` doesn't apply. Define a deliberate required-field subset per `PropertyType`, lives in [app/utils/propertyCompletion.ts](../../frontend/app/utils/propertyCompletion.ts):

```ts
// Per-type required fields per tab. Optional/computed fields don't count toward %.
export const REQUIRED_FIELDS: Record<
  PropertyType,
  Record<"details" | "ownership" | "utilities", string[]>
> = {
  condo: {
    details: ["yearBuilt", "builtUpSqft", "bedrooms", "bathrooms", "furnishing"],
    ownership: ["titleType", "purchaseDate", "purchasePrice", "currentMarketValue"],
    utilities: ["monthlyMaintenanceFee", "quitRentAnnual", "assessmentRateAnnual"],
  },
  landed: {
    details: ["yearBuilt", "builtUpSqft", "landSqft", "bedrooms", "bathrooms"],
    ownership: ["titleType", "purchaseDate", "purchasePrice", "currentMarketValue"],
    utilities: ["quitRentAnnual", "assessmentRateAnnual"],
  },
  shoplot: { /* ... */ },
  room:    { /* ... */ },
};
```

Rules:
- Optional or computed fields (e.g. `acquisitionTotal`, `landSqft` on a condo) are not in the list — they don't drag completion down.
- The `coOwners` array is "complete" when shares sum to 100 (see invariant below), regardless of count.
- Mortgage block is "complete" if either fully filled *or* explicitly marked "no mortgage" (a UI checkbox stored as `ownership.mortgage = null`). Don't penalise cash-purchased properties.

#### Co-owner invariants

- `Property.coOwners` always contains **at least one** entry; on property creation, an entry for the creating user is inserted with `sharePct: 100, isPrimary: true`.
- Exactly one entry has `isPrimary: true`. The primary's identity (matched by `Property.ownerId`) is who manages the listing in app — display, notifications, billing-of-record.
- Sum of `sharePct` across all entries must equal `100`. Save is **blocked** when sum ≠ 100 (form-level error on the repeater); no auto-redistribute, no warning-only save — joint ownership is legally meaningful.
- Adding a co-owner does **not** auto-rebalance existing shares — the user must edit them. The repeater shows a live "Total: X% (must be 100%)" line.
- Removing the primary requires nominating a new primary first; the action is gated in the UI, not silently re-assigned.

### 4.6 Capital-gains snapshot ([utils/rpgt.ts](../../frontend/app/utils/rpgt.ts))

Resident-individual RPGT brackets:

| Holding period | Rate |
|---|---|
| Years 1–2 | 30% |
| Year 3 | 20% |
| Year 4 | 15% |
| Year 5 | 10% |
| Year 6+ | 5% |

The snapshot needs `purchasePrice`, `purchaseDate`, and `currentMarketValue` to render. Acquisition cost = `purchasePrice + stampDuty + legalFees`. Gain = `marketValue − acquisitionCost`. RPGT = `gain × bracketRate`. Net = `gain − RPGT`. UI carries an "estimate only — not tax advice. Assumes resident-individual filing; non-citizens, foreigners, and companies have different rates." disclaimer. Disposal-side allowables (agent commission, sale legal fees) and renovation expenses are out of scope for the snapshot — owners who want a precise figure should consult their tax agent.

### 4.7 Schema impact for backend

The PROJECT.md `properties` table currently covers Tier 1 only. Recommended extension:

- **Top-level columns on `properties`**: add `internal_label`, `notes`, `year_built`, `built_up_sqft`, `land_sqft`, `bedrooms`, `bathrooms`, `parking_lots`, `furnishing` as nullable columns. (Drop the previously-proposed `title_type`, `tenure_expiry`, `strata_title` from the column list — they move into the `ownership` JSON.)
- **`ownership JSON` column** on `properties` — title info, acquisition, valuation, mortgage. Flexible while landlord fields stabilise; reasonable for our query scale (one owner has tens, not millions, of properties). Revisit if a concrete report query becomes painful.
- **`utilities JSON` column** on `properties` — recurring fees + service-account reference numbers. Genuinely auxiliary; no plans to filter on `tnb_account_no`.
- **`property_co_owners` table** (new, *not* JSON) — relational integrity matters here:
  - `id`, `property_id` (FK → properties), `user_id` (FK → users, nullable for off-platform co-owners), `name`, `share_pct` (decimal 5,2), `is_primary` (bool), timestamps.
  - DB-level invariants: per `property_id`, `sum(share_pct) = 100` and `count(is_primary = true) = 1`. Enforce in a migration trigger or in the `Property` repository on save — both is fine.
  - `properties.owner_id` continues to point at the primary co-owner's `user_id`; the table just makes the joint-ownership picture explicit and queryable ("show all properties where user X is any kind of owner" becomes a simple join).
- **Mortgage stays nested in `ownership` JSON for now.** TODO: extract to a `property_mortgages` table when we add payment history, refinancing events, or multi-mortgage support.
- Photos and document uploads (Documents tab) are deferred to Phase 4+ when storage is wired; reuse the polymorphic `documents` table already in PROJECT.md.

---

## 5. Tenants

Tenants follow the same Tier 1 / 2 / 3 split as Properties. Difference: tenants are cross-property (no parent), so the detail page is a standalone route at `/owner/tenants/[id]` rather than a panel on another page.

### 5.1 Type

```ts
// app/types/tenant.ts
export type TenantStatus =
  | "invited"        // sent invite, hasn't accepted
  | "active"         // signed agreement, currently occupying
  | "notice_given"   // gave notice, still occupying — owners want the 30-day vacancy heads-up
  | "moved_out";     // gone, agreement closed

export interface TenantPersonal {
  icNumber?: string;          // MyKad — YYMMDD-PB-####
  dateOfBirth?: string;       // ISO date
  occupation?: string;
  employer?: string;
  monthlyIncome?: number;     // sen
  nationality?: string;
  photoUrl?: string;          // Phase 4+
}

export interface TenantEmergencyContact {
  name?: string;
  phone?: string;
  relationship?: string;
}

export interface Tenant {
  // Tier 1 — captured in invite modal
  id: string;
  name: string;
  email: string;
  phone: string;
  status: TenantStatus;
  invitedAt: string;
  createdAt: string;
  // Tier 2 — JSON column on backend
  personal?: TenantPersonal;
  // Tier 3 — JSON column on backend
  emergencyContact?: TenantEmergencyContact;
}
```

### 5.2 Invite modal (Tier 1 only)

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | text | ✓ | 2–80 chars |
| `email` | email | ✓ | Standard email validation |
| `phone` | tel | ✓ | MY-friendly regex `^[\d\s+\-()]{8,20}$` |

The invite flow records the contact and sets `status="invited"`. Magic-link emails arrive when authentication ships.

### 5.3 Detail page tabs

The detail route follows the [§ 4.4 Detail-page header pattern](UI-STANDARDS.md) — title block left, `Delete tenant` ghost button bottom-right of the title.

- **Identity** (Tier 1) — name, email, phone, **plus** status. Status is the one Tier 1 field not in the invite modal; new tenants always start `invited`. Allowed transitions: `invited → active → notice_given → moved_out` (and `invited → moved_out` for declined invites).
- **Personal** (Tier 2) — IC, DOB, occupation, employer, monthly income (sen ↔ ringgit at the form / service boundary), nationality.
- **Emergency contact** (Tier 3) — name, phone, relationship.
- **Documents** *(Phase 4+)* — hidden behind the same `runtimeConfig.public.features.documents` flag as the property Documents tab. IC copy, payslip, reference letter all land with Phase 4 file storage; we do not render a placeholder tab.

### 5.4 Schema impact for backend

PROJECT.md's `users` table covers Tier 1 (with `role="tenant"`). Tier 2/3 are extensions — recommend two nullable JSON columns on `users`:

- `personal_info JSON` — Tier 2.
- `emergency_contact JSON` — Tier 3.

Photo + document uploads are Phase 4+; they reuse the polymorphic `documents` table already in PROJECT.md.

### 5.5 Mock seed

`app/mocks/tenants.ts` carries 5 tenants spanning all four statuses (`invited`, `active`, `notice_given`, `moved_out`), with mixed Tier 2/3 fullness so the detail page renders both rich and sparse cases. The `notice_given` case anchors the upcoming-vacancy widgets on the dashboard.

---

## 6. Payments (invoices + payments)

The first cross-entity flow — invoices are derived from active/expired agreements (one per month based on `rentDueDay`), and payments settle invoices. Both live behind `/owner/payments`.

### 6.1 Types

```ts
// app/types/invoice.ts
export type InvoiceStatus = "pending" | "paid" | "overdue" | "cancelled";
export interface Invoice {
  id: string;
  agreementId: string;
  invoiceNumber: string;     // INV-0001, sequenced chronologically
  amount: number;            // sen
  lateFee: number;           // sen
  dueDate: string;           // ISO date
  status: InvoiceStatus;
  createdAt: string;
}

// app/types/payment.ts
export type PaymentMethod = "fpx" | "card" | "cash" | "transfer";
export type PaymentStatus = "pending" | "successful" | "failed";
export interface Payment {
  id: string;
  invoiceId: string;
  amount: number;            // sen
  method: PaymentMethod;
  status: PaymentStatus;
  paidAt: string;            // ISO datetime
  reference?: string;
  createdAt: string;
}
```

### 6.2 Mock generator

[mocks/invoices.ts](../../frontend/app/mocks/invoices.ts) walks each agreement month-by-month from `startDate` to `min(endDate, today + 30 days)`. Status is decided by simple heuristic:

- `paid` — invoice older than 30 days *or* under an `expired` / `terminated` agreement (a matching `payment` is also generated).
- `overdue` — due in the last 30 days, unpaid (carries `lateFee` from the agreement).
- `pending` — due in the future (we generate up to 30 days ahead so the table always has a "next" invoice).
- `draft` agreements produce **no** invoices.

After generation, all invoices are sorted by `dueDate` and renumbered `INV-0001..INV-NNNN` so the table reads chronologically.

### 6.3 Service

[services/useInvoices.ts](../../frontend/app/services/useInvoices.ts) exposes:

- `list()` / `listWithRefs()` — `WithRefs` joins each invoice with its agreement → unit → property → tenant + matched payments. Used by the table.
- `recordPayment(input)` — creates a `Payment`, flips invoice `status` to `paid`. Returns `{ payment, invoice }`.
- `sendInvoice(id)` — mock returns `{ sentAt }`. Backend will own actual email/WhatsApp dispatch.
- `updateStatus(id, status)` — for cancellation.

### 6.4 List page UX

[pages/owner/payments.vue](../../frontend/app/pages/owner/payments.vue) — the first **TanStack Table** screen, per [UI-STANDARDS § 3.5](UI-STANDARDS.md#L215). Filter row layout:

```
┌────────────────────────────────────────────────────────────────────┐
│ [All N] [Pending] [Overdue] [Paid] [Cancelled] [Clear]   [Month ▾] [Year ▾] │
└────────────────────────────────────────────────────────────────────┘
```

- **Status pills** — left side, with counts that reflect the *currently date-filtered* subset (so "Pending 2" inside `Year=2026` means 2 pending in 2026).
- **Month + Year dropdowns** — right side. Year options derived from the data; Month is fixed Jan–Dec via `common.months.*`. Both default to "All".
- **Sortable columns**: `dueDate` (default desc), `amount`. Invoice / Tenant / Status / Action are scan-only.
- **Pagination**: 20 rows / page via `getPaginationRowModel`. Page index resets to 0 whenever any filter changes (TanStack doesn't auto-reset).
- **Invoice number is a clickable link** — opens [InvoiceViewModal](../../frontend/app/components/owner/InvoiceViewModal.vue).
- **Action column**: `Record payment` button for `pending` / `overdue`; "Paid {date}" text for `paid`; "—" for `cancelled`.

### 6.5 Record payment modal

[RecordPaymentModal.vue](../../frontend/app/components/owner/RecordPaymentModal.vue) — pre-fills `amount` with `rent + lateFee` (ringgit at the form boundary, sen at the service boundary), `method=fpx`, `paidAt=today`. Optional free-text `reference`. Submit creates a `Payment` and flips the invoice.

### 6.6 Invoice view modal

[InvoiceViewModal.vue](../../frontend/app/components/owner/InvoiceViewModal.vue) at `size="lg"` — wide enough that the four footer actions sit on one line.

```
┌──────────────────────────────────────────────────────────┐
│ INV-0029                                          [×]   │
│   Billing period · June 2026                  [Pending] │
│   Due 04/06/2026                                        │
│                                                          │
│   BILL TO                                                │
│     Arif Hakim                                           │
│     arif.hakim@example.com · +60 17-888 1234            │
│                                                          │
│   PROPERTY                                               │
│     Wangsa Walk Shoplot G-12                             │
│     Ground floor shop                                    │
│                                                          │
│   ┌────────────────────────────────────────────────┐   │
│   │  Rent                          RM 4,000.00     │   │
│   │  Late fee (if any)                RM 50.00     │   │
│   │  ─────────────────────────────────────────────  │   │
│   │  Total due                     RM 4,050.00     │   │
│   └────────────────────────────────────────────────┘   │
│                                                          │
│   PAYMENTS RECEIVED  (when paid)                         │
│     · FPX  04/06/2026  · FPX-20260604     RM 4,000.00   │
│                                                          │
│ [Copy link]  [Download PDF]            [Send to tenant] │
└──────────────────────────────────────────────────────────┘
```

UX rules locked in here:

- **No redundant Close button** in the footer — top-right `×` and click-outside already close the modal. Reuse this rule for any future read-mostly modal.
- **Send to tenant** is the only primary action; Copy link + Download PDF are ghost-tone stubs that toast a Phase-4 disclaimer (PDF generation needs file storage).
- **Send is hidden** when `status === "cancelled"`.

### 6.7 Schema impact for backend

`invoices` and `payments` tables are already in [PROJECT.md § Database schema](../global/PROJECT.md#L328) — no extension needed. Two endpoints we mocked that the backend should expose:

- `POST /invoices/:id/payments` — record a payment + flip invoice status. Body matches `PaymentInput`.
- `POST /invoices/:id/send` — dispatch invoice via email / WhatsApp. Returns `{ sentAt }`.

Late-fee accrual is a backend concern (cron job per [PROJECT.md § Flow 3 step 6](../global/PROJECT.md#L184)). The mock just snapshots `agreement.lateFee` onto overdue invoices for display.

---

## 7. Maintenance tickets

Cross-entity flow: a `Ticket` is a tenant-or-owner-reported issue against a `Unit`, with a comment thread for back-and-forth and a four-state status workflow. Owner UX is a Kanban board + detail page.

### 7.1 Types

```ts
// app/types/ticket.ts
export type TicketStatus = "new" | "in_progress" | "resolved" | "reopened";
export type TicketCategory =
  | "plumbing" | "electrical" | "appliance" | "structural" | "pest" | "other";
export type TicketPriority = "low" | "medium" | "high" | "urgent";
export type TicketReporterRole = "owner" | "tenant";

export interface Ticket {
  id: string;
  unitId: string;                  // FK -> Unit
  reporterId: string;              // tenant id, or "owner-1" when role === "owner"
  reporterRole: TicketReporterRole;
  category: TicketCategory;
  priority: TicketPriority;
  title: string;                   // ≤100 chars, shown on Kanban cards
  description: string;             // long body, shown on detail page
  status: TicketStatus;
  createdAt: string;
  updatedAt: string;
  resolvedAt?: string;             // set when status flips to "resolved"
  // Photos: Phase 4+ (file storage)
}

export interface TicketComment {
  id: string;
  ticketId: string;
  authorId: string;
  authorRole: TicketReporterRole;
  body: string;
  createdAt: string;
}
```

### 7.2 Status workflow

A small transition map enforces the allowed paths so the detail-page action buttons stay correct. Lives next to the type for proximity:

```ts
export const TICKET_TRANSITIONS: Record<TicketStatus, TicketStatus[]> = {
  new:         ["in_progress", "resolved"],
  in_progress: ["resolved", "new"],          // "new" = regression / re-triage
  resolved:    ["reopened"],
  reopened:    ["in_progress", "resolved"],
};
```

### 7.3 Kanban + detail UX

**[pages/owner/maintenance.vue](../../frontend/app/pages/owner/maintenance.vue)** — four-column Kanban (`New` / `In progress` / `Resolved` / `Reopened`). Cards are read-only navigation: clicking a card opens the detail page. **No drag-and-drop yet** — keeps the build dependency-free. If we add it later, the same card markup wraps in a draggable.

```
┌─ Maintenance ─────────────────────────────────────────  [+ Log ticket] ─┐
│                                                                          │
│  ┌─ NEW (2) ──┐  ┌─ IN PROGRESS (2) ┐  ┌─ RESOLVED (2) ┐  ┌─ REOPENED (1) ┐
│  │ [Urgent]   │  │ [High]            │  │ [Low]          │  │ [High]      │
│  │ Power…     │  │ Bath drain…       │  │ Tap drip       │  │ Heater…     │
│  │ KLCC · …   │  │ KLCC · …          │  │ KLCC · …       │  │ Subang · …  │
│  │            │  │                   │  │                │  │             │
│  │ [Medium]   │  │ [High]            │  │ [Medium]       │  │             │
│  │ Repaint…   │  │ Termites…         │  │ Sticky lock    │  │             │
│  └────────────┘  └───────────────────┘  └────────────────┘  └─────────────┘
└──────────────────────────────────────────────────────────────────────────┘
```

**[pages/owner/maintenance/[id].vue](../../frontend/app/pages/owner/maintenance/%5Bid%5D.vue)** — detail page with three regions:

- **Header**: priority pill + status pill + category caption, title, breadcrumb to property/unit + reporter.
- **Main column**: `Description` card (full body, preserves line breaks), `Comments` thread card with author avatar (owner = ink-fill, tenant = green-soft), and an inline "Add a comment" textarea that posts owner-attributed comments.
- **Side column**: `Status` card whose buttons come straight from `TICKET_TRANSITIONS[currentStatus]` — only valid moves render. `Photos` card stays a Phase-4 stub (re-uses the `runtimeConfig.public.features.documents` flag).

**[TicketCreateModal.vue](../../frontend/app/components/owner/TicketCreateModal.vue)** — owner-side modal at `size="md"`. Fields: unit (select listing all units with property prefix), `Logged by` (Me / any tenant — mock-flexibility, real flow would auto-derive from active agreement), category, priority, summary, details. Created ticket lands in the `New` column.

### 7.4 Mock seed

[mocks/tickets.ts](../../frontend/app/mocks/tickets.ts) carries **7 tickets** spanning all four statuses, three priorities, and five categories — at least one ticket in every Kanban column with a couple in the busy ones. Tickets use realistic Malaysian-rental scenarios (storm-tripped main panel, termite frames, tap drip, water-heater regression) so the demo reads as believable.

[ticketCommentsMock](../../frontend/app/mocks/tickets.ts) gives three tickets a real thread (plumber-scheduled → tenant-confirmed → resolved; pest exterminator booked + tenant move-out note; water-heater original-fix + regression report) so the comments UI doesn't show a single ghost ticket.

### 7.5 Dashboard wire-up

`useDashboard()` (see § 8) loads `useTickets().list()` alongside the other entities and surfaces two new `AttentionKind`s in the "Needs attention" feed:

- `ticket_new` — only **high / urgent** priority new tickets (low/medium stay in the Kanban so the feed isn't noisy)
- `ticket_reopened` — *all* reopened tickets, since by definition they need re-triage

Tone mapping in [pages/owner/index.vue](../../frontend/app/pages/owner/index.vue): `ticket_new` → `pending` (amber), `ticket_reopened` → `overdue` (red).

### 7.6 Schema impact for backend

- **`tickets` table**: `id`, `unit_id` (FK), `reporter_id` (FK → users), `reporter_role` enum, `category` enum, `priority` enum, `title`, `description` (text), `status` enum, `created_at`, `updated_at`, `resolved_at` (nullable).
- **`ticket_comments` table**: `id`, `ticket_id` (FK), `author_id` (FK → users), `author_role` enum, `body` (text), `created_at`.
- **Endpoints the frontend mocks today**: `GET /tickets` (+ `?expand=unit,property,reporter,comments`), `GET /tickets/:id`, `POST /tickets`, `PATCH /tickets/:id/status` (body `{ status }`), `POST /tickets/:id/comments` (body `{ body }`).
- Photos defer to Phase 4 (polymorphic `documents` table already in PROJECT.md).

---

## 8. Dashboard & Reports

The first screens that **read across multiple entities** rather than owning one. They live on top of the existing services — no new mocks, no service writes — so the mock→backend swap for individual entities automatically picks up here.

### 7.1 Composable layer

Two read-only aggregation composables sit between the services and the pages. Each loads the same five collections in parallel (`Properties`, `Units`, `Tenants`, `Agreements`, `useInvoices().listWithRefs()` → invoices + payments) and exposes computeds for its page.

| Composable | Page | Focus |
|---|---|---|
| [useDashboard.ts](../../frontend/app/composables/useDashboard.ts) | [pages/owner/index.vue](../../frontend/app/pages/owner/index.vue) | "What's happening **right now**" — current-month tiles + trailing-12-month chart + needs-attention feed. |
| [useReports.ts](../../frontend/app/composables/useReports.ts) | [pages/owner/reports.vue](../../frontend/app/pages/owner/reports.vue) | "How did **year X** go" — year-aware totals + per-property breakdown with RPGT snapshot. Year picker derives options from actual payment/invoice data. |

Both share an aggregation contract:

- **Money is sen everywhere**. Pages format with `useMoney().formatRM` or `<MoneyDisplay>` at the boundary.
- **`successful` payments only count** toward income. `pending` / `failed` are excluded.
- **Outstanding** = sum of `(amount + lateFee)` across `pending` + `overdue` invoices.
- **Occupancy** = `units.filter(status === "occupied").length / units.length`.

### 7.2 Dashboard (`/owner`)

Header tiles → 12-month income chart → "Needs attention" list.

```
┌─────────────────────────────────────────────────────────────────┐
│  Dashboard                                                      │
│  Snapshot of how your portfolio is doing right now.            │
│                                                                 │
│  ┌Income─────┐ ┌Occupancy─┐ ┌Outstanding┐ ┌Expiring────┐      │
│  │ RM X,XXX  │ │  XX%      │ │ RM X,XXX  │ │     N      │      │
│  │ This mo.  │ │ N of M    │ │ N invoices│ │ Next 60d   │      │
│  └───────────┘ └───────────┘ └───────────┘ └────────────┘      │
│                                                                 │
│  ┌── Income — last 12 months ─────────────────────────────┐    │
│  │  ▁▂▃▅▄▆▅▇▆█  ← current month bar uses ink, others ▌   │    │
│  │  J F M A M J J A S O N D                                │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                 │
│  ┌── Needs attention ─────────────────────────────────────┐    │
│  │  [Overdue]      INV-0023 · Aminah Yusof          →    │    │
│  │  [Expiring]     Arif Hakim · 28d                  →    │    │
│  │  [Notice given] Siti Khadijah                     →    │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

`needsAttention` rolls together three signals into one click-through list:

- Each `overdue` invoice → links to `/owner/payments`
- Each `active` agreement with `endDate` within 60 days → links to `/owner/agreements`
- Each tenant in `notice_given` → links to that tenant's detail page

When a tile or feed is empty (no properties yet), the page shows the existing "Add your first property" empty state and hides the rest.

### 7.3 Reports (`/owner/reports`)

Year picker on the left, CSV + PDF buttons on the right. Below: two summary tiles, a 12-month bar chart for the chosen year, and a per-property breakdown table.

Per-property row carries: property + city, units (occupied/total), occupancy %, income for year, outstanding, **net gain (RPGT)** — pulled via `computeCapitalGains` from [utils/rpgt.ts](../../frontend/app/utils/rpgt.ts) so the resident-individual disclaimer applies (see § 4.6).

**CSV download works today** — [utils/csv.ts](../../frontend/app/utils/csv.ts) builds an RFC 4180-correct string with a UTF-8 BOM (so Excel opens it cleanly) and triggers a Blob download. No backend dependency. Filename pattern: `roofly-report-{year}.csv`.

**PDF download is a Phase-4 stub** — the button toasts a "PDF arrives once file storage is wired" message. Same Phase-4 deferral as the property/tenant Documents placeholders.

### 7.4 MiniBarChart

[components/ui/MiniBarChart.vue](../../frontend/app/components/ui/MiniBarChart.vue) — hand-rolled CSS/HTML bars, no chart library. Decision rationale:

- Monochrome aesthetic is one or two tones (`bg-ink` for the highlighted bar, `bg-ink-muted/40` for the rest, `bg-line-passive` for empty months) — a chart lib like Chart.js / unovis would either need heavy theming or fight us.
- Bundle stays clean. `chart.js` minified is ~70 KB; we don't need axes, legends, tooltips, or animations for this.
- Hover `title` per bar gives the value; `aria-label` on the wrapper carries an accessible summary.

If we ever need richer charts (multi-series, axes), revisit this — but the current need is satisfied.

### 7.5 Schema impact for backend

**No new endpoints.** Both pages are pure aggregation over data the backend already returns through the existing services. When the entity endpoints land:

1. Flip `useMock` (already centralized in `runtimeConfig.public.useMock`) and the dashboard/reports follow automatically.
2. If aggregation becomes slow at portfolio scale (probably not for a single landlord with tens of properties — see § 4.7's JSON-vs-columns reasoning), add purpose-built aggregation endpoints later: `GET /reports/dashboard?asOf=...` and `GET /reports/yearly/:year`. The composables would swap their parallel-fetch logic for a single call; the page contracts wouldn't change.

---

## 9. Settings

The account-level surface — the only page in the owner shell that doesn't read across the entity tables. Edits the logged-in owner's profile, preferences, notification rules, and subscription tier.

### 9.1 Types

```ts
// app/types/owner.ts
export interface OwnerProfile {
  id: string;                       // matches AuthUser.id from the auth store
  name: string;
  email: string;                    // managed by auth — read-only on this page
  phone: string;
  photoUrl?: string;                // Phase 4+
  businessName?: string;
  bankAccountLast4?: string;        // display-only; bank linkage lives elsewhere
}

export interface OwnerPreferences {
  locale: "en" | "ms";
  theme: "light" | "dark" | "system";
  moneyLocale: "en-MY";             // forward-compat for future locales
}

export interface NotificationPreferences {
  events: Record<NotificationEvent, boolean>;
  channels: Record<NotificationChannel, boolean>;  // display-only until Phase 4
}

export type PlanTier = "free" | "starter" | "pro" | "business";

export interface OwnerAccount {
  profile: OwnerProfile;
  preferences: OwnerPreferences;
  notifications: NotificationPreferences;
  planTier: PlanTier;
}
```

`NotificationEvent` covers the five actionable triggers — `rent_reminder`, `agreement_expiry`, `payment_received`, `ticket_update`, `invite_accepted`. The list is intentionally event-shaped, not channel-shaped: Phase 4 adds per-channel rules on top without renaming anything.

### 9.2 Tab structure

Same Reka-UI Tabs primitive as the property/tenant detail pages. Four tabs:

| Tab | Content | State source |
|---|---|---|
| **Profile** | Identity (name, email read-only, phone, business name), photo placeholder, masked bank account. | `OwnerAccount.profile` |
| **Preferences** | Language radio (English / Bahasa Melayu) + theme radio (Light / Dark / System) — applied to the live UI on save via `setLocale()` and `setTheme()`. | `OwnerAccount.preferences` (cookie-backed for theme + locale, Pinia for the rest) |
| **Notifications** | Five event toggles + a Phase-4 banner explaining channels (email + WhatsApp) ship later. In-app notifications are documented as always-on. | `OwnerAccount.notifications.events` |
| **Plan** | Four-tier ladder (Free / Starter / Pro / Business), current tier highlighted, upgrade CTAs toast a Phase-7 stub. | `Plan[]` from `useOwnerSettings().listPlans()` + `OwnerAccount.planTier` |

### 9.3 Composable + service

[useOwnerSettings.ts](../../frontend/app/services/useOwnerSettings.ts) keeps the same pattern as the entity services: `get` + per-tab patch (`updateProfile`, `updatePreferences`, `updateNotifications`) + `listPlans`. Each tab owns its own form, submits its slice, and re-emits the fresh `OwnerAccount` to the parent page so the other tabs stay in sync.

The Preferences form is special — it applies its values to the live app *immediately on save* (via `useTheme().setTheme` and `useI18n().setLocale`) so the change is visible without reload. Cookies handle persistence across reloads; the service-layer call is for the eventual backend swap.

### 9.4 Mock seed

[mocks/owner.ts](../../frontend/app/mocks/owner.ts) carries a single record whose `id` matches the auth-store stub user (`stub-owner`). Notifications start with all five events on; channels start with `email + in_app` enabled and `whatsapp` off (defaulted off until WhatsApp Cloud API is wired). Plan tier is `free` so the upgrade CTAs stay visible.

[plansMock](../../frontend/app/mocks/owner.ts) carries the four tiers from [PROJECT.md § 12](../global/PROJECT.md) (`Free` / `Starter` / `Pro` / `Business`) — pricing in RM, units cap, description key — used display-only by the Plan panel.

### 9.5 Schema impact for backend

- **`users` table** already covers `name` / `email` / `phone` (Tier 1 per § 5.4). Add `business_name`, `photo_path` (Phase 4), and a nullable `bank_account_last4` snapshot.
- **`owner_preferences` JSON** column on `users` (or sibling table) — `{ locale, theme, money_locale }`. Could also be a kv-store; small enough to stay JSON.
- **`notification_preferences` JSON** column on `users` — `{ events: {...}, channels: {...} }`. Phase 4 may promote channels into a per-event matrix; the JSON shape absorbs that without a migration.
- **Subscription / plan** is its own concern in Phase 7 — likely a `subscriptions` table joined to `users.id`. The frontend currently reads `account.planTier` from the same payload as profile.
- **Endpoints the frontend mocks today**: `GET /account`, `PATCH /account/profile`, `PATCH /account/preferences`, `PATCH /account/notifications`, `GET /plans`.

---

## 10. Migration plan (per entity)

When the backend endpoint for an entity ships:

1. Confirm the API response matches the TypeScript type in `app/types/<entity>.ts`. If the backend diverges, update the type *and* the mocks together — the mocks are the contract.
2. Flip `USE_MOCK = false` in that entity's service composable.
3. Delete `app/mocks/<entity>.ts` once the swap is verified.
4. Leave `app/types/<entity>.ts` in place — it stays as the response shape.

Entities migrate independently; we don't need a big-bang swap.

---

## 11. Decisions

| Question | Decision |
|---|---|
| Modal / select / dropdown primitives | **Reka UI** — install `reka-ui`, wrap into `~/components/ui/Modal.vue` and `Select.vue`. Coherent set covers later needs (Dropdown, Combobox, DatePicker). |
| Form validation | **vee-validate + Zod** — already in [package.json](../../frontend/package.json). Schemas in `app/schemas/<entity>.ts`, shared between Add modal and detail-page edit forms. |
| Field tiers | **Tier 1** in Add Property modal; **Tier 2** as nullable columns + **Tier 3** as `details` JSON, both edited on the property detail page. |
| Money representation | Integer **sen**, formatted via [useMoney.ts](../../frontend/app/composables/useMoney.ts). |
| Mock toggle | **`runtimeConfig.public.useMock`** — single source of truth, flipped per-environment via `NUXT_PUBLIC_USE_MOCK`. Per-entity migration just deletes the `if (useMock)` branch in that service. |
| Co-owners storage | **Separate `property_co_owners` table** on backend (not JSON). DB-enforced invariants: shares sum to 100, exactly one `is_primary`. |
| Documents tab | **Placeholder visible by default** via `runtimeConfig.public.features.documents` — signals to demo audiences that uploads ship in Phase 4. Same flag will gate real storage when it lands. |
| Property `state` field type | **`MalaysianState` enum from day one** — no `string` interim. |
| Charts | **Hand-rolled CSS/HTML in [MiniBarChart.vue](../../frontend/app/components/ui/MiniBarChart.vue)** — no chart-library dep. Monochrome palette + simple bar visuals don't justify the bundle / theming cost. Revisit if multi-series or axes show up. |
| CSV export | **Client-side Blob** via [utils/csv.ts](../../frontend/app/utils/csv.ts) (RFC 4180 escaping + UTF-8 BOM for Excel). No file storage required, works pre-Phase-4. |
| Cross-entity reads | Aggregations live in **read-only composables** on top of services (`useDashboard`, `useReports`) — no service writes, no new mocks. Mock→backend swap stays per-entity. |
| Kanban interactions | **Read-only cards + buttons-on-detail** for status transitions — no drag-and-drop library yet. Allowed transitions come from a static `TICKET_TRANSITIONS` map. Revisit when DnD is genuinely the better UX. |

---

## 12. Order of work

1. `app/types/property.ts` + `app/schemas/property.ts` (Zod) + `app/mocks/properties.ts` + `app/services/useProperties.ts`.
2. Install `reka-ui`. Build thin wrappers `~/components/ui/Modal.vue` and `Select.vue` (style per [UI-STANDARDS.md](UI-STANDARDS.md)).
3. `AddPropertyModal.vue` (Tier 1) wired to the `+ Add property` button on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
4. Properties list rendering on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue) — replace EmptyState when list non-empty.
5. Property detail route `pages/owner/properties/[id].vue` with **Basics** (Tier 2) and **Ownership & costs** (Tier 3) tabs.
6. Repeat the type → schema → mock → service → UI loop for Units, Tenants, Agreements, Invoices/Payments.
7. **Maintenance tickets** — types + status transition map + Zod schemas + `useTickets` service + Kanban + detail page with comment thread + create modal. See § 7.
8. **Cross-entity views** — `useDashboard` + `useReports` composables → dashboard tiles, 12-month chart, "Needs attention" feed → reports page (year picker, per-property breakdown, CSV export, PDF stub). See § 8.
9. **Settings** — `useOwnerSettings` composable + 4-tab page (Profile / Preferences / Notifications / Plan). Closes the owner shell. See § 9.
# Roofly Frontend Mock POC

> **Status:** Draft — Phase 2 (frontend-first, backend follows).
> **Goal:** Build the full owner UX against typed TypeScript mocks so the backend team has a clear API contract to implement, and the frontend has zero hard dependency on Laravel until the swap is ready.
> **Override priority:** [UI-STANDARDS.md](UI-STANDARDS.md) > [PROJECT.md](../global/PROJECT.md) > this doc. This file describes a *temporary scaffold*.

---

## 1. Why mock-first

- Backend (Laravel + Sanctum) is still being scaffolded — see [PROJECT.md § Database schema](../global/PROJECT.md#L328).
- We don't want UI work blocked on API readiness, and we don't want to scatter `if (mock) ... else ...` checks across every component.
- Mocks double as a **contract**: when the backend lands, response shapes must match the TypeScript types in `app/types/`.

**Non-goals:** persistent state across reloads, network simulation, MSW. Just plain in-memory arrays and async-shaped functions.

---

## 2. Folder layout

```
frontend/app/
├── types/                ← single source of truth for entity shapes
│   ├── property.ts
│   ├── unit.ts
│   ├── tenant.ts
│   └── ...
├── mocks/                ← seed data, only imported by services
│   ├── properties.ts
│   ├── units.ts
│   └── ...
├── services/             ← the swap point: today returns mocks, tomorrow calls useApi()
│   ├── useProperties.ts
│   ├── useUnits.ts
│   └── ...
└── composables/
    └── useApi.ts         ← unchanged; services will start using it post-swap
```

**Rule:** pages and components import from `services/` only. They must never `import { propertiesMock } from "~/mocks/..."`. That single boundary is what makes the swap painless.

---

## 3. The service pattern

Each service composable returns the same shape regardless of backing store. The mock flag is sourced from `runtimeConfig.public.useMock` so all services check one place — flip per-environment via env var, no code edits, no per-service drift.

```ts
// nuxt.config.ts
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      // Default true during Phase 2; set NUXT_PUBLIC_USE_MOCK=false once backend lands.
      useMock: true,
    },
  },
});
```

```ts
// app/services/useProperties.ts
import type { Property, PropertyInput } from "~/types/property";
import { propertiesMock } from "~/mocks/properties";

export const useProperties = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Property[]> => {
    if (useMock) return structuredClone(propertiesMock);
    const { request } = useApi();
    return request<Property[]>("/properties");
  };

  const create = async (input: PropertyInput): Promise<Property> => {
    if (useMock) {
      const created: Property = {
        id: crypto.randomUUID(),
        ...input,
        createdAt: new Date().toISOString(),
      };
      propertiesMock.push(created);
      return created;
    }
    const { request } = useApi();
    return request<Property>("/properties", { method: "POST", body: input });
  };

  return { list, create };
};
```

Notes:
- One source of truth for the mock toggle (`runtimeConfig.public.useMock`); per-entity migration just deletes the `if (useMock)` branch in that service.
- Services always return promises, even for mocks, so call sites are identical post-swap.
- `structuredClone` on read prevents accidental mutation of the seed array.

---

## 4. Properties

### 4.1 Type

Schema source: [PROJECT.md § properties table](../global/PROJECT.md#L328) extended with two JSON sub-objects (`ownership` + `utilities`) that map to the detail-page tabs, plus a top-level `coOwners` array that maps to a **separate `property_co_owners` table** on the backend (relational integrity matters for joint ownership — see §4.7). Money is integer **sen** (matches [useMoney.ts](../../frontend/app/composables/useMoney.ts)).

```ts
// app/types/property.ts (excerpt)
import type { MalaysianState } from "./geography";

export interface PropertyMortgage {
  bank?: string;
  loanAmount?: number;          // sen
  outstandingBalance?: number;  // sen
  monthlyInstalment?: number;   // sen
  tenureYears?: number;
  maturityDate?: string;
  interestRatePct?: number;
}

export interface PropertyCoOwner {
  id: string;                   // uuid; primary owner has isPrimary=true
  name: string;
  sharePct: number;             // 0-100; sum across all co-owners must === 100
  isPrimary: boolean;           // exactly one true; matches Property.ownerId
}

export interface PropertyOwnership {
  // Title — for the Title section
  titleType?: "freehold" | "leasehold";
  titleNumber?: string;
  lotNumber?: string;
  tenureExpiry?: string;
  strataTitle?: boolean;
  masterTitle?: boolean;
  // Acquisition — drives the gains snapshot
  purchaseDate?: string;
  purchasePrice?: number;       // sen
  stampDuty?: number;           // sen
  legalFees?: number;           // sen
  // Valuation
  currentMarketValue?: number;  // sen
  lastValuedAt?: string;
  valuationSource?: "bank" | "agent" | "self";
  // Mortgage (optional)
  mortgage?: PropertyMortgage;
}

export interface PropertyUtilities {
  monthlyMaintenanceFee?: number;
  sinkingFund?: number;
  quitRentAnnual?: number;
  assessmentRateAnnual?: number;
  buildingInsuranceAnnual?: number;
  tnbAccountNo?: string;
  waterAccountNo?: string;
  indahWaterAccountNo?: string;
  internetAccountNo?: string;
  managementCorpName?: string;
  managementCorpPhone?: string;
}

export interface Property {
  // Identity
  id: string;
  ownerId: string;              // matches the `isPrimary: true` co-owner
  name: string;
  internalLabel?: string;
  type: "condo" | "landed" | "shoplot" | "room";
  notes?: string;
  // Location
  address: string;
  city: string;
  state: MalaysianState;        // see §4.3
  postcode: string;
  // Specifications
  yearBuilt?: number;
  builtUpSqft?: number;
  landSqft?: number;
  bedrooms?: number;
  bathrooms?: number;
  parkingLots?: number;
  furnishing?: "unfurnished" | "partial" | "fully";
  // JSON sub-objects — one per detail-page tab
  ownership?: PropertyOwnership;
  utilities?: PropertyUtilities;
  // Joint ownership — separate table on backend (see §4.7)
  coOwners: PropertyCoOwner[];  // always at least one entry (the primary owner)
  // Server-assigned
  createdAt: string;
}

// Add Property modal still captures Tier 1 only — everything else is edited on the detail page.
export type PropertyInput = Pick<
  Property,
  "name" | "address" | "city" | "state" | "postcode" | "type"
>;
```

### 4.2 Add Property modal — field spec (Tier 1 only)

| Field | Type | Required | Validation | Notes |
|---|---|---|---|---|
| `name` | text | ✓ | 2–80 chars | Free text. Placeholder example: "Suria KLCC #12-3A" |
| `type` | select | ✓ | enum | Options: Condo / Landed / Shoplot / Room. i18n labels. |
| `address` | textarea | ✓ | 5–200 chars | One field — street + building, freeform. |
| `city` | text | ✓ | 2–60 chars | Autocomplete later; freeform for POC. |
| `state` | select | ✓ | enum (see 4.3) | 16 Malaysian states + FTs. |
| `postcode` | text | ✓ | exactly 5 digits | MY postal format `^\d{5}$`. |

UX:
- Modal component: `app/components/owner/AddPropertyModal.vue` (new).
- Triggered from the `+ Add property` button in [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
- On submit: call `useProperties().create(input)`, close modal on success, push the returned `Property` into the local list ref.
- Built on `~/components/ui/Modal.vue` + `Select.vue` (Reka UI wrappers — see §8) and existing `Input.vue` / `Button.vue`.
- Validation via **vee-validate + Zod** (already installed). Schema lives in `app/schemas/property.ts` and is shared with the detail-page edit form.
- All labels go through i18n (`en.json`, `ms.json`) under `owner.properties.addModal.*`.

### 4.3 Malaysian states (enum source)

Lives in `app/types/geography.ts` and is imported by `Property` (typed as `MalaysianState` from day one — no `string` interim).

```ts
export const MY_STATES = [
  "Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan",
  "Pahang", "Perak", "Perlis", "Pulau Pinang", "Sabah",
  "Sarawak", "Selangor", "Terengganu",
  "W.P. Kuala Lumpur", "W.P. Labuan", "W.P. Putrajaya",
] as const;
export type MalaysianState = (typeof MY_STATES)[number];
```

### 4.4 Mock seed

`app/mocks/properties.ts` carries **5 properties** with mixed fullness so the detail-page completion indicators read as expected:

1. **Fully populated condo** — Suria KLCC #12-3A.
2. **Mid landed (single unit)** — TTDI bungalow.
3. **Multi-unit landed** — Subang terrace house with master bedroom rental + 2 separate room rentals (3 units under one property — stress-tests the one-property-many-units model end-to-end).
4. **Sparse shoplot** — Wangsa Walk Shoplot G-12.
5. **Near-empty room** — USJ 9 single room.

The empty case is intentional. The multi-unit landed case is intentional too — it surfaces edge cases (per-unit rent allocation, unit-level tenant displays on the property Overview tab) before they can hide until Phase 3.

### 4.5 Detail page — tab structure

Route: `/owner/properties/[id]`. Header pattern follows [§ 4.4 Detail-page header in UI-STANDARDS](UI-STANDARDS.md). Below the title block, one Card hosts the tabs; below that Card, the `UnitsPanel` continues to render the property's units.

The Documents tab renders a **"coming in Phase 4" placeholder by default** so owners see during demos that file uploads are on the way. The faint dot on the tab trigger + the tooltip do the signaling — clicking lands on a Phase-4 empty state, not a broken feature. Toggle via `runtimeConfig.public.features.documents` (default `true`; set `NUXT_PUBLIC_FEATURE_DOCUMENTS=false` to hide entirely). The same flag will switch to gating real file storage in Phase 4 — flag mechanism stays, semantics shift.

| Tab | Mental mode | Backed by | Notes |
|---|---|---|---|
| **Overview** | "How is it doing?" | `useUnits().listByProperty` + `useAgreements().listWithRefs` | Read-only — counts of units (total / occupied), active agreements, monthly income from active rent, list of active tenants. |
| **Details** | "What is it?" | top-level `Property` columns | One form, three labeled sections: Identity, Location, Specifications. |
| **Ownership** | "What's the legal/financial picture?" | `Property.ownership` JSON + `Property.coOwners[]` | Title, Acquisition (auto-totaled), Valuation, Mortgage, Co-owners (vee-validate `useFieldArray` repeater — see invariants below), and a **capital-gains snapshot** computed via `app/utils/rpgt.ts`. |
| **Utilities** | "What does it cost to run?" | `Property.utilities` JSON | Recurring fees (auto annual + monthly equivalent), Service accounts. |
| **Documents** *(Phase 4+)* | "Where are the papers?" | — | Placeholder card visible by default — sets demo expectation that uploads are coming. Trigger carries a faint dot to read as "not wired yet". |

`useProperties().update` deep-merges `ownership` and `utilities` so each tab's form can PATCH only its own slice. `coOwners` replaces wholesale (it's a list, not a partial object).

#### Completion indicators

Each *visible* tab trigger shows a small completion indicator (✓ when 100%, amber dot when partial, faint dot when empty). Completion is **not** "every field that exists in the type" — that would give every condo a permanent 95% because `landSqft` doesn't apply. Define a deliberate required-field subset per `PropertyType`, lives in [app/utils/propertyCompletion.ts](../../frontend/app/utils/propertyCompletion.ts):

```ts
// Per-type required fields per tab. Optional/computed fields don't count toward %.
export const REQUIRED_FIELDS: Record<
  PropertyType,
  Record<"details" | "ownership" | "utilities", string[]>
> = {
  condo: {
    details: ["yearBuilt", "builtUpSqft", "bedrooms", "bathrooms", "furnishing"],
    ownership: ["titleType", "purchaseDate", "purchasePrice", "currentMarketValue"],
    utilities: ["monthlyMaintenanceFee", "quitRentAnnual", "assessmentRateAnnual"],
  },
  landed: {
    details: ["yearBuilt", "builtUpSqft", "landSqft", "bedrooms", "bathrooms"],
    ownership: ["titleType", "purchaseDate", "purchasePrice", "currentMarketValue"],
    utilities: ["quitRentAnnual", "assessmentRateAnnual"],
  },
  shoplot: { /* ... */ },
  room:    { /* ... */ },
};
```

Rules:
- Optional or computed fields (e.g. `acquisitionTotal`, `landSqft` on a condo) are not in the list — they don't drag completion down.
- The `coOwners` array is "complete" when shares sum to 100 (see invariant below), regardless of count.
- Mortgage block is "complete" if either fully filled *or* explicitly marked "no mortgage" (a UI checkbox stored as `ownership.mortgage = null`). Don't penalise cash-purchased properties.

#### Co-owner invariants

- `Property.coOwners` always contains **at least one** entry; on property creation, an entry for the creating user is inserted with `sharePct: 100, isPrimary: true`.
- Exactly one entry has `isPrimary: true`. The primary's identity (matched by `Property.ownerId`) is who manages the listing in app — display, notifications, billing-of-record.
- Sum of `sharePct` across all entries must equal `100`. Save is **blocked** when sum ≠ 100 (form-level error on the repeater); no auto-redistribute, no warning-only save — joint ownership is legally meaningful.
- Adding a co-owner does **not** auto-rebalance existing shares — the user must edit them. The repeater shows a live "Total: X% (must be 100%)" line.
- Removing the primary requires nominating a new primary first; the action is gated in the UI, not silently re-assigned.

### 4.6 Capital-gains snapshot ([utils/rpgt.ts](../../frontend/app/utils/rpgt.ts))

Resident-individual RPGT brackets:

| Holding period | Rate |
|---|---|
| Years 1–2 | 30% |
| Year 3 | 20% |
| Year 4 | 15% |
| Year 5 | 10% |
| Year 6+ | 5% |

The snapshot needs `purchasePrice`, `purchaseDate`, and `currentMarketValue` to render. Acquisition cost = `purchasePrice + stampDuty + legalFees`. Gain = `marketValue − acquisitionCost`. RPGT = `gain × bracketRate`. Net = `gain − RPGT`. UI carries an "estimate only — not tax advice. Assumes resident-individual filing; non-citizens, foreigners, and companies have different rates." disclaimer. Disposal-side allowables (agent commission, sale legal fees) and renovation expenses are out of scope for the snapshot — owners who want a precise figure should consult their tax agent.

### 4.7 Schema impact for backend

The PROJECT.md `properties` table currently covers Tier 1 only. Recommended extension:

- **Top-level columns on `properties`**: add `internal_label`, `notes`, `year_built`, `built_up_sqft`, `land_sqft`, `bedrooms`, `bathrooms`, `parking_lots`, `furnishing` as nullable columns. (Drop the previously-proposed `title_type`, `tenure_expiry`, `strata_title` from the column list — they move into the `ownership` JSON.)
- **`ownership JSON` column** on `properties` — title info, acquisition, valuation, mortgage. Flexible while landlord fields stabilise; reasonable for our query scale (one owner has tens, not millions, of properties). Revisit if a concrete report query becomes painful.
- **`utilities JSON` column** on `properties` — recurring fees + service-account reference numbers. Genuinely auxiliary; no plans to filter on `tnb_account_no`.
- **`property_co_owners` table** (new, *not* JSON) — relational integrity matters here:
  - `id`, `property_id` (FK → properties), `user_id` (FK → users, nullable for off-platform co-owners), `name`, `share_pct` (decimal 5,2), `is_primary` (bool), timestamps.
  - DB-level invariants: per `property_id`, `sum(share_pct) = 100` and `count(is_primary = true) = 1`. Enforce in a migration trigger or in the `Property` repository on save — both is fine.
  - `properties.owner_id` continues to point at the primary co-owner's `user_id`; the table just makes the joint-ownership picture explicit and queryable ("show all properties where user X is any kind of owner" becomes a simple join).
- **Mortgage stays nested in `ownership` JSON for now.** TODO: extract to a `property_mortgages` table when we add payment history, refinancing events, or multi-mortgage support.
- Photos and document uploads (Documents tab) are deferred to Phase 4+ when storage is wired; reuse the polymorphic `documents` table already in PROJECT.md.

---

## 5. Tenants

Tenants follow the same Tier 1 / 2 / 3 split as Properties. Difference: tenants are cross-property (no parent), so the detail page is a standalone route at `/owner/tenants/[id]` rather than a panel on another page.

### 5.1 Type

```ts
// app/types/tenant.ts
export type TenantStatus =
  | "invited"        // sent invite, hasn't accepted
  | "active"         // signed agreement, currently occupying
  | "notice_given"   // gave notice, still occupying — owners want the 30-day vacancy heads-up
  | "moved_out";     // gone, agreement closed

export interface TenantPersonal {
  icNumber?: string;          // MyKad — YYMMDD-PB-####
  dateOfBirth?: string;       // ISO date
  occupation?: string;
  employer?: string;
  monthlyIncome?: number;     // sen
  nationality?: string;
  photoUrl?: string;          // Phase 4+
}

export interface TenantEmergencyContact {
  name?: string;
  phone?: string;
  relationship?: string;
}

export interface Tenant {
  // Tier 1 — captured in invite modal
  id: string;
  name: string;
  email: string;
  phone: string;
  status: TenantStatus;
  invitedAt: string;
  createdAt: string;
  // Tier 2 — JSON column on backend
  personal?: TenantPersonal;
  // Tier 3 — JSON column on backend
  emergencyContact?: TenantEmergencyContact;
}
```

### 5.2 Invite modal (Tier 1 only)

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | text | ✓ | 2–80 chars |
| `email` | email | ✓ | Standard email validation |
| `phone` | tel | ✓ | MY-friendly regex `^[\d\s+\-()]{8,20}$` |

The invite flow records the contact and sets `status="invited"`. Magic-link emails arrive when authentication ships.

### 5.3 Detail page tabs

The detail route follows the [§ 4.4 Detail-page header pattern](UI-STANDARDS.md) — title block left, `Delete tenant` ghost button bottom-right of the title.

- **Identity** (Tier 1) — name, email, phone, **plus** status. Status is the one Tier 1 field not in the invite modal; new tenants always start `invited`. Allowed transitions: `invited → active → notice_given → moved_out` (and `invited → moved_out` for declined invites).
- **Personal** (Tier 2) — IC, DOB, occupation, employer, monthly income (sen ↔ ringgit at the form / service boundary), nationality.
- **Emergency contact** (Tier 3) — name, phone, relationship.
- **Documents** *(Phase 4+)* — hidden behind the same `runtimeConfig.public.features.documents` flag as the property Documents tab. IC copy, payslip, reference letter all land with Phase 4 file storage; we do not render a placeholder tab.

### 5.4 Schema impact for backend

PROJECT.md's `users` table covers Tier 1 (with `role="tenant"`). Tier 2/3 are extensions — recommend two nullable JSON columns on `users`:

- `personal_info JSON` — Tier 2.
- `emergency_contact JSON` — Tier 3.

Photo + document uploads are Phase 4+; they reuse the polymorphic `documents` table already in PROJECT.md.

### 5.5 Mock seed

`app/mocks/tenants.ts` carries 5 tenants spanning all four statuses (`invited`, `active`, `notice_given`, `moved_out`), with mixed Tier 2/3 fullness so the detail page renders both rich and sparse cases. The `notice_given` case anchors the upcoming-vacancy widgets on the dashboard.

---

## 6. Payments (invoices + payments)

The first cross-entity flow — invoices are derived from active/expired agreements (one per month based on `rentDueDay`), and payments settle invoices. Both live behind `/owner/payments`.

### 6.1 Types

```ts
// app/types/invoice.ts
export type InvoiceStatus = "pending" | "paid" | "overdue" | "cancelled";
export interface Invoice {
  id: string;
  agreementId: string;
  invoiceNumber: string;     // INV-0001, sequenced chronologically
  amount: number;            // sen
  lateFee: number;           // sen
  dueDate: string;           // ISO date
  status: InvoiceStatus;
  createdAt: string;
}

// app/types/payment.ts
export type PaymentMethod = "fpx" | "card" | "cash" | "transfer";
export type PaymentStatus = "pending" | "successful" | "failed";
export interface Payment {
  id: string;
  invoiceId: string;
  amount: number;            // sen
  method: PaymentMethod;
  status: PaymentStatus;
  paidAt: string;            // ISO datetime
  reference?: string;
  createdAt: string;
}
```

### 6.2 Mock generator

[mocks/invoices.ts](../../frontend/app/mocks/invoices.ts) walks each agreement month-by-month from `startDate` to `min(endDate, today + 30 days)`. Status is decided by simple heuristic:

- `paid` — invoice older than 30 days *or* under an `expired` / `terminated` agreement (a matching `payment` is also generated).
- `overdue` — due in the last 30 days, unpaid (carries `lateFee` from the agreement).
- `pending` — due in the future (we generate up to 30 days ahead so the table always has a "next" invoice).
- `draft` agreements produce **no** invoices.

After generation, all invoices are sorted by `dueDate` and renumbered `INV-0001..INV-NNNN` so the table reads chronologically.

### 6.3 Service

[services/useInvoices.ts](../../frontend/app/services/useInvoices.ts) exposes:

- `list()` / `listWithRefs()` — `WithRefs` joins each invoice with its agreement → unit → property → tenant + matched payments. Used by the table.
- `recordPayment(input)` — creates a `Payment`, flips invoice `status` to `paid`. Returns `{ payment, invoice }`.
- `sendInvoice(id)` — mock returns `{ sentAt }`. Backend will own actual email/WhatsApp dispatch.
- `updateStatus(id, status)` — for cancellation.

### 6.4 List page UX

[pages/owner/payments.vue](../../frontend/app/pages/owner/payments.vue) — the first **TanStack Table** screen, per [UI-STANDARDS § 3.5](UI-STANDARDS.md#L215). Filter row layout:

```
┌────────────────────────────────────────────────────────────────────┐
│ [All N] [Pending] [Overdue] [Paid] [Cancelled] [Clear]   [Month ▾] [Year ▾] │
└────────────────────────────────────────────────────────────────────┘
```

- **Status pills** — left side, with counts that reflect the *currently date-filtered* subset (so "Pending 2" inside `Year=2026` means 2 pending in 2026).
- **Month + Year dropdowns** — right side. Year options derived from the data; Month is fixed Jan–Dec via `common.months.*`. Both default to "All".
- **Sortable columns**: `dueDate` (default desc), `amount`. Invoice / Tenant / Status / Action are scan-only.
- **Pagination**: 20 rows / page via `getPaginationRowModel`. Page index resets to 0 whenever any filter changes (TanStack doesn't auto-reset).
- **Invoice number is a clickable link** — opens [InvoiceViewModal](../../frontend/app/components/owner/InvoiceViewModal.vue).
- **Action column**: `Record payment` button for `pending` / `overdue`; "Paid {date}" text for `paid`; "—" for `cancelled`.

### 6.5 Record payment modal

[RecordPaymentModal.vue](../../frontend/app/components/owner/RecordPaymentModal.vue) — pre-fills `amount` with `rent + lateFee` (ringgit at the form boundary, sen at the service boundary), `method=fpx`, `paidAt=today`. Optional free-text `reference`. Submit creates a `Payment` and flips the invoice.

### 6.6 Invoice view modal

[InvoiceViewModal.vue](../../frontend/app/components/owner/InvoiceViewModal.vue) at `size="lg"` — wide enough that the four footer actions sit on one line.

```
┌──────────────────────────────────────────────────────────┐
│ INV-0029                                          [×]   │
│   Billing period · June 2026                  [Pending] │
│   Due 04/06/2026                                        │
│                                                          │
│   BILL TO                                                │
│     Arif Hakim                                           │
│     arif.hakim@example.com · +60 17-888 1234            │
│                                                          │
│   PROPERTY                                               │
│     Wangsa Walk Shoplot G-12                             │
│     Ground floor shop                                    │
│                                                          │
│   ┌────────────────────────────────────────────────┐   │
│   │  Rent                          RM 4,000.00     │   │
│   │  Late fee (if any)                RM 50.00     │   │
│   │  ─────────────────────────────────────────────  │   │
│   │  Total due                     RM 4,050.00     │   │
│   └────────────────────────────────────────────────┘   │
│                                                          │
│   PAYMENTS RECEIVED  (when paid)                         │
│     · FPX  04/06/2026  · FPX-20260604     RM 4,000.00   │
│                                                          │
│ [Copy link]  [Download PDF]            [Send to tenant] │
└──────────────────────────────────────────────────────────┘
```

UX rules locked in here:

- **No redundant Close button** in the footer — top-right `×` and click-outside already close the modal. Reuse this rule for any future read-mostly modal.
- **Send to tenant** is the only primary action; Copy link + Download PDF are ghost-tone stubs that toast a Phase-4 disclaimer (PDF generation needs file storage).
- **Send is hidden** when `status === "cancelled"`.

### 6.7 Schema impact for backend

`invoices` and `payments` tables are already in [PROJECT.md § Database schema](../global/PROJECT.md#L328) — no extension needed. Two endpoints we mocked that the backend should expose:

- `POST /invoices/:id/payments` — record a payment + flip invoice status. Body matches `PaymentInput`.
- `POST /invoices/:id/send` — dispatch invoice via email / WhatsApp. Returns `{ sentAt }`.

Late-fee accrual is a backend concern (cron job per [PROJECT.md § Flow 3 step 6](../global/PROJECT.md#L184)). The mock just snapshots `agreement.lateFee` onto overdue invoices for display.

---

## 7. Migration plan (per entity)

When the backend endpoint for an entity ships:

1. Confirm the API response matches the TypeScript type in `app/types/<entity>.ts`. If the backend diverges, update the type *and* the mocks together — the mocks are the contract.
2. Flip `USE_MOCK = false` in that entity's service composable.
3. Delete `app/mocks/<entity>.ts` once the swap is verified.
4. Leave `app/types/<entity>.ts` in place — it stays as the response shape.

Entities migrate independently; we don't need a big-bang swap.

---

## 8. Decisions

| Question | Decision |
|---|---|
| Modal / select / dropdown primitives | **Reka UI** — install `reka-ui`, wrap into `~/components/ui/Modal.vue` and `Select.vue`. Coherent set covers later needs (Dropdown, Combobox, DatePicker). |
| Form validation | **vee-validate + Zod** — already in [package.json](../../frontend/package.json). Schemas in `app/schemas/<entity>.ts`, shared between Add modal and detail-page edit forms. |
| Field tiers | **Tier 1** in Add Property modal; **Tier 2** as nullable columns + **Tier 3** as `details` JSON, both edited on the property detail page. |
| Money representation | Integer **sen**, formatted via [useMoney.ts](../../frontend/app/composables/useMoney.ts). |
| Mock toggle | **`runtimeConfig.public.useMock`** — single source of truth, flipped per-environment via `NUXT_PUBLIC_USE_MOCK`. Per-entity migration just deletes the `if (useMock)` branch in that service. |
| Co-owners storage | **Separate `property_co_owners` table** on backend (not JSON). DB-enforced invariants: shares sum to 100, exactly one `is_primary`. |
| Documents tab | **Placeholder visible by default** via `runtimeConfig.public.features.documents` — signals to demo audiences that uploads ship in Phase 4. Same flag will gate real storage when it lands. |
| Property `state` field type | **`MalaysianState` enum from day one** — no `string` interim. |

---

## 9. Order of work

1. `app/types/property.ts` + `app/schemas/property.ts` (Zod) + `app/mocks/properties.ts` + `app/services/useProperties.ts`.
2. Install `reka-ui`. Build thin wrappers `~/components/ui/Modal.vue` and `Select.vue` (style per [UI-STANDARDS.md](UI-STANDARDS.md)).
3. `AddPropertyModal.vue` (Tier 1) wired to the `+ Add property` button on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
4. Properties list rendering on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue) — replace EmptyState when list non-empty.
5. Property detail route `pages/owner/properties/[id].vue` with **Basics** (Tier 2) and **Ownership & costs** (Tier 3) tabs.
6. Repeat the type → schema → mock → service → UI loop for Units, Tenants, Agreements, etc.
