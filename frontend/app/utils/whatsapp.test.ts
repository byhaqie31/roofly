import { describe, expect, it } from "vitest";
import { whatsappNumber, whatsappShareUrl } from "./whatsapp";

describe("whatsappNumber", () => {
  it("strips formatting from a Malaysian number that already has the country code", () => {
    expect(whatsappNumber("+60 12-345 6789")).toBe("60123456789");
  });

  it("replaces a leading 0 with Malaysia's country code", () => {
    expect(whatsappNumber("012-345 6789")).toBe("60123456789");
  });

  it("keeps a foreign number's country code", () => {
    expect(whatsappNumber("+65 9123 4567")).toBe("6591234567");
  });

  it("returns null when there are not enough digits to dial", () => {
    expect(whatsappNumber("")).toBeNull();
    expect(whatsappNumber("12")).toBeNull();
  });
});

describe("whatsappShareUrl", () => {
  it("targets the tenant's number with the message URL-encoded", () => {
    const url = whatsappShareUrl("+60 12-345 6789", "Hi Adi, here is your link: https://x.my/a?b=1&c=2");
    expect(url).toBe(
      "https://wa.me/60123456789?text=Hi%20Adi%2C%20here%20is%20your%20link%3A%20https%3A%2F%2Fx.my%2Fa%3Fb%3D1%26c%3D2",
    );
  });

  it("falls back to a recipient-less share when the number is unusable", () => {
    expect(whatsappShareUrl("", "hello")).toBe("https://wa.me/?text=hello");
  });
});
