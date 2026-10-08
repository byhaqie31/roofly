import { describe, expect, it } from "vitest";
import { maskEmail, maskName } from "./privacyMask";

// Same cases as backend tests/Unit/PrivacyMaskTest.php — the two must agree.
describe("maskName", () => {
  it("keeps the first name and the last initial", () => {
    expect(maskName("Aminah Binti Yusof")).toBe("Aminah Y.");
    expect(maskName("  Lim   Li Wei ")).toBe("Lim W.");
    expect(maskName("Ravi")).toBe("Ravi");
    expect(maskName("")).toBe("•••");
    expect(maskName(null)).toBe("•••");
  });
});

describe("maskEmail", () => {
  it("keeps two characters and the domain", () => {
    expect(maskEmail("aminah.yusof@example.com")).toBe("am•••@example.com");
    expect(maskEmail("ab@example.com")).toBe("a•••@example.com");
    expect(maskEmail("not-an-email")).toBe("•••");
    expect(maskEmail("@example.com")).toBe("•••");
    expect(maskEmail(undefined)).toBe("•••");
  });
});
