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

Schema source: [PROJECT.md § properties table](../global/PROJECT.md#L328) for Tier 1; Tier 2/3 are extensions detailed in §4.5–4.7. Money is integer **sen** (matches [useMoney.ts](../../frontend/app/composables/useMoney.ts)).

```ts
// app/types/property.ts
export type PropertyType = "condo" | "landed" | "shoplot" | "room";
export type Furnishing = "unfurnished" | "partial" | "fully";
export type TitleType = "freehold" | "leasehold";

// Tier 3 — flexible "house ledger" detail. Backend stores as JSON.
export interface PropertyDetails {
  purchaseDate?: string;            // ISO date
  purchasePrice?: number;           // sen
  monthlyMaintenanceFee?: number;   // sen
  quitRentAnnual?: number;          // sen
  assessmentRateAnnual?: number;    // sen
  insurancePolicyNo?: string;
  insuranceProvider?: string;
  tnbAccountNo?: string;
  waterAccountNo?: string;
  indahWaterAccountNo?: string;
  notes?: string;
  photos?: string[];                // URLs — Phase 4+
}

export interface Property {
  // Tier 1 — required, captured in Add Property modal
  id: string;
  ownerId: string;
  name: string;
  address: string;
  city: string;
  state: string;                    // see §4.3
  postcode: string;
  type: PropertyType;
  // Tier 2 — optional columns, edited on detail page
  yearBuilt?: number;
  builtUpSqft?: number;
  landSqft?: number;
  bedrooms?: number;
  bathrooms?: number;
  parkingLots?: number;
  furnishing?: Furnishing;
  titleType?: TitleType;
  tenureExpiry?: string;            // ISO date — only when leasehold
  strataTitle?: boolean;
  // Tier 3 — JSON blob
  details?: PropertyDetails;
  // Server-assigned
  createdAt: string;
}

// Add Property modal submits Tier 1 only — Tier 2/3 are edited on the detail page.
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
- Built on `~/components/ui/Modal.vue` + `Select.vue` (Reka UI wrappers — see §7) and existing `Input.vue` / `Button.vue`.
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

```ts
// app/mocks/properties.ts
import type { Property } from "~/types/property";

export const propertiesMock: Property[] = [
  {
    id: "11111111-1111-1111-1111-111111111111",
    ownerId: "owner-1",
    // Tier 1
    name: "Suria KLCC #12-3A",
    address: "Jalan Ampang, Lot 241",
    city: "Kuala Lumpur",
    state: "W.P. Kuala Lumpur",
    postcode: "50088",
    type: "condo",
    // Tier 2
    yearBuilt: 2008,
    builtUpSqft: 1100,
    bedrooms: 3,
    bathrooms: 2,
    parkingLots: 1,
    furnishing: "fully",
    titleType: "freehold",
    strataTitle: true,
    // Tier 3
    details: {
      monthlyMaintenanceFee: 45_000,        // RM 450.00
      assessmentRateAnnual: 120_000,        // RM 1,200.00
      tnbAccountNo: "220012345678",
      notes: "Master bedroom AC serviced 2025-11.",
    },
    createdAt: "2026-01-12T09:00:00Z",
  },
  // Add 2–3 more representative entries: one landed, one shoplot, one room.
  // Mix Tier 2/3 fullness so the detail page has both rich and sparse cases.
];
```

---

### 4.5 Property detail page — "Basics" tab (Tier 2)

| Field | Type | Validation | Notes |
|---|---|---|---|
| `yearBuilt` | number (year) | 1900 ≤ y ≤ current year | Useful for maintenance + insurance |
| `builtUpSqft` | number | > 0 | Common landlord reference |
| `landSqft` | number | > 0 | Show only when `type` is `landed` or `shoplot` |
| `bedrooms` | number | 0–20 | Defaults onto first unit, useful at property level for landed |
| `bathrooms` | number | 0–20 | Same |
| `parkingLots` | number | 0–20 | Negotiation point with tenants |
| `furnishing` | select | enum | unfurnished / partial / fully |
| `titleType` | select | enum | freehold / leasehold |
| `tenureExpiry` | date | required iff `titleType=leasehold` | MY tenure detail |
| `strataTitle` | toggle | boolean | Auto-true for `condo`, editable for `landed` |

UX: edited inline on the property detail page, saved via `useProperties().update(id, patch)`. Same Zod schema (partial) as the modal — lives in `app/schemas/property.ts`.

### 4.6 Property detail page — "Ownership & costs" tab (Tier 3)

| Field | Type | Notes |
|---|---|---|
| `purchaseDate` | date | ROI / tax base |
| `purchasePrice` | money (sen) | ROI / tax base — input formatted via `useMoney` |
| `monthlyMaintenanceFee` | money (sen) | Condo / strata only |
| `quitRentAnnual` | money (sen) | Cukai tanah |
| `assessmentRateAnnual` | money (sen) | Cukai pintu |
| `insurancePolicyNo` | text | Reference when claiming |
| `insuranceProvider` | text | Same |
| `tnbAccountNo` | text | Utility transfer at handover |
| `waterAccountNo` | text | Same |
| `indahWaterAccountNo` | text | Sewerage |
| `notes` | textarea | Catch-all |
| `photos` | image[] | **Phase 4+** — needs storage; out of scope for POC |

All Tier 3 fields are optional and free-form. Treat this tab as the owner's personal ledger — no validation beyond type checks and money parsing.

### 4.7 Schema impact for backend

The PROJECT.md `properties` table covers Tier 1 only. The backend will need to extend it:

- **Tier 2 → add nullable columns** to `properties`. Stable, queryable, indexable. Worth a migration.
- **Tier 3 → add a single `details JSON` column** to `properties`. Flexible while we discover what landlords actually fill in. Skip indexing.

Photos are deferred to Phase 4+ when storage (S3 / R2 / similar) is wired.

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

## 6. Migration plan (per entity)

When the backend endpoint for an entity ships:

1. Confirm the API response matches the TypeScript type in `app/types/<entity>.ts`. If the backend diverges, update the type *and* the mocks together — the mocks are the contract.
2. Flip `USE_MOCK = false` in that entity's service composable.
3. Delete `app/mocks/<entity>.ts` once the swap is verified.
4. Leave `app/types/<entity>.ts` in place — it stays as the response shape.

Entities migrate independently; we don't need a big-bang swap.

---

## 7. Decisions

| Question | Decision |
|---|---|
| Modal / select / dropdown primitives | **Reka UI** — install `reka-ui`, wrap into `~/components/ui/Modal.vue` and `Select.vue`. Coherent set covers later needs (Dropdown, Combobox, DatePicker). |
| Form validation | **vee-validate + Zod** — already in [package.json](../../frontend/package.json). Schemas in `app/schemas/<entity>.ts`, shared between Add modal and detail-page edit forms. |
| Field tiers | **Tier 1** in Add Property modal; **Tier 2** as nullable columns + **Tier 3** as `details` JSON, both edited on the property detail page. |
| Money representation | Integer **sen**, formatted via [useMoney.ts](../../frontend/app/composables/useMoney.ts). |

---

## 8. Order of work

1. `app/types/property.ts` + `app/schemas/property.ts` (Zod) + `app/mocks/properties.ts` + `app/services/useProperties.ts`.
2. Install `reka-ui`. Build thin wrappers `~/components/ui/Modal.vue` and `Select.vue` (style per [UI-STANDARDS.md](UI-STANDARDS.md)).
3. `AddPropertyModal.vue` (Tier 1) wired to the `+ Add property` button on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue#L17).
4. Properties list rendering on [pages/owner/properties.vue](../../frontend/app/pages/owner/properties.vue) — replace EmptyState when list non-empty.
5. Property detail route `pages/owner/properties/[id].vue` with **Basics** (Tier 2) and **Ownership & costs** (Tier 3) tabs.
6. Repeat the type → schema → mock → service → UI loop for Units, Tenants, Agreements, etc.
