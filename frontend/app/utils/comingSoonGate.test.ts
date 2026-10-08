import { describe, expect, it } from "vitest";
import { shouldRedirectToComingSoon } from "./comingSoonGate";

describe("shouldRedirectToComingSoon", () => {
  it("keeps the legal pages reachable while coming-soon-only is on", () => {
    for (const path of ["/legal/privacy", "/legal/terms", "/legal/billing", "/legal/acceptable-use", "/legal/beta"]) {
      expect(shouldRedirectToComingSoon(path, true), path).toBe(false);
    }
  });

  it("still redirects every other public and app route", () => {
    for (const path of ["/", "/auth/login", "/auth/register", "/demo", "/owner", "/tenant/payments", "/suspended", "/legalese"]) {
      expect(shouldRedirectToComingSoon(path, true), path).toBe(true);
    }
  });

  it("never redirects the coming-soon page or the admin back office", () => {
    for (const path of ["/coming-soon", "/admin", "/admin/enquiries"]) {
      expect(shouldRedirectToComingSoon(path, true), path).toBe(false);
    }
  });

  it("does nothing when coming-soon-only is off", () => {
    for (const path of ["/", "/auth/login", "/legal/privacy", "/owner"]) {
      expect(shouldRedirectToComingSoon(path, false), path).toBe(false);
    }
  });
});
