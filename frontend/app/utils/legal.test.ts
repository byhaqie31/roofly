import { describe, expect, it } from "vitest";
import type { LegalConfig } from "~/config/legal";
import { LEGAL } from "~/config/legal";
import {
  availableLegalSlugs,
  contactOptions,
  legalPath,
  scopeLegalHref,
  footerLinks,
  formatLegalDate,
  inlineSegments,
  isLegalPath,
  isLegalSlugAvailable,
  operatorLines,
} from "./legal";

const config = (over: { operator?: Partial<LegalConfig["operator"]>; contact?: Partial<LegalConfig["contact"]> } = {}): LegalConfig => ({
  ...LEGAL,
  operator: { ...LEGAL.operator, ssmNumber: null, registeredAddress: null, ...over.operator },
  contact: { email: null, privacyEmail: null, phone: null, ...over.contact },
});

const keys = (links: { key: string }[]) => links.map((l) => l.key);

describe("footerLinks", () => {
  it("full footer: all four public documents, no beta outside UAT", () => {
    expect(keys(footerLinks("full", { showBetaTerms: false }))).toEqual(["privacy", "terms", "billing", "acceptable-use"]);
  });

  it("slim footer: privacy and terms only", () => {
    expect(keys(footerLinks("slim", { showBetaTerms: false }))).toEqual(["privacy", "terms"]);
  });

  it("shell footer links the help page when given one", () => {
    expect(keys(footerLinks("shell", { showBetaTerms: false, helpTo: "/owner/help" }))).toEqual(["privacy", "terms", "help"]);
    expect(footerLinks("shell", { showBetaTerms: false, helpTo: "/tenant/help" }).at(-1)?.to).toBe("/tenant/help");
    expect(keys(footerLinks("shell", { showBetaTerms: false }))).toEqual(["privacy", "terms"]);
  });

  it("shows the beta terms link only when the beta flag is on", () => {
    for (const variant of ["full", "slim", "shell"] as const) {
      expect(keys(footerLinks(variant, { showBetaTerms: false }))).not.toContain("beta");
      expect(keys(footerLinks(variant, { showBetaTerms: true }))).toContain("beta");
    }
  });

  it("admin footer: privacy and terms, never beta", () => {
    expect(keys(footerLinks("admin", { showBetaTerms: true }))).toEqual(["privacy", "terms"]);
  });

  it("points document links at /legal/<slug>", () => {
    for (const link of footerLinks("full", { showBetaTerms: true })) {
      expect(link.to).toBe(`/legal/${link.key}`);
      expect(link.labelKey).toBe(`legal.docs.${link.key}`);
    }
  });
});

describe("in-app legal links", () => {
  it("points shell footer links at the in-app copies", () => {
    const links = footerLinks("shell", { showBetaTerms: true, helpTo: "/owner/help", legalBase: "/owner/legal" });
    expect(links.map((l) => l.to)).toEqual(["/owner/legal/privacy", "/owner/legal/terms", "/owner/legal/beta", "/owner/help"]);
    expect(footerLinks("slim", { showBetaTerms: false }).map((l) => l.to)).toEqual(["/legal/privacy", "/legal/terms"]);
  });

  it("builds paths under a base", () => {
    expect(legalPath("terms")).toBe("/legal/terms");
    expect(legalPath("terms", "/tenant/legal")).toBe("/tenant/legal/terms");
  });

  it("rewrites a document's own legal links to stay in the app", () => {
    expect(scopeLegalHref("/legal/acceptable-use", "/owner/legal")).toBe("/owner/legal/acceptable-use");
    expect(scopeLegalHref("/legal/terms")).toBe("/legal/terms");
    expect(scopeLegalHref("mailto:hello@example.com", "/owner/legal")).toBe("mailto:hello@example.com");
    expect(scopeLegalHref("/legalese", "/owner/legal")).toBe("/legalese");
  });
});

describe("operatorLines", () => {
  it("drops every null field and keeps the operator name", () => {
    expect(operatorLines(config())).toEqual([{ key: "name", value: LEGAL.operator.name }]);
  });

  it("includes filled fields in display order with contact hrefs", () => {
    const lines = operatorLines(
      config({
        operator: { ssmNumber: "202601000001", registeredAddress: "Kuala Lumpur" },
        contact: { email: "hello@example.com", phone: "+60 12-345 6789" },
      }),
    );
    expect(lines.map((l) => l.key)).toEqual(["name", "ssmNumber", "registeredAddress", "email", "phone"]);
    expect(lines.find((l) => l.key === "email")?.href).toBe("mailto:hello@example.com");
    expect(lines.find((l) => l.key === "phone")?.href).toBe("tel:+60123456789");
  });

  it("can leave the contact methods out", () => {
    const lines = operatorLines(config({ contact: { email: "hello@example.com" } }), { includeContact: false });
    expect(lines.map((l) => l.key)).toEqual(["name"]);
  });

  it("uses the privacy email on the privacy channel, falling back to the general one", () => {
    const both = config({ contact: { email: "hello@example.com", privacyEmail: "privacy@example.com" } });
    expect(operatorLines(both, { channel: "privacy" }).find((l) => l.href?.startsWith("mailto:"))).toMatchObject({
      key: "privacyEmail",
      value: "privacy@example.com",
    });
    expect(operatorLines(both).find((l) => l.href?.startsWith("mailto:"))?.key).toBe("email");
    const generalOnly = config({ contact: { email: "hello@example.com" } });
    expect(operatorLines(generalOnly, { channel: "privacy" }).find((l) => l.href?.startsWith("mailto:"))?.key).toBe("email");
    expect(operatorLines(config(), { channel: "privacy" }).map((l) => l.key)).toEqual(["name"]);
  });

  it("treats blank strings like null", () => {
    expect(operatorLines(config({ operator: { ssmNumber: "  " } })).map((l) => l.key)).toEqual(["name"]);
  });
});

describe("contactOptions", () => {
  it("offers email, call and WhatsApp from the configured contact details", () => {
    const opts = contactOptions(config({ contact: { email: "hello@example.com", phone: "+60 18-317 3103" } }), { whatsappText: "Hi" });
    expect(opts.map((o) => o.key)).toEqual(["email", "call", "whatsapp"]);
    expect(opts[0]).toMatchObject({ href: "mailto:hello@example.com", external: false });
    expect(opts[1]).toMatchObject({ href: "tel:+60183173103", external: false });
    expect(opts[2]).toMatchObject({ href: "https://wa.me/60183173103?text=Hi", external: true });
  });

  it("drops what isn't configured, and is empty with no contact details", () => {
    expect(contactOptions(config({ contact: { email: "hello@example.com" } })).map((o) => o.key)).toEqual(["email"]);
    expect(contactOptions(config({ contact: { phone: "012-345 6789" } })).map((o) => o.key)).toEqual(["call", "whatsapp"]);
    expect(contactOptions(config())).toEqual([]);
  });
});

describe("legal slugs", () => {
  it("recognises legal paths", () => {
    expect(isLegalPath("/legal/privacy")).toBe(true);
    expect(isLegalPath("/legal")).toBe(true);
    expect(isLegalPath("/legalese")).toBe(false);
    expect(isLegalPath("/owner/legal")).toBe(false);
  });

  it("hides the beta terms unless the beta flag is on", () => {
    expect(isLegalSlugAvailable("beta", { showBetaTerms: false })).toBe(false);
    expect(isLegalSlugAvailable("beta", { showBetaTerms: true })).toBe(true);
    expect(isLegalSlugAvailable("privacy", { showBetaTerms: false })).toBe(true);
    expect(isLegalSlugAvailable("cookies", { showBetaTerms: true })).toBe(false);
    expect(availableLegalSlugs({ showBetaTerms: false })).not.toContain("beta");
  });
});

describe("inlineSegments", () => {
  it("splits [label](href) links out of plain text", () => {
    expect(inlineSegments("Read our [privacy notice](/legal/privacy) first.")).toEqual([
      { text: "Read our " },
      { text: "privacy notice", href: "/legal/privacy" },
      { text: " first." },
    ]);
  });

  it("returns plain text untouched", () => {
    expect(inlineSegments("No links here.")).toEqual([{ text: "No links here." }]);
  });
});

describe("formatLegalDate", () => {
  it("formats per locale and passes null through", () => {
    expect(formatLegalDate(null, "en")).toBeNull();
    expect(formatLegalDate("2026-10-08", "en")).toContain("2026");
    expect(formatLegalDate("not-a-date", "en")).toBeNull();
  });
});
