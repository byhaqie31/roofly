import { describe, expect, it } from "vitest";
import { dateOfBirthFromMyKad, formatMyKadInput, normalizeMyKad } from "./mykad";

describe("normalizeMyKad", () => {
  it("accepts 12 bare digits and returns the canonical dashed form", () => {
    expect(normalizeMyKad("880314145687")).toBe("880314-14-5687");
  });

  it("accepts dashed or spaced input", () => {
    expect(normalizeMyKad("880314-14-5687")).toBe("880314-14-5687");
    expect(normalizeMyKad(" 880314 14 5687 ")).toBe("880314-14-5687");
  });

  it("rejects anything that is not exactly 12 digits", () => {
    expect(normalizeMyKad("88031414568")).toBeNull();
    expect(normalizeMyKad("8803141456870")).toBeNull();
    expect(normalizeMyKad("")).toBeNull();
    expect(normalizeMyKad("A1234567")).toBeNull();
  });
});

describe("formatMyKadInput", () => {
  it("inserts dashes after the 6th and 8th digits while typing", () => {
    expect(formatMyKadInput("8803")).toBe("8803");
    expect(formatMyKadInput("8803141")).toBe("880314-1");
    expect(formatMyKadInput("88031414")).toBe("880314-14");
    expect(formatMyKadInput("880314145687")).toBe("880314-14-5687");
  });

  it("drops non-digits and anything past 12 digits", () => {
    expect(formatMyKadInput("880314-14-5687999")).toBe("880314-14-5687");
    expect(formatMyKadInput("88ab03")).toBe("8803");
  });
});

describe("dateOfBirthFromMyKad", () => {
  const today = new Date("2026-10-07T00:00:00Z");

  it("reads YYMMDD, picking the century that does not land in the future", () => {
    expect(dateOfBirthFromMyKad("880314145687", today)).toBe("1988-03-14");
    expect(dateOfBirthFromMyKad("050101-01-0001", today)).toBe("2005-01-01");
    expect(dateOfBirthFromMyKad("260314-14-5687", today)).toBe("2026-03-14"); // earlier this year
    expect(dateOfBirthFromMyKad("261231-14-5687", today)).toBe("1926-12-31"); // would be in the future
  });

  it("returns null for incomplete numbers or impossible dates", () => {
    expect(dateOfBirthFromMyKad("8803", today)).toBeNull();
    expect(dateOfBirthFromMyKad("881314145687", today)).toBeNull(); // month 13
    expect(dateOfBirthFromMyKad("880230145687", today)).toBeNull(); // 30 Feb
  });
});
