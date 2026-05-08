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

Each service composable returns the same shape regardless of backing store:

```ts
// app/services/useProperties.ts
import type { Property, PropertyInput } from "~/types/property";
import { propertiesMock } from "~/mocks/properties";

const USE_MOCK = true; // flip to false when backend is ready

export const useProperties = () => {
  const list = async (): Promise<Property[]> => {
    if (USE_MOCK) return structuredClone(propertiesMock);
    const { request } = useApi();
    return request<Property[]>("/properties");
  };

  const create = async (input: PropertyInput): Promise<Property> => {
    if (USE_MOCK) {
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
- `USE_MOCK` is a module-level constant for now. We can promote it to `runtimeConfig.public.useMock` later if we want per-env control without code edits.
- Services always return promises, even for mocks, so call sites are identical post-swap.
- `structuredClone` on read prevents accidental mutation of the seed array.

---

## 4. Properties

### 4.1 Type

Schema source: [PROJECT.md § properties table](../global/PROJECT.md#L328) extended with two JSON sub-objects (`ownership` + `utilities`) that map to the detail-page tabs. Money is integer **sen** (matches [useMoney.ts](../../frontend/app/composables/useMoney.ts)).

```ts
// app/types/property.ts (excerpt)
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
  name: string;
  sharePct: number;             // 0-100
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
  // Joint ownership
  coOwners?: PropertyCoOwner[];
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
  ownerId: string;
  name: string;
  internalLabel?: string;
  type: "condo" | "landed" | "shoplot" | "room";
  notes?: string;
  // Location
  address: string;
  city: string;
  state: string;                // see §4.3
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

```ts
export const MY_STATES = [
  "Johor", "Kedah", "Kelantan", "Melaka", "Negeri Sembilan",
  "Pahang", "Perak", "Perlis", "Pulau Pinang", "Sabah",
  "Sarawak", "Selangor", "Terengganu",
  "W.P. Kuala Lumpur", "W.P. Labuan", "W.P. Putrajaya",
] as const;
export type MalaysianState = (typeof MY_STATES)[number];
```

(Promote `state` field on `Property` to `MalaysianState` once we're confident — keep as `string` until then to avoid churn while seeding.)

### 4.4 Mock seed

`app/mocks/properties.ts` carries 4 properties spanning all four `type`s with mixed fullness — one fully populated condo (Suria KLCC), one mid landed (TTDI), one sparse shoplot, one near-empty room (USJ 9). The empty case is intentional so the completion indicators on each tab read as expected.

### 4.5 Detail page — five-tab structure

Route: `/owner/properties/[id]`. Header pattern follows [§ 4.4 Detail-page header in UI-STANDARDS](UI-STANDARDS.md). Below the title block, one Card hosts a five-tab interface; below that Card, the `UnitsPanel` continues to render the property's units.

Each tab trigger shows a small **completion indicator** (✓ when 100%, amber dot when partial, faint dot when empty). The percentage is computed in [pages/owner/properties/[id].vue](../../frontend/app/pages/owner/properties/%5Bid%5D.vue) by counting non-empty fields per tab's owning sub-object.

| Tab | Mental mode | Backed by | Notes |
|---|---|---|---|
| **Overview** | "How is it doing?" | `useUnits().listByProperty` + `useAgreements().listWithRefs` | Read-only — counts of units (total / occupied), active agreements, monthly income from active rent, list of active tenants. |
| **Details** | "What is it?" | top-level `Property` columns | One form, three labeled sections: Identity, Location, Specifications. |
| **Ownership** | "What's the legal/financial picture?" | `Property.ownership` JSON | Title, Acquisition (auto-totaled), Valuation, Mortgage, Co-owners (vee-validate `useFieldArray` repeater with sum-to-100% indicator), and a **capital-gains snapshot** computed via `app/utils/rpgt.ts`. |
| **Utilities** | "What does it cost to run?" | `Property.utilities` JSON | Recurring fees (auto annual + monthly equivalent), Service accounts. |
| **Documents** | "Where are the papers?" | — | Phase 4+ placeholder (file storage not wired). |

`useProperties().update` deep-merges `ownership` and `utilities` so each tab's form can PATCH only its own slice.

### 4.6 Capital-gains snapshot ([utils/rpgt.ts](../../frontend/app/utils/rpgt.ts))

Resident-individual RPGT brackets:

| Holding period | Rate |
|---|---|
| Years 1–2 | 30% |
| Year 3 | 20% |
| Year 4 | 15% |
| Year 5 | 10% |
| Year 6+ | 5% |

The snapshot needs `purchasePrice`, `purchaseDate`, and `currentMarketValue` to render. Acquisition cost = `purchasePrice + stampDuty + legalFees`. Gain = `marketValue − acquisitionCost`. RPGT = `gain × bracketRate`. Net = `gain − RPGT`. UI carries an "estimate only — not tax advice" disclaimer.

### 4.7 Schema impact for backend

The PROJECT.md `properties` table currently covers Tier 1 only. Recommended extension:

- **Top-level columns**: add `internal_label`, `notes`, `year_built`, `built_up_sqft`, `land_sqft`, `bedrooms`, `bathrooms`, `parking_lots`, `furnishing` as nullable columns. (Drop the previously-proposed `title_type`, `tenure_expiry`, `strata_title` from the column list — they move into the JSON.)
- **`ownership JSON` column** on `properties` — title info, acquisition, valuation, mortgage, co-owners. Flexible while landlord fields stabilise.
- **`utilities JSON` column** on `properties` — recurring fees + service-account reference numbers.
- Photos and document uploads (Documents tab) are deferred to Phase 4+ when storage is wired; reuse the polymorphic `documents` table already in PROJECT.md.

---

## 5. Tenants

Tenants follow the same Tier 1 / 2 / 3 split as Properties. Difference: tenants are cross-property (no parent), so the detail page is a standalone route at `/owner/tenants/[id]` rather than a panel on another page.

### 5.1 Type

```ts
// app/types/tenant.ts
export type TenantStatus = "invited" | "active" | "moved_out";

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

- **Identity** (Tier 1) — name, email, phone, **plus** status. Status is the one Tier 1 field not in the invite modal; new tenants always start `invited`.
- **Personal** (Tier 2) — IC, DOB, occupation, employer, monthly income (sen ↔ ringgit at the form / service boundary), nationality.
- **Emergency contact** (Tier 3) — name, phone, relationship.
- **Documents** — placeholder card. Real uploads (IC copy, payslip, reference letter) land with Phase 4 file storage.

### 5.4 Schema impact for backend

PROJECT.md's `users` table covers Tier 1 (with `role="tenant"`). Tier 2/3 are extensions — recommend two nullable JSON columns on `users`:

- `personal_info JSON` — Tier 2.
- `emergency_contact JSON` — Tier 3.

Photo + document uploads are Phase 4+; they reuse the polymorphic `documents` table already in PROJECT.md.

### 5.5 Mock seed

`app/mocks/tenants.ts` carries 4 tenants spanning all three statuses, with mixed Tier 2/3 fullness so the detail page renders both rich and sparse cases.

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

---

## 9. Order of work

1. `app/types/property.ts` + `app/schemas/property.ts` (Zod) + `app/mocks/properties.ts` + `app/services/useProperties.ts`.
2. Install `reka-ui`. Build thin wrappers `~/components/ui/Modal.vue` and `Select.vue` (style per [UI-STANDARDS.md](UI-STANDARDS.md)).
3. `AddPropertyModal.vue` (Tier 1) wired to the `+ Add property` button on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
4. Properties list rendering on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue) — replace EmptyState when list non-empty.
5. Property detail route `pages/owner/properties/[id].vue` with **Basics** (Tier 2) and **Ownership & costs** (Tier 3) tabs.
6. Repeat the type → schema → mock → service → UI loop for Units, Tenants, Agreements, etc.
