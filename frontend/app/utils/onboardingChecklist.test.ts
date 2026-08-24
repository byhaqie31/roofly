import { describe, expect, it } from "vitest";
import { buildChecklist, type ChecklistInput } from "~/utils/onboardingChecklist";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";

const property = (over: Partial<Property> = {}): Property => ({
  id: "p1", ownerId: "o1", name: "Home", type: "room", purpose: "rental",
  address: "1 St", city: "KL", state: "W.P. Kuala Lumpur", postcode: "50000",
  coOwners: [{ id: "c1", name: "Me", sharePct: 100, isPrimary: true }],
  createdAt: "2026-01-01T00:00:00Z",
  ...over,
});
// `room` needs only titleType for ownership and nothing for utilities — keeps fixtures small.
const completeRoom = (over: Partial<Property> = {}) =>
  property({ bedrooms: 1, bathrooms: 1, ownership: { titleType: "freehold" }, ...over });

const unit = (propertyId: string): Unit => ({ id: `u-${propertyId}`, propertyId, label: "A", status: "vacant", createdAt: "" });
const tenant: Tenant = { id: "t1", name: "T", email: "t@x.my", phone: "", status: "invited", invitedAt: "", createdAt: "" };
const agreement = (status: Agreement["status"]): Agreement => ({
  id: "a1", unitId: "u-p1", tenantId: "t1", startDate: "2026-01-01", endDate: "2026-12-31",
  rentAmount: 1, depositAmount: 1, lateFee: 0, rentDueDay: 1, status, createdAt: "",
});

const base: ChecklistInput = { purposes: ["rental"], properties: [], units: [], tenants: [], agreements: [] };
const keys = (s: ReturnType<typeof buildChecklist>) => s.map((x) => x.key);

describe("buildChecklist", () => {
  it("rental owner gets all six steps, only add_property enabled when empty", () => {
    const steps = buildChecklist(base);
    expect(keys(steps)).toEqual(["add_property", "fill_ownership", "fill_utilities", "add_unit", "invite_tenant", "create_agreement"]);
    expect(steps[0]).toMatchObject({ done: false, enabled: true, to: "/owner/properties?add=1" });
    expect(steps.slice(1).every((s) => !s.enabled && !s.done)).toBe(true);
  });

  it("own-stay-only owner gets the three property steps", () => {
    expect(keys(buildChecklist({ ...base, purposes: ["own_stay"] }))).toEqual(["add_property", "fill_ownership", "fill_utilities"]);
  });

  it("mixed purposes is the union in canonical order", () => {
    expect(keys(buildChecklist({ ...base, purposes: ["investment", "rental"] }))).toEqual([
      "add_property", "fill_ownership", "fill_utilities", "add_unit", "invite_tenant", "create_agreement",
    ]);
  });

  it("ownership step links to the first incomplete property and is done when all complete", () => {
    const steps = buildChecklist({ ...base, properties: [completeRoom({ id: "p1" }), property({ id: "p2", bedrooms: 1, bathrooms: 1 })] });
    const own = steps.find((s) => s.key === "fill_ownership")!;
    expect(own).toMatchObject({ done: false, enabled: true, propertyId: "p2", to: "/owner/properties/p2?tab=ownership" });

    const all = buildChecklist({ ...base, properties: [completeRoom({ id: "p1" })] });
    expect(all.find((s) => s.key === "fill_ownership")!.done).toBe(true);
    expect(all.find((s) => s.key === "fill_utilities")!.done).toBe(true);
  });

  it("add_unit only looks at rental properties", () => {
    const ownStay = completeRoom({ id: "h", purpose: "own_stay" });
    const rental = completeRoom({ id: "p1" });
    const noUnits = buildChecklist({ ...base, purposes: ["rental", "own_stay"], properties: [ownStay, rental] });
    expect(noUnits.find((s) => s.key === "add_unit")).toMatchObject({ done: false, enabled: true, propertyId: "p1", to: "/owner/properties/p1?tab=overview" });

    const withUnit = buildChecklist({ ...base, properties: [rental], units: [unit("p1")] });
    expect(withUnit.find((s) => s.key === "add_unit")).toMatchObject({ done: true, enabled: true });
  });

  it("add_unit is done when ANY rental property has a unit, not all (getting-started nudge, not a completeness audit)", () => {
    const p1 = completeRoom({ id: "p1" });
    const p2 = completeRoom({ id: "p2" });
    const steps = buildChecklist({ ...base, properties: [p1, p2], units: [unit("p1")] });
    expect(steps.find((s) => s.key === "add_unit")).toMatchObject({ done: true, enabled: true });
  });

  it("tenant + agreement steps", () => {
    const steps = buildChecklist({ ...base, properties: [completeRoom()], units: [unit("p1")], tenants: [tenant], agreements: [agreement("draft")] });
    expect(steps.find((s) => s.key === "invite_tenant")).toMatchObject({ done: true, enabled: true, to: "/owner/tenants?invite=1" });
    expect(steps.find((s) => s.key === "create_agreement")).toMatchObject({ done: false, enabled: true, to: "/owner/agreements/new" });
    const active = buildChecklist({ ...base, properties: [completeRoom()], units: [unit("p1")], tenants: [tenant], agreements: [agreement("active")] });
    expect(active.find((s) => s.key === "create_agreement")!.done).toBe(true);
  });
});
