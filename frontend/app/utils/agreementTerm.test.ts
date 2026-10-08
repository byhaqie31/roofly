import { describe, expect, it } from "vitest";
import { AGREEMENT_TERM_PRESETS, endDateForTerm, termMonthsBetween } from "./agreementTerm";

describe("endDateForTerm", () => {
  it("ends the day before the same date N months later (12 months from 1 Jan ends 31 Dec)", () => {
    expect(endDateForTerm("2026-01-01", 12)).toBe("2026-12-31");
    expect(endDateForTerm("2026-01-01", 6)).toBe("2026-06-30");
    expect(endDateForTerm("2026-01-01", 24)).toBe("2027-12-31");
  });

  it("handles mid-month starts and month-end overflow", () => {
    expect(endDateForTerm("2026-03-15", 12)).toBe("2027-03-14");
    expect(endDateForTerm("2026-01-31", 1)).toBe("2026-02-27"); // 31 Jan + 1 month clamps to 28 Feb, minus a day
    expect(endDateForTerm("2026-08-31", 6)).toBe("2027-02-27");
  });

  it("returns an empty string for an unusable start", () => {
    expect(endDateForTerm("", 12)).toBe("");
    expect(endDateForTerm("not-a-date", 12)).toBe("");
  });
});

describe("termMonthsBetween", () => {
  it("recognises an exact preset", () => {
    expect(termMonthsBetween("2026-01-01", "2026-12-31")).toBe(12);
    expect(termMonthsBetween("2026-03-15", "2026-09-14")).toBe(6);
    expect(termMonthsBetween("2026-01-01", "2027-12-31")).toBe(24);
  });

  it("returns null for anything that is not one of the presets", () => {
    expect(termMonthsBetween("2026-01-01", "2026-12-30")).toBeNull();
    expect(termMonthsBetween("2026-01-01", "2026-10-31")).toBeNull(); // 10 months, not offered
    expect(termMonthsBetween("", "2026-12-31")).toBeNull();
  });

  it("exposes the presets in display order", () => {
    expect(AGREEMENT_TERM_PRESETS).toEqual([6, 12, 24]);
  });
});
