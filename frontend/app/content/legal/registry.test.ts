import { describe, expect, it } from "vitest";
import { LEGAL_DOCUMENTS, LEGAL_LOCALES, getLegalDocument } from "./index";
import type { LegalDocumentContent } from "./types";
import { LEGAL } from "~/config/legal";
import { LEGAL_SLUGS, inlineSegments, isLegalSlugAvailable } from "~/utils/legal";

/** Every string a reader will see in a document. */
const renderedStrings = (doc: LegalDocumentContent): string[] => [
  doc.title,
  doc.summary,
  ...doc.sections.flatMap((s) => [
    s.heading,
    ...s.blocks.flatMap((b) => (b.type === "p" ? [b.text] : b.type === "ul" ? b.items : [])),
  ]),
];

describe("legal content registry", () => {
  it("has a config entry and both locales for every slug", () => {
    for (const slug of LEGAL_SLUGS) {
      expect(LEGAL.documents[slug]).toBeDefined();
      for (const locale of LEGAL_LOCALES) {
        const doc = LEGAL_DOCUMENTS[slug][locale];
        expect(doc, `${slug}/${locale}`).toBeDefined();
        expect(doc.title.trim()).not.toBe("");
        expect(doc.sections.length).toBeGreaterThan(0);
      }
    }
  });

  it("resolves every page in both locales, falling back to English", () => {
    for (const slug of LEGAL_SLUGS) {
      expect(getLegalDocument(slug, "en")).toBe(LEGAL_DOCUMENTS[slug].en);
      expect(getLegalDocument(slug, "ms")).toBe(LEGAL_DOCUMENTS[slug].ms);
      expect(getLegalDocument(slug, "fr")).toBe(LEGAL_DOCUMENTS[slug].en);
    }
  });

  it("keeps the same sections, in the same order, in EN and BM", () => {
    for (const slug of LEGAL_SLUGS) {
      const ids = (l: "en" | "ms") => LEGAL_DOCUMENTS[slug][l].sections.map((s) => s.id);
      expect(ids("ms"), slug).toEqual(ids("en"));
      // Same block shapes too, so a contact/plans block isn't dropped in one language.
      const shapes = (l: "en" | "ms") => LEGAL_DOCUMENTS[slug][l].sections.map((s) => s.blocks.map((b) => b.type).join(","));
      expect(shapes("ms"), slug).toEqual(shapes("en"));
    }
  });

  it("uses unique section ids within a document", () => {
    for (const slug of LEGAL_SLUGS) {
      const ids = LEGAL_DOCUMENTS[slug].en.sections.map((s) => s.id);
      expect(new Set(ids).size).toBe(ids.length);
    }
  });

  it("never shows a TODO or a placeholder to readers", () => {
    for (const slug of LEGAL_SLUGS) {
      for (const locale of LEGAL_LOCALES) {
        for (const text of renderedStrings(LEGAL_DOCUMENTS[slug][locale])) {
          expect(text, `${slug}/${locale}`).not.toMatch(/TODO|TBD|XXX|lorem|\{\w+\}/i);
        }
      }
    }
  });

  it("only links to legal pages that exist everywhere", () => {
    for (const slug of LEGAL_SLUGS) {
      for (const locale of LEGAL_LOCALES) {
        for (const text of renderedStrings(LEGAL_DOCUMENTS[slug][locale])) {
          for (const seg of inlineSegments(text)) {
            if (!seg.href) continue;
            const target = seg.href.replace(/^\/legal\//, "");
            // Beta terms are UAT-only, so no other document may link to them.
            expect(isLegalSlugAvailable(target, { showBetaTerms: false }), `${slug}/${locale} → ${seg.href}`).toBe(true);
          }
        }
      }
    }
  });
});
