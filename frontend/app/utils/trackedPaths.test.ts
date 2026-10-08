import { describe, expect, it } from "vitest";
import { isTrackedPath } from "./trackedPaths";

describe("isTrackedPath", () => {
  it("tracks the public marketing, auth and legal pages", () => {
    for (const path of ["/", "/coming-soon", "/demo", "/auth/login", "/auth/register", "/legal/privacy", "/legal/terms"]) {
      expect(isTrackedPath(path), path).toBe(true);
    }
  });

  it("never tracks inside the owner, tenant or admin shells", () => {
    for (const path of ["/owner", "/owner/help", "/tenant", "/tenant/help", "/admin", "/admin/analytics"]) {
      expect(isTrackedPath(path), path).toBe(false);
    }
  });

  it("matches whole path segments only", () => {
    expect(isTrackedPath("/legalese")).toBe(false);
    expect(isTrackedPath("/authority")).toBe(false);
  });
});
