import { describe, expect, it } from "vitest";
import { pageLabelFor } from "./pageLabel";

describe("pageLabelFor", () => {
  it.each([
    ["/tenant", "Tenant app · Home"],
    ["/tenant/", "Tenant app · Home"],
    ["/tenant/tickets/abc-123", "Tenant app · Issue details"],
    ["/tenant/payments?invoice=9", "Tenant app · Payments"],
    ["/owner", "Owner app · Dashboard"],
    ["/owner/payments", "Owner app · Payments"],
    ["/owner/properties/9f1c", "Owner app · Property details"],
    ["/owner/agreements/new", "Owner app · New agreement"],
    ["/owner/agreements/a1#terms", "Owner app · Agreement details"],
    ["/owner/maintenance/t-7", "Owner app · Maintenance ticket"],
    ["/owner/help", "Owner app · Help and support"],
    ["/tenant/help", "Tenant app · Help and support"],
    ["/owner/legal/privacy", "Owner app · Legal"],
    ["/tenant/legal/terms", "Tenant app · Legal"],
  ])("%s → %s", (path, label) => {
    expect(pageLabelFor(path)).toBe(label);
  });

  it("keeps the path for an unmapped page inside an app", () => {
    expect(pageLabelFor("/owner/something-new")).toBe("Owner app · /owner/something-new");
  });

  it("returns the bare path outside the owner/tenant apps", () => {
    expect(pageLabelFor("/coming-soon")).toBe("/coming-soon");
  });
});
