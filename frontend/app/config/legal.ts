/**
 * Single source of truth for the legal baseline: who operates Roofly, how to
 * reach us, and the version + effective date of each legal document.
 *
 * Read by the legal pages (`pages/legal/[doc].vue`), every footer
 * (`components/layout/SiteFooter.vue`) and the help pages' Legal section.
 * A `null` value renders nothing — never a public placeholder. Fill each
 * `TODO(legal)` with the real value before these pages are relied on.
 */

export type LegalSlug = "privacy" | "terms" | "billing" | "acceptable-use" | "beta";

export interface LegalDocumentMeta {
  /** Bump on every published change to the document's copy. */
  version: string;
  /** ISO date (YYYY-MM-DD) the version takes effect. */
  effectiveDate: string | null;
}

export interface LegalConfig {
  operator: {
    name: string;
    /** SSM business registration number. */
    ssmNumber: string | null;
    registeredAddress: string | null;
    website: string | null;
  };
  contact: {
    /** General enquiries — shown as "Contact" in the full footer. */
    email: string | null;
    /** Access / correction / withdrawal requests under the PDPA. */
    privacyEmail: string | null;
    phone: string | null;
  };
  documents: Record<LegalSlug, LegalDocumentMeta>;
}

export const LEGAL: LegalConfig = {
  operator: {
    name: "Axel Nova Ventures",
    ssmNumber: "202603119899 (CA0420977-U)",
    registeredAddress: "I-City, Seksyen 7, Shah Alam, Selangor",
    website: "https://axelnovaventures.com",
  },
  contact: {
    email: "baihaqie@axelnova.tech",
    privacyEmail: "baihaqie@axelnova.tech", // TODO(legal): confirm inbox for Roofly PDPA requests
    phone: "+60183173103", // TODO(legal): confirm current business number
  },
  documents: {
    privacy: {
      version: "1.0",
      effectiveDate: "2026-10-08",
    },
    terms: {
      version: "1.0",
      effectiveDate: "2026-10-08",
    },
    billing: {
      version: "1.0",
      effectiveDate: "2026-10-08",
    },
    "acceptable-use": {
      version: "1.0",
      effectiveDate: "2026-10-08",
    },
    beta: {
      version: "1.0",
      effectiveDate: "2026-10-08",
    },
  },
};
