import { describe, expect, it } from "vitest";
import { TENANT_CORE_FIELDS, tenantProfileGaps } from "./tenantProfileCompletion";

const full = {
  id: "t1",
  name: "Adi",
  email: "adi@example.com",
  phone: "+60 12-345 6789",
  personal: { icNumber: "880314-14-5687" },
  emergencyContact: { name: "Siti", phone: "+60 13-222 3333" },
};

describe("tenantProfileGaps", () => {
  it("reports nothing missing for a complete core profile", () => {
    expect(tenantProfileGaps(full)).toEqual({ missing: [], done: 4, total: 4 });
  });

  it("lists each missing or blank core field in display order", () => {
    const gaps = tenantProfileGaps({
      ...full,
      phone: "  ",
      personal: {},
      emergencyContact: { name: "Siti", phone: "" },
    });
    expect(gaps.missing).toEqual(["phone", "icNumber", "ecPhone"]);
    expect(gaps.done).toBe(1);
    expect(gaps.total).toBe(TENANT_CORE_FIELDS.length);
  });

  it("treats a null profile as entirely missing", () => {
    expect(tenantProfileGaps(null).missing).toEqual([...TENANT_CORE_FIELDS]);
  });
});
