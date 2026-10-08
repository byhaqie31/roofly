import { describe, expect, it } from "vitest";
import { latestRejectedClaim, maskAccountNumber, pendingClaim, todayIso } from "~/utils/paymentClaim";
import type { Payment } from "~/types/payment";

const pay = (over: Partial<Payment>): Payment => ({
  id: "p", invoiceId: "i", amount: 100, method: "transfer", status: "pending",
  paidAt: "2026-10-01T00:00:00Z", createdAt: "2026-10-01T00:00:00Z", ...over,
});

describe("pendingClaim", () => {
  it("finds the open claim", () => {
    expect(pendingClaim([pay({ id: "a", status: "failed" }), pay({ id: "b" })])?.id).toBe("b");
  });
  it("is null when nothing is pending", () => {
    expect(pendingClaim([pay({ status: "successful" })])).toBeNull();
    expect(pendingClaim([])).toBeNull();
  });
});

describe("latestRejectedClaim", () => {
  const rejected = pay({ id: "r", status: "failed", rejectionReason: "Not received", createdAt: "2026-10-02T00:00:00Z" });

  it("returns the rejection when it is the latest payment", () => {
    expect(latestRejectedClaim([pay({ id: "old", status: "failed", createdAt: "2026-09-01T00:00:00Z" }), rejected])?.id).toBe("r");
  });
  it("is null once the tenant claimed again or it was paid", () => {
    expect(latestRejectedClaim([rejected, pay({ id: "n", createdAt: "2026-10-03T00:00:00Z" })])).toBeNull();
    expect(latestRejectedClaim([rejected, pay({ id: "s", status: "successful", createdAt: "2026-10-03T00:00:00Z" })])).toBeNull();
  });
  it("ignores failures without a reason (gateway failures, not owner rejections)", () => {
    expect(latestRejectedClaim([pay({ status: "failed" })])).toBeNull();
  });
});

describe("maskAccountNumber", () => {
  it("keeps the last four digits", () => {
    expect(maskAccountNumber("514012344521")).toBe("•••• 4521");
  });
  it("is empty for a missing number", () => {
    expect(maskAccountNumber(null)).toBe("");
  });
});

describe("todayIso", () => {
  it("formats the local date", () => {
    expect(todayIso(new Date(2026, 0, 5))).toBe("2026-01-05");
  });
});
