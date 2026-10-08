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
    ssmNumber: null, // TODO(legal): SSM registration number
    registeredAddress: null, // TODO(legal): registered business address
    website: "https://axelnovaventures.com",
  },
  contact: {
    email: null, // TODO(legal): general contact email (suspended.vue already links support@roofly.my — confirm it's monitored)
    privacyEmail: null, // TODO(legal): privacy contact email for PDPA requests
    phone: null, // TODO(legal): business phone number
  },
  documents: {
    privacy: { version: "0.1", effectiveDate: null }, // TODO(legal): effective date once reviewed
    terms: { version: "0.1", effectiveDate: null }, // TODO(legal): effective date once reviewed
    billing: { version: "0.1", effectiveDate: null }, // TODO(legal): effective date once reviewed
    "acceptable-use": { version: "0.1", effectiveDate: null }, // TODO(legal): effective date once reviewed
    beta: { version: "0.1", effectiveDate: null }, // TODO(legal): effective date once reviewed
  },
};
